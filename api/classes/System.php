<?php
/**
 * GDOLS Panel - System Class
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles system operations, server management, and command execution
 */

// Prevent direct access
if (!defined("GDOLS_PANEL_ACCESS")) {
    die("Direct access not permitted");
}

class System
{
    private static $instance = null;
    private $conn;
    private $logger;

    public function __construct()
    {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
        $this->logger = Logger::getInstance();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute shell command safely
     */
    public function executeCommand($command, $sudo = false)
    {
        $prefix = $sudo ? "sudo " : "";
        $fullCommand = $prefix . $command . " 2>&1";

        exec($fullCommand, $output, $returnCode);

        $this->logger->logActivity("command_exec", "system", null, [
            "command" => $fullCommand,
            "return_code" => $returnCode,
        ]);

        return [
            "success" => $returnCode === 0,
            "output" => implode("\n", $output),
            "return_code" => $returnCode,
        ];
    }

    /**
     * Get system information
     */
    public function getSystemInfo()
    {
        $info = [
            "hostname" => gethostname(),
            "os" => php_uname("s"),
            "kernel" => php_uname("r"),
            "architecture" => php_uname("m"),
            "php_version" => PHP_VERSION,
            "software" => [],
            "uptime" => "",
        ];

        // Get uptime
        $uptime = $this->executeCommand("uptime -s");
        if ($uptime["success"]) {
            $info["uptime"] = trim($uptime["output"]);
        }

        // Get OLS version
        $ols = $this->executeCommand(OLS_BIN . " version");
        if ($ols["success"]) {
            $info["software"]["openlitespeed"] = trim($ols["output"]);
        }

        // Get MariaDB version
        $mysql = $this->executeCommand("mysql --version");
        if ($mysql["success"]) {
            $info["software"]["mariadb"] = trim($mysql["output"]);
        }

        // Get Redis version
        $redis = $this->executeCommand("redis-server --version");
        if ($redis["success"]) {
            $info["software"]["redis"] = trim($redis["output"]);
        }

        return $info;
    }

    /**
     * Get system resource usage
     */
    public function getResourceUsage()
    {
        $usage = [
            "cpu" => $this->getCPUUsage(),
            "memory" => $this->getMemoryUsage(),
            "disk" => $this->getDiskUsage(),
            "network" => $this->getNetworkStats(),
        ];

        return $usage;
    }

    /**
     * Get CPU usage
     */
    private function getCPUUsage()
    {
        $load = sys_getloadavg();

        // Get CPU count
        $cpu = $this->executeCommand("nproc");
        $cpuCount = $cpu["success"] ? (int) trim($cpu["output"]) : 1;

        // Get CPU percentage
        $top = $this->executeCommand(
            "top -bn1 | grep 'Cpu(s)' | awk '{print $2}' | cut -d'%' -f1",
        );
        $cpuPercent = $top["success"] ? (float) trim($top["output"]) : 0;

        return [
            "load_average" => [
                "1min" => $load[0],
                "5min" => $load[1],
                "15min" => $load[2],
            ],
            "cores" => $cpuCount,
            "usage_percent" => $cpuPercent,
        ];
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage()
    {
        $meminfo = @file_get_contents("/proc/meminfo");
        if ($meminfo === false) {
            return ["error" => "Cannot read memory info"];
        }

        $data = [];
        foreach (explode("\n", $meminfo) as $line) {
            $parts = explode(":", $line);
            if (count($parts) == 2) {
                $data[trim($parts[0])] = (int) preg_replace(
                    "/[^0-9]/",
                    "",
                    $parts[1],
                );
            }
        }

        $total = $data["MemTotal"] ?? 0;
        $free = $data["MemFree"] ?? 0;
        $buffers = $data["Buffers"] ?? 0;
        $cached = $data["Cached"] ?? 0;
        $available = $free + $buffers + $cached;
        $used = $total - $available;

        return [
            "total" => $total * 1024, // Convert to bytes
            "used" => $used * 1024,
            "free" => $free * 1024,
            "available" => $available * 1024,
            "usage_percent" => $total > 0 ? ($used / $total) * 100 : 0,
            "buffers" => $buffers * 1024,
            "cached" => $cached * 1024,
        ];
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage()
    {
        $df = $this->executeCommand("df -h /");
        if (!$df["success"]) {
            return ["error" => "Cannot get disk usage"];
        }

        $lines = explode("\n", trim($df["output"]));
        if (count($lines) < 2) {
            return ["error" => "Invalid df output"];
        }

        $parts = preg_split("/\s+/", $lines[1]);

        return [
            "total" => $parts[1] ?? "N/A",
            "used" => $parts[2] ?? "N/A",
            "available" => $parts[3] ?? "N/A",
            "usage_percent" => (int) str_replace("%", "", $parts[4] ?? "0"),
            "mount" => $parts[5] ?? "/",
        ];
    }

    /**
     * Get network statistics
     */
    private function getNetworkStats()
    {
        $stats = [
            "interfaces" => [],
            "connections" => 0,
        ];

        // Get network interfaces
        $ip = $this->executeCommand("ip -o addr show | awk '{print $2, $4}'");
        if ($ip["success"]) {
            foreach (explode("\n", trim($ip["output"])) as $line) {
                $parts = explode(" ", $line);
                if (count($parts) == 2) {
                    $stats["interfaces"][$parts[0]] = $parts[1];
                }
            }
        }

        // Get connection count
        $ss = $this->executeCommand("ss -tun | wc -l");
        if ($ss["success"]) {
            $stats["connections"] = (int) trim($ss["output"]);
        }

        return $stats;
    }

    /**
     * Manage OpenLiteSpeed service
     */
    public function manageOLS($action)
    {
        $validActions = ["start", "stop", "restart", "reload", "status"];

        if (!in_array($action, $validActions)) {
            return [
                "success" => false,
                "message" => "Invalid action",
            ];
        }

        $result = $this->executeCommand(OLS_BIN . " $action", true);

        $this->logger->logActivity("ols_$action", "openlitespeed", null, [
            "result" => $result["success"],
        ]);

        return $result;
    }

    /**
     * Manage Redis service
     */
    public function manageRedis($action)
    {
        $validActions = ["start", "stop", "restart", "status"];

        if (!in_array($action, $validActions)) {
            return [
                "success" => false,
                "message" => "Invalid action",
            ];
        }

        $result = $this->executeCommand("systemctl $action redis", true);

        $this->logger->logActivity("redis_$action", "redis", null, [
            "result" => $result["success"],
        ]);

        return $result;
    }

    /**
     * Get Redis status
     */
    public function getRedisStatus()
    {
        // Check if Redis is running
        $status = $this->executeCommand("systemctl is-active redis");

        // Get Redis info
        $info = $this->executeCommand(
            'redis-cli INFO 2>/dev/null | grep -E "used_memory_human|connected_clients|uptime_in_days"',
        );

        return [
            "running" => trim($status["output"]) === "active",
            "info" => $info["output"],
        ];
    }

    /**
     * Update Redis configuration
     */
    public function updateRedisConfig($config)
    {
        $configFile = "/etc/redis/redis.conf";

        // Backup current config
        $this->executeCommand(
            "cp $configFile {$configFile}.bak." . time(),
            true,
        );

        $commands = [];

        if (isset($config["maxmemory"])) {
            $commands[] = "sed -i 's/^maxmemory.*/maxmemory {$config["maxmemory"]}/' $configFile";
        }

        if (isset($config["maxmemory_policy"])) {
            $commands[] = "sed -i 's/^maxmemory-policy.*/maxmemory-policy {$config["maxmemory_policy"]}/' $configFile";
        }

        if (isset($config["timeout"])) {
            $commands[] = "sed -i 's/^timeout.*/timeout {$config["timeout"]}/' $configFile";
        }

        if (isset($config["tcp_keepalive"])) {
            $commands[] = "sed -i 's/^tcp-keepalive.*/tcp-keepalive {$config["tcp_keepalive"]}/' $configFile";
        }

        foreach ($commands as $command) {
            $result = $this->executeCommand($command, true);
            if (!$result["success"]) {
                return [
                    "success" => false,
                    "message" => "Failed to update Redis config",
                ];
            }
        }

        // Restart Redis to apply changes
        $this->manageRedis("restart");

        // Update database
        try {
            $stmt = $this->conn->prepare(
                "UPDATE redis_config SET maxmemory = ?, maxmemory_policy = ?, timeout = ?, tcp_keepalive = ?",
            );
            $stmt->execute([
                $config["maxmemory"],
                $config["maxmemory_policy"],
                $config["timeout"],
                $config["tcp_keepalive"],
            ]);
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to update Redis config in database: " .
                    $e->getMessage(),
            );
        }

        $this->logger->logActivity(
            "redis_config_update",
            "redis",
            null,
            $config,
        );

        return [
            "success" => true,
            "message" => "Redis configuration updated successfully",
        ];
    }

    /**
     * Get PHP extensions status
     */
    public function getPHPExtensions()
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM php_extensions ORDER BY priority ASC",
            );
            $stmt->execute();
            $extensions = $stmt->fetchAll();

            // Check which are actually installed
            $php = $this->executeCommand(PHP_BIN . " -m");
            $installedModules = [];
            if ($php["success"]) {
                $installedModules = array_filter(explode("\n", $php["output"]));
            }

            foreach ($extensions as &$ext) {
                $ext["is_installed"] = in_array(
                    $ext["name"],
                    $installedModules,
                );
                $ext["can_enable"] =
                    $ext["is_installed"] ||
                    $ext["name"] === "imagick" ||
                    $ext["name"] === "ioncube";
            }

            return $extensions;
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to get PHP extensions: " . $e->getMessage(),
            );
            return [];
        }
    }

    /**
     * Install PHP extension
     */
    public function installPHPExtension($extensionName)
    {
        $validExtensions = PHP_EXTENSIONS_AVAILABLE;

        if (!in_array($extensionName, $validExtensions)) {
            return [
                "success" => false,
                "message" => "Invalid extension name",
            ];
        }

        $installCommands = [
            "imagick" => "apt install -y lsphp" . PHP_VERSION . "-imagick",
            "intl" => "apt install -y lsphp" . PHP_VERSION . "-intl",
            "redis" => "apt install -y lsphp" . PHP_VERSION . "-redis",
            "mysqli" => "apt install -y lsphp" . PHP_VERSION . "-mysql",
            "pdo" => "apt install -y lsphp" . PHP_VERSION . "-pdo",
            "pdo_mysql" => "apt install -y lsphp" . PHP_VERSION . "-mysql",
            "zip" => "apt install -y lsphp" . PHP_VERSION . "-zip",
            "gd" => "apt install -y lsphp" . PHP_VERSION . "-imagick",
            "curl" => "apt install -y lsphp" . PHP_VERSION . "-curl",
            "mbstring" => "apt install -y lsphp" . PHP_VERSION . "-mbstring",
            "xml" => "apt install -y lsphp" . PHP_VERSION . "-xml",
            "opcache" => "apt install -y lsphp" . PHP_VERSION . "-opcache",
            "apcu" => "apt install -y lsphp" . PHP_VERSION . "-apcu",
            "memcached" => "apt install -y lsphp" . PHP_VERSION . "-memcached",
            "imap" => "apt install -y lsphp" . PHP_VERSION . "-imap",
            "exif" => "apt install -y lsphp" . PHP_VERSION . "-exif",
            "fileinfo" => "apt install -y lsphp" . PHP_VERSION . "-fileinfo",
            "soap" => "apt install -y lsphp" . PHP_VERSION . "-soap",
            "xsl" => "apt install -y lsphp" . PHP_VERSION . "-xsl",
            "bz2" => "apt install -y lsphp" . PHP_VERSION . "-bz2",
            "zlib" => "apt install -y lsphp" . PHP_VERSION . "-common",
        ];

        // Special handling for ioncube
        if ($extensionName === "ioncube") {
            $result = $this->installIonCube();
        } else {
            $command = $installCommands[$extensionName] ?? null;
            if (!$command) {
                return [
                    "success" => false,
                    "message" =>
                        "No installation command available for this extension",
                ];
            }

            $result = $this->executeCommand($command, true);
        }

        if ($result["success"]) {
            // Update database
            try {
                $stmt = $this->conn->prepare(
                    "UPDATE php_extensions SET installed = 1, enabled = 1 WHERE name = ?",
                );
                $stmt->execute([$extensionName]);
            } catch (PDOException $e) {
                $this->logger->logError(
                    "Failed to update extension in database: " .
                        $e->getMessage(),
                );
            }

            $this->logger->logActivity(
                "extension_install",
                "php_extension",
                null,
                [
                    "extension" => $extensionName,
                ],
            );

            return [
                "success" => true,
                "message" => "Extension {$extensionName} installed successfully",
                "output" => $result["output"],
            ];
        }

        return [
            "success" => false,
            "message" => "Failed to install extension {$extensionName}",
            "output" => $result["output"],
        ];
    }

    /**
     * Install IonCube Loader
     */
    private function installIonCube()
    {
        // Download and install IonCube
        $commands = [
            "cd /tmp && wget -q http://downloads3.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz",
            "tar xzf ioncube_loaders_lin_x86-64.tar.gz",
            "cp ioncube/ioncube_loader_lin_8.3.so /usr/local/lsws/lsphp" .
            PHP_VERSION .
            "/lib/php/20230831/",
            'echo "zend_extension = /usr/local/lsws/lsphp' .
            PHP_VERSION .
            '/lib/php/20230831/ioncube_loader_lin_8.3.so" >> ' .
            PHP_INI,
        ];

        foreach ($commands as $cmd) {
            $result = $this->executeCommand($cmd, true);
            if (!$result["success"]) {
                return $result;
            }
        }

        // Restart OLS
        $this->manageOLS("restart");

        return [
            "success" => true,
            "message" => "IonCube Loader installed successfully",
        ];
    }

    /**
     * Enable/disable PHP extension
     */
    public function togglePHPExtension($extensionName, $enable = true)
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE php_extensions SET enabled = ? WHERE name = ?",
            );
            $stmt->execute([$enable ? 1 : 0, $extensionName]);

            $this->logger->logActivity(
                "extension_toggle",
                "php_extension",
                null,
                [
                    "extension" => $extensionName,
                    "enabled" => $enable,
                ],
            );

            return [
                "success" => true,
                "message" => $enable
                    ? "Extension {$extensionName} enabled"
                    : "Extension {$extensionName} disabled",
            ];
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to toggle extension: " . $e->getMessage(),
            );
            return [
                "success" => false,
                "message" => "Failed to toggle extension",
            ];
        }
    }

    /**
     * Manage firewall (UFW)
     */
    public function manageFirewall($action, $rule = [])
    {
        switch ($action) {
            case "status":
                return $this->getFirewallStatus();

            case "enable":
                $result = $this->executeCommand("ufw --force enable", true);
                break;

            case "disable":
                $result = $this->executeCommand("ufw --force disable", true);
                break;

            case "allow":
            case "deny":
                $port = $rule["port"] ?? "";
                $protocol = $rule["protocol"] ?? "tcp";
                $comment = $rule["comment"] ?? "";

                $command = "ufw {$action} {$port}/{$protocol}";
                if ($comment) {
                    $command .= " comment '{$comment}'";
                }

                $result = $this->executeCommand($command, true);

                if ($result["success"] && isset($rule["name"])) {
                    // Add to database
                    try {
                        $stmt = $this->conn->prepare("
                            INSERT INTO firewall_rules (rule_id, action, protocol, port, source, description, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            "RULE_" . strtoupper($rule["name"]) . "_" . time(),
                            $action,
                            $protocol,
                            $port,
                            $rule["source"] ?? "any",
                            $rule["description"] ?? $comment,
                            $_SESSION["user_id"] ?? 1,
                        ]);
                    } catch (PDOException $e) {
                        $this->logger->logError(
                            "Failed to save firewall rule: " . $e->getMessage(),
                        );
                    }
                }
                break;

            case "delete":
                $ruleId = $rule["rule_id"] ?? "";
                if ($ruleId) {
                    $result = $this->executeCommand(
                        "ufw delete {$ruleId}",
                        true,
                    );

                    // Remove from database
                    try {
                        $stmt = $this->conn->prepare(
                            "DELETE FROM firewall_rules WHERE rule_id = ?",
                        );
                        $stmt->execute([$ruleId]);
                    } catch (PDOException $e) {
                        $this->logger->logError(
                            "Failed to delete firewall rule from DB: " .
                                $e->getMessage(),
                        );
                    }
                } else {
                    $result = [
                        "success" => false,
                        "message" => "Rule ID required",
                    ];
                }
                break;

            default:
                return [
                    "success" => false,
                    "message" => "Invalid firewall action",
                ];
        }

        if (isset($result)) {
            $this->logger->logActivity(
                "firewall_" . $action,
                "firewall",
                null,
                [
                    "rule" => $rule,
                    "result" => $result["success"],
                ],
            );

            return $result;
        }

        return [
            "success" => false,
            "message" => "Firewall operation failed",
        ];
    }

    /**
     * Get firewall status
     */
    private function getFirewallStatus()
    {
        $status = $this->executeCommand("ufw status verbose");

        // Get rules from database
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM firewall_rules ORDER BY id DESC",
            );
            $stmt->execute();
            $rules = $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to get firewall rules: " . $e->getMessage(),
            );
            $rules = [];
        }

        return [
            "success" => true,
            "status" => $status["output"],
            "rules" => $rules,
        ];
    }

    /**
     * Create virtual host
     */
    public function createVirtualHost($config)
    {
        $domain = $config["domain"] ?? "";
        $email = $config["email"] ?? "";
        $type = $config["type"] ?? "custom";
        $docroot = $config["docroot"] ?? DEFAULT_DOCROOT . "/" . $domain;

        if (!$domain || !$email) {
            return [
                "success" => false,
                "message" => "Domain and email are required",
            ];
        }

        // Build vhsetup command
        $command = "/usr/local/lsws/vhsetup.sh -d {$domain} -le {$email} -f";

        if ($type === "wordpress") {
            $command .= " -w";
        }

        if ($docroot && $docroot !== DEFAULT_DOCROOT . "/" . $domain) {
            $command .= " --path {$docroot}";
        }

        $result = $this->executeCommand($command, true);

        if ($result["success"]) {
            // Save to database
            try {
                $stmt = $this->conn->prepare("
                    INSERT INTO virtual_hosts (domain, docroot, email, type, backend_host, backend_port, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $domain,
                    $docroot,
                    $email,
                    $type,
                    $config["backend_host"] ?? null,
                    $config["backend_port"] ?? null,
                    $_SESSION["user_id"] ?? 1,
                ]);

                $vhostId = $this->conn->lastInsertId();

                // If proxy type, configure proxy
                if ($type === "proxy" && !empty($config["backend_port"])) {
                    $this->configureProxy($domain, $config);
                }

                $this->logger->logActivity(
                    "vhost_create",
                    "virtual_host",
                    $vhostId,
                    [
                        "domain" => $domain,
                        "type" => $type,
                    ],
                );

                return [
                    "success" => true,
                    "message" => "Virtual host {$domain} created successfully",
                    "id" => $vhostId,
                ];
            } catch (PDOException $e) {
                $this->logger->logError(
                    "Failed to save virtual host: " . $e->getMessage(),
                );
                return [
                    "success" => false,
                    "message" =>
                        "Virtual host created but failed to save to database",
                ];
            }
        }

        return [
            "success" => false,
            "message" => "Failed to create virtual host",
            "output" => $result["output"],
        ];
    }

    /**
     * Configure reverse proxy
     */
    private function configureProxy($domain, $config)
    {
        $backend = $config["backend_host"] ?? "127.0.0.1";
        $port = $config["backend_port"];
        $uri = $config["uri"] ?? "/";
        $extName = "proxy_" . str_replace(".", "_", $domain) . "_{$port}";

        // Add external processor
        $extProcessor = "
extprocessor {$extName} {
  type proxy
  address {$backend}:{$port}
  maxConns 100
  initTimeout 60
  retryTimeout 0
  pcKeepAliveTimeout 60
  respBuffer 0
}";

        // Add to httpd_config.conf
        $this->executeCommand(
            "echo '" . addslashes($extProcessor) . "' >> " . OLS_HTTPD_CONF,
            true,
        );

        // Add context to vhost
        $vhconf = OLS_VHOSTS . "/{$domain}/vhconf.conf";
        $context = "
context {$uri} {
  type proxy
  location {$uri}
  handler {$extName}
  addDefaultCharset off
}";

        $this->executeCommand(
            "echo '" . addslashes($context) . "' >> {$vhconf}",
            true,
        );

        // Restart OLS
        $this->manageOLS("restart");

        return true;
    }

    /**
     * List virtual hosts
     */
    public function listVirtualHosts()
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM virtual_hosts ORDER BY created_at DESC",
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to list virtual hosts: " . $e->getMessage(),
            );
            return [];
        }
    }

    /**
     * Delete virtual host with optional database cleanup
     */
    public function deleteVirtualHost($domain, $deleteDatabase = false)
    {
        // Get vhost info before deletion
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM virtual_hosts WHERE domain = ?",
            );
            $stmt->execute([$domain]);
            $vhost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$vhost) {
                return [
                    "success" => false,
                    "message" => "Virtual host {$domain} not found",
                ];
            }
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to retrieve virtual host: " . $e->getMessage(),
            );
            return [
                "success" => false,
                "message" => "Failed to retrieve virtual host information",
            ];
        }

        // Delete associated database if requested
        $dbDeleted = false;
        if ($deleteDatabase && !empty($vhost["db_name"])) {
            $dbResult = $this->deleteVirtualHostDatabase(
                $vhost["db_name"],
                $vhost["db_user"] ?? null,
            );
            $dbDeleted = $dbResult["success"];
        }

        // Remove from OLS
        $vhostDir = OLS_VHOSTS . "/{$domain}";
        $result = $this->executeCommand("rm -rf {$vhostDir}", true);

        // Remove from httpd_config.conf mapping
        $this->executeCommand("sed -i '/{$domain}/d' " . OLS_HTTPD_CONF, true);

        // Remove from database
        try {
            $stmt = $this->conn->prepare(
                "DELETE FROM virtual_hosts WHERE domain = ?",
            );
            $stmt->execute([$domain]);
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to delete virtual host from DB: " . $e->getMessage(),
            );
        }

        // Restart OLS
        $this->manageOLS("restart");

        $this->logger->logActivity("vhost_delete", "virtual_host", null, [
            "domain" => $domain,
            "database_deleted" => $dbDeleted,
            "type" => $vhost["type"] ?? "unknown",
        ]);

        $message = "Virtual host {$domain} deleted successfully";
        if ($dbDeleted) {
            $message .= " with database and user";
        }

        return [
            "success" => true,
            "message" => $message,
            "database_deleted" => $dbDeleted,
        ];
    }

    /**
     * Delete database and database user for virtual host
     */
    private function deleteVirtualHostDatabase($dbName, $dbUser = null)
    {
        $dbConfig = require CONFIG_PATH . "/database.php";

        try {
            // Connect to MariaDB as root
            $conn = new mysqli(
                $dbConfig["db_host"] ?? "localhost",
                "root",
                $dbConfig["db_root_password"] ?? "",
                "",
                $dbConfig["db_port"] ?? 3306,
            );

            if ($conn->connect_error) {
                throw new Exception(
                    "Database connection failed: " . $conn->connect_error,
                );
            }

            // Drop database if exists
            $result = $conn->query("DROP DATABASE IF EXISTS `{$dbName}`");
            if (!$result) {
                throw new Exception("Failed to drop database: " . $conn->error);
            }

            // Drop database user if specified
            if ($dbUser) {
                $result = $conn->query(
                    "DROP USER IF EXISTS '{$dbUser}'@'localhost'",
                );
                if (!$result) {
                    $this->logger->logError(
                        "Failed to drop database user: " . $conn->error,
                    );
                }
                $conn->query("FLUSH PRIVILEGES");
            }

            $conn->close();

            $this->logger->logActivity("database_delete", "database", null, [
                "database" => $dbName,
                "user" => $dbUser,
            ]);

            return [
                "success" => true,
                "message" => "Database {$dbName} and user deleted successfully",
            ];
        } catch (Exception $e) {
            $this->logger->logError(
                "Failed to delete database: " . $e->getMessage(),
            );
            return [
                "success" => false,
                "message" => $e->getMessage(),
            ];
        }
    }

    /**
     * Create database backup
     */
    public function createBackup($type = "full", $entities = [])
    {
        $backupName = "backup_{$type}_" . date("Y-m-d_H-i-s");
        $backupPath = BACKUP_PATH . "/" . $backupName;

        if (!is_dir(BACKUP_PATH)) {
            mkdir(BACKUP_PATH, 0755, true);
        }

        switch ($type) {
            case "database":
                $backupFile = $backupPath . ".sql";
                $command = sprintf(
                    "mysqldump -h%s -P%s -u%s -p%s %s > %s 2>&1",
                    DB_HOST,
                    DB_PORT,
                    DB_USER,
                    DB_PASS,
                    DB_NAME,
                    $backupFile,
                );
                exec($command, $output, $returnCode);
                $success = $returnCode === 0 && file_exists($backupFile);
                break;

            case "files":
                $backupFile = $backupPath . ".tar.gz";
                $command = "tar -czf {$backupFile} " . implode(" ", $entities);
                exec($command, $output, $returnCode);
                $success = $returnCode === 0;
                break;

            case "full":
            default:
                $backupFile = $backupPath . ".tar.gz";
                $command = "tar -czf {$backupFile} /usr/local/lsws /var/www /etc 2>&1";
                exec($command, $output, $returnCode);
                $success = $returnCode === 0;
                break;
        }

        if ($success) {
            // Save to database
            try {
                $stmt = $this->conn->prepare("
                    INSERT INTO backups (name, type, path, size, created_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $backupName,
                    $type,
                    $backupFile,
                    filesize($backupFile),
                    $_SESSION["user_id"] ?? 1,
                ]);
            } catch (PDOException $e) {
                $this->logger->logError(
                    "Failed to save backup info: " . $e->getMessage(),
                );
            }

            $this->logger->logActivity("backup_create", "backup", null, [
                "type" => $type,
                "file" => $backupFile,
            ]);

            return [
                "success" => true,
                "message" => "Backup created successfully",
                "file" => $backupFile,
            ];
        }

        return [
            "success" => false,
            "message" => "Failed to create backup",
        ];
    }

    /**
     * Get backup list
     */
    public function listBackups()
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM backups ORDER BY created_at DESC",
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to list backups: " . $e->getMessage(),
            );
            return [];
        }
    }

    /**
     * Delete old backups
     */
    public function cleanupOldBackups()
    {
        $retentionDays = BACKUP_RETENTION_DAYS;

        try {
            $stmt = $this->conn->prepare("
                DELETE FROM backups
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$retentionDays]);

            $deletedCount = $stmt->rowCount();

            $this->logger->logActivity("backup_cleanup", "backup", null, [
                "deleted_count" => $deletedCount,
            ]);

            return [
                "success" => true,
                "deleted_count" => $deletedCount,
            ];
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to cleanup backups: " . $e->getMessage(),
            );
            return [
                "success" => false,
                "message" => "Failed to cleanup old backups",
            ];
        }
    }
}
