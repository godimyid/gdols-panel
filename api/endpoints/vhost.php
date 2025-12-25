<?php
/**
 * GDOLS Panel - Virtual Hosts API Endpoint
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles virtual host CRUD operations
 */

// Load bootstrap
require_once dirname(__DIR__) . "/bootstrap.php";

// Get request method and action
$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

try {
    switch ($action) {
        case "list":
            handleList();
            break;

        case "get":
            handleGet();
            break;

        case "create":
            handleCreate();
            break;

        case "update":
            handleUpdate();
            break;

        case "delete":
            handleDelete();
            break;

        case "check_domain":
            handleCheckDomain();
            break;

        default:
            errorResponse("Invalid action", 400);
    }
} catch (Exception $e) {
    errorResponse("An error occurred: " . $e->getMessage(), 500);
}

/**
 * Handle list virtual hosts
 */
function handleList()
{
    global $conn, $system;

    requireAdmin();

    try {
        $stmt = $conn->prepare("
            SELECT vh.*,
                   (SELECT COUNT(*) FROM databases WHERE vhost_id = vh.id) as database_count
            FROM virtual_hosts vh
            ORDER BY vh.created_at DESC
        ");
        $stmt->execute();
        $vhosts = $stmt->fetchAll();

        successResponse(
            [
                "vhosts" => $vhosts,
                "total" => count($vhosts),
                "active" => count(
                    array_filter($vhosts, fn($v) => $v["status"] === "active"),
                ),
            ],
            "Virtual hosts retrieved successfully",
        );
    } catch (PDOException $e) {
        $logger->logError("Failed to list virtual hosts: " . $e->getMessage());
        errorResponse("Failed to retrieve virtual hosts", 500);
    }
}

/**
 * Handle get single virtual host
 */
function handleGet()
{
    global $conn;

    requireAdmin();

    $id = $_GET["id"] ?? "";

    if (empty($id)) {
        errorResponse("Virtual host ID is required");
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM virtual_hosts WHERE id = ?");
        $stmt->execute([$id]);
        $vhost = $stmt->fetch();

        if (!$vhost) {
            errorResponse("Virtual host not found", 404);
        }

        // Get SSL info if enabled
        if ($vhost["ssl_enabled"]) {
            $vhost["ssl_info"] = getSSLInfo($vhost["domain"]);
        }

        successResponse($vhost, "Virtual host retrieved successfully");
    } catch (PDOException $e) {
        errorResponse("Failed to retrieve virtual host", 500);
    }
}

/**
 * Handle create virtual host
 */
function handleCreate()
{
    global $conn, $system, $logger;

    requireAdmin();

    // Only allow POST
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        errorResponse("Method not allowed", 405);
    }

    // Get input
    $input = json_decode(file_get_contents("php://input"), true);

    // Validate required fields
    $required = ["domain", "email", "type"];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("Field '{$field}' is required");
        }
    }

    $domain = trim($input["domain"]);
    $email = trim($input["email"]);
    $type = $input["type"];
    $docroot = !empty($input["docroot"])
        ? trim($input["docroot"])
        : DEFAULT_DOCROOT . "/" . $domain;

    // Validate domain format
    if (
        !filter_var($domain, FILTER_VALIDATE_DOMAIN) &&
        !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-._]*[a-zA-Z0-9]$/', $domain)
    ) {
        errorResponse("Invalid domain format");
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorResponse("Invalid email format");
    }

    // Validate type
    if (!in_array($type, ["wordpress", "custom", "proxy"])) {
        errorResponse(
            "Invalid virtual host type. Must be: wordpress, custom, or proxy",
        );
    }

    // For proxy type, validate backend
    if ($type === "proxy") {
        if (empty($input["backend_port"])) {
            errorResponse("Backend port is required for proxy type");
        }
        if (
            !is_numeric($input["backend_port"]) ||
            $input["backend_port"] < 1 ||
            $input["backend_port"] > 65535
        ) {
            errorResponse("Invalid backend port");
        }
    }

    // Check if domain already exists
    try {
        $stmt = $conn->prepare("SELECT id FROM virtual_hosts WHERE domain = ?");
        $stmt->execute([$domain]);
        if ($stmt->fetch()) {
            errorResponse("Domain already exists");
        }

        // Check DNS resolution (optional, for validation)
        if (checkDNSExists($domain)) {
            // Domain exists in DNS, this is good
        }
    } catch (PDOException $e) {
        errorResponse("Database error checking domain", 500);
    }

    // Create virtual host using system class
    $result = $system->createVirtualHost([
        "domain" => $domain,
        "email" => $email,
        "type" => $type,
        "docroot" => $docroot,
        "backend_host" => $input["backend_host"] ?? "127.0.0.1",
        "backend_port" => $input["backend_port"] ?? null,
        "uri" => $input["uri"] ?? "/",
    ]);

    if (!$result["success"]) {
        errorResponse(
            $result["message"] ?? "Failed to create virtual host",
            500,
        );
    }

    $logger->logActivity("vhost_create", "virtual_host", $result["id"], [
        "domain" => $domain,
        "type" => $type,
        "docroot" => $docroot,
    ]);

    successResponse(
        [
            "id" => $result["id"],
            "domain" => $domain,
            "type" => $type,
            "docroot" => $docroot,
        ],
        "Virtual host created successfully",
    );
}

