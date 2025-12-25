<?php
/**
 * GDOLS Panel - API Bootstrap File
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Main bootstrap file for API initialization
 */

// Define access constant
define("GDOLS_PANEL_ACCESS", true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set("session.cookie_httponly", 1);
    ini_set("session.cookie_secure", 0); // Set to 1 if using HTTPS
    ini_set("session.use_strict_mode", 1);
    ini_set("session.cookie_samesite", "Strict");

    session_name(SESSION_NAME);
    session_start();
}

// Load configuration
require_once dirname(__DIR__) . "/config/config.php";

// Load additional classes
require_once dirname(__DIR__) . "/config/database.php";
require_once dirname(__DIR__) . "/api/classes/Security.php";
require_once dirname(__DIR__) . "/api/classes/Logger.php";
require_once dirname(__DIR__) . "/api/classes/System.php";

// Set timezone
date_default_timezone_set(PANEL_TIMEZONE);

// Initialize database
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    die("Database initialization failed. Please check configuration.");
}

// Initialize security
$security = Security::getInstance();

// Initialize logger
$logger = Logger::getInstance();

// Initialize system
$system = System::getInstance();

// Set JSON header for API responses
header("Content-Type: application/json; charset=utf-8");

// CORS headers (adjust for production)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Rate limiting
$security->checkRateLimit();

// CSRF validation for POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$security->validateCSRF()) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "CSRF token validation failed",
    ]);
    exit();
}

// Log API access
$logger->logAccess();

// Initialize user session
$user = null;
if (isset($_SESSION["user_id"])) {
    try {
        $stmt = $conn->prepare(
            "SELECT * FROM users WHERE id = ? AND status = 'active'",
        );
        $stmt->execute([$_SESSION["user_id"]]);
        $user = $stmt->fetch();

        if (!$user) {
            // Invalid session, destroy it
            session_destroy();
            unset($_SESSION["user_id"]);
        }
    } catch (PDOException $e) {
        error_log("User session validation failed: " . $e->getMessage());
    }
}

// Helper function to send JSON response
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

// Helper function to send error response
function errorResponse($message, $statusCode = 400, $errors = [])
{
    jsonResponse(
        [
            "success" => false,
            "message" => $message,
            "errors" => $errors,
        ],
        $statusCode,
    );
}

// Helper function to send success response
function successResponse($data, $message = "Success")
{
    jsonResponse([
        "success" => true,
        "message" => $message,
        "data" => $data,
    ]);
}

// Check authentication helper
function requireAuth()
{
    global $user;
    if (!$user) {
        errorResponse("Authentication required", 401);
    }
    return $user;
}

// Check admin role helper
function requireAdmin()
{
    $user = requireAuth();
    if ($user["role"] !== "admin") {
        errorResponse("Admin access required", 403);
    }
    return $user;
}

// Auto-generate CSRF token
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// Define current user globally
define("CURRENT_USER_ID", $user ? $user["id"] : null);
define("CURRENT_USER_ROLE", $user ? $user["role"] : null);

// Error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    global $logger;
    $logger->logError("Error: [$errno] $errstr in $errfile on line $errline");

    if (DEBUG_MODE) {
        errorResponse("Error: $errstr", 500);
    } else {
        errorResponse("An internal error occurred", 500);
    }
});

// Exception handler
set_exception_handler(function ($exception) {
    global $logger;
    $logger->logError(
        "Exception: " .
            $exception->getMessage() .
            " in " .
            $exception->getFile() .
            " on line " .
            $exception->getLine(),
    );

    if (DEBUG_MODE) {
        errorResponse($exception->getMessage(), 500);
    } else {
        errorResponse("An internal error occurred", 500);
    }
});

// Shutdown function
register_shutdown_function(function () {
    global $logger;
    $error = error_get_last();
    if (
        $error !== null &&
        in_array($error["type"], [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
        ])
    ) {
        $logger->logError(
            "Fatal Error: " .
                $error["message"] .
                " in " .
                $error["file"] .
                " on line " .
                $error["line"],
        );
    }
});