/**
 * Handle update virtual host
 */
function handleUpdate()
{
    global $conn, $system, $logger;

    requireAdmin();

    // Only allow POST/PUT
    if (!in_array($_SERVER["REQUEST_METHOD"], ["POST", "PUT"])) {
        errorResponse("Method not allowed", 405);
    }

    $id = $_GET["id"] ?? "";
    if (empty($id)) {
        errorResponse("Virtual host ID is required");
    }

    // Get input
    $input = json_decode(file_get_contents("php://input"), true);

    // Get existing vhost
    try {
        $stmt = $conn->prepare("SELECT * FROM virtual_hosts WHERE id = ?");
        $stmt->execute([$id]);
        $vhost = $stmt->fetch();

        if (!$vhost) {
            errorResponse("Virtual host not found", 404);
        }

        // Update fields
        $updates = [];
        $params = [];

        if (!empty($input["email"])) {
            if (!filter_var($input["email"], FILTER_VALIDATE_EMAIL)) {
                errorResponse("Invalid email format");
            }
            $updates[] = "email = ?";
            $params[] = trim($input["email"]);
        }

        if (
            !empty($input["status"]) &&
            in_array($input["status"], ["active", "suspended", "pending"])
        ) {
            $updates[] = "status = ?";
            $params[] = $input["status"];
        }

        if (isset($input["ssl_enabled"])) {
            $updates[] = "ssl_enabled = ?";
            $params[] = $input["ssl_enabled"] ? 1 : 0;
        }

        if (!empty($input["ssl_cert"])) {
            $updates[] = "ssl_cert = ?";
            $params[] = $input["ssl_cert"];
        }

        if (!empty($input["ssl_key"])) {
            $updates[] = "ssl_key = ?";
            $params[] = $input["ssl_key"];
        }

        if (!empty($input["backend_host"]) && $vhost["type"] === "proxy") {
            $updates[] = "backend_host = ?";
            $params[] = $input["backend_host"];
        }

        if (!empty($input["backend_port"]) && $vhost["type"] === "proxy") {
            if (
                !is_numeric($input["backend_port"]) ||
                $input["backend_port"] < 1 ||
                $input["backend_port"] > 65535
            ) {
                errorResponse("Invalid backend port");
            }
            $updates[] = "backend_port = ?";
            $params[] = $input["backend_port"];
        }

        if (empty($updates)) {
            errorResponse("No fields to update");
        }

        $params[] = $id;

        $sql =
            "UPDATE virtual_hosts SET " .
            implode(", ", $updates) .
            " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $logger->logActivity("vhost_update", "virtual_host", $id, [
            "domain" => $vhost["domain"],
            "updates" => $updates,
        ]);

        successResponse(
            [
                "id" => $id,
                "updated_fields" => $updates,
            ],
            "Virtual host updated successfully",
        );
    } catch (PDOException $e) {
        $logger->logError("Failed to update virtual host: " . $e->getMessage());
        errorResponse("Failed to update virtual host", 500);
    }
}

/**
 * Handle delete virtual host
 */
function handleDelete()
{
    global $system, $logger;

    requireAdmin();

    // Only allow POST/DELETE
    if (!in_array($_SERVER["REQUEST_METHOD"], ["POST", "DELETE"])) {
        errorResponse("Method not allowed", 405);
    }

    $id = $_GET["id"] ?? "";
    if (empty($id)) {
        errorResponse("Virtual host ID is required");
    }

    // Confirm deletion
    $confirm = $_GET["confirm"] ?? "false";
    if ($confirm !== "true") {
        errorResponse("Please confirm deletion by adding ?confirm=true", 400);
    }

    // Check if database should also be deleted
    $deleteDatabase = $_GET["delete_database"] ?? "false";
    $deleteDatabase = filter_var($deleteDatabase, FILTER_VALIDATE_BOOLEAN);

    // Get vhost info before deletion
    global $conn;
    try {
        $stmt = $conn->prepare(
            "SELECT domain, db_name, db_user FROM virtual_hosts WHERE id = ?",
        );
        $stmt->execute([$id]);
        $vhost = $stmt->fetch();

        if (!$vhost) {
            errorResponse("Virtual host not found", 404);
        }

        $domain = $vhost["domain"];
    } catch (PDOException $e) {
        errorResponse("Failed to retrieve virtual host", 500);
    }

    // Delete using system class
    $result = $system->deleteVirtualHost($domain, $deleteDatabase);

    if (!$result["success"]) {
        errorResponse(
            $result["message"] ?? "Failed to delete virtual host",
            500,
        );
    }

    $logger->logActivity("vhost_delete", "virtual_host", $id, [
        "domain" => $domain,
        "database_deleted" => $result["database_deleted"] ?? false,
    ]);

    successResponse(
        [
            "id" => $id,
            "domain" => $domain,
            "database_deleted" => $result["database_deleted"] ?? false,
        ],
        "Virtual host deleted successfully",
    );
}

/**
 * Handle check domain availability
 */
function handleCheckDomain()
{
    global $conn;

    requireAdmin();

    $domain = $_GET["domain"] ?? "";

    if (empty($domain)) {
        errorResponse("Domain is required");
    }

    try {
        // Check in database
        $stmt = $conn->prepare(
            "SELECT id, domain, status FROM virtual_hosts WHERE domain = ?",
        );
        $stmt->execute([$domain]);
        $exists = $stmt->fetch();

        $result = [
            "domain" => $domain,
            "available" => !$exists,
            "exists" => !!$exists,
            "current_vhost" => $exists,
        ];

        if ($exists) {
            $result["message"] = "Domain already exists in panel";
        } else {
            $result["message"] = "Domain is available";
            // Check DNS
            $result["dns"] = checkDNSExists($domain);
        }

        successResponse($result, "Domain check completed");
    } catch (PDOException $e) {
        errorResponse("Failed to check domain", 500);
    }
}

/**
 * Helper: Check if domain exists in DNS
 */
function checkDNSExists($domain)
{
    $records = @dns_get_record($domain, DNS_A + DNS_AAAA);

    $result = [
        "has_dns" => !empty($records),
        "records" => $records ?: [],
    ];

    if (!empty($records)) {
        $result["ip"] = $records[0]["ip"] ?? null;
        $result["ipv6"] = $records[0]["ipv6"] ?? null;
    }

    return $result;
}

/**
 * Helper: Get SSL certificate info
 */
function getSSLInfo($domain)
{
    $certFile = OLS_VHOSTS . "/{$domain}/cert/{$domain}.crt";

    if (!file_exists($certFile)) {
        return [
            "has_certificate" => false,
        ];
    }

    $certData = file_get_contents($certFile);
    $certInfo = openssl_x509_parse($certData);

    if (!$certInfo) {
        return [
            "has_certificate" => true,
            "valid" => false,
            "error" => "Invalid certificate",
        ];
    }

    return [
        "has_certificate" => true,
        "valid" => true,
        "subject" => $certInfo["subject"]["CN"] ?? "",
        "issuer" => $certInfo["issuer"]["O"] ?? "",
        "valid_from" => date("Y-m-d H:i:s", $certInfo["validFrom_time_t"]),
        "valid_to" => date("Y-m-d H:i:s", $certInfo["validTo_time_t"]),
        "expired" => $certInfo["validTo_time_t"] < time(),
    ];
}
