<?php
/**
 * GDOLS Panel - Database Initialization File
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Database schema and initialization for GDOLS Panel
 */

// Prevent direct access
if (!defined("GDOLS_PANEL_ACCESS")) {
    die("Direct access not permitted");
}

class Database
{
    private static $instance = null;
    private $connection;

    public function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        try {
            $dsn =
                "mysql:host=" .
                DB_HOST .
                ";port=" .
                DB_PORT .
                ";charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check configuration.");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function initialize()
    {
        try {
            $this->createDatabase();
            $this->createTables();
            $this->insertDefaultData();
            return true;
        } catch (Exception $e) {
            error_log("Database initialization failed: " . $e->getMessage());
            return false;
        }
    }

    private function createDatabase()
    {
        $conn = $this->connection;

        // Create database if not exists
        $conn->exec(
            "CREATE DATABASE IF NOT EXISTS `" .
                DB_NAME .
                "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        );
        $conn->exec("USE `" . DB_NAME . "`");
    }

    private function createTables()
    {
        $conn = $this->connection;

        // Users table
        $sql = "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `role` ENUM('admin', 'user') DEFAULT 'admin',
            `status` ENUM('active', 'suspended', 'locked') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `last_login` TIMESTAMP NULL,
            `login_attempts` INT(11) DEFAULT 0,
            `locked_until` TIMESTAMP NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_username` (`username`),
            INDEX `idx_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->exec($sql);

        // Virtual hosts table
        $sql = "CREATE TABLE IF NOT EXISTS `virtual_hosts` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `domain` VARCHAR(255) NOT NULL UNIQUE,
            `docroot` VARCHAR(500) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `type` ENUM('wordpress', 'custom', 'proxy') DEFAULT 'custom',
            `backend_host` VARCHAR(255) NULL,
            `backend_port` INT(11) NULL,
            `php_version` VARCHAR(10) DEFAULT '83',
            `ssl_enabled` TINYINT(1) DEFAULT 0,
            `ssl_cert` TEXT NULL,
            `ssl_key` TEXT NULL,
            `status` ENUM('active', 'suspended', 'pending') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT(11) NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_domain` (`domain`),
            INDEX `idx_status` (`status`),
            INDEX `idx_type` (`type`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->exec($sql);

        // PHP extensions table
        $sql = "CREATE TABLE IF NOT EXISTS `php_extensions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(50) NOT NULL UNIQUE,
            `display_name` VARCHAR(100) NOT NULL,
            `description` TEXT NULL,
            `enabled` TINYINT(1) DEFAULT 0,
            `installed` TINYINT(1) DEFAULT 0,
            `category` VARCHAR(50) DEFAULT 'general',
            `priority` INT(11) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_enabled` (`enabled`),
            INDEX `idx_category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->exec($sql);

        // Firewall rules table
        $sql = "CREATE TABLE IF NOT EXISTS `firewall_rules` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `rule_id` VARCHAR(50) NOT NULL UNIQUE,
            `action` ENUM('allow', 'deny', 'limit') NOT NULL,
            `protocol` ENUM('tcp', 'udp', 'both') DEFAULT 'tcp',
            `port` VARCHAR(100) NOT NULL,
            `source` VARCHAR(100) DEFAULT 'any',
            `description` TEXT NULL,
            `enabled` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT(11) NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_enabled` (`enabled`),
            INDEX `idx_action` (`action`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->exec($sql);

        // System logs table
        $sql = "CREATE TABLE IF NOT EXISTS `system_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NULL,
            `action` VARCHAR(100) NOT NULL,
            `entity` VARCHAR(50) NULL,
            `entity_id` INT(11) NULL,
            `details` TEXT NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `status` ENUM('success', 'failed', 'warning') DEFAULT 'success',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_action` (`action`),
            INDEX `idx_status` (`status`),
            INDEX `idx_created_at` (`created_at`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->exec($sql);

        // System settings table
        $sql = "CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT NOT NULL,
            `setting_type` ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
            `category` VARCHAR(50) DEFAULT 'general',
            `description` TEXT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->exec($sql);

        // Backups table
        $sql = "CREATE TABLE IF NOT EXISTS `backups` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `type` ENUM('full', 'database', 'files', 'config') NOT NULL,
            `path` VARCHAR(500) NOT NULL,
            `size` BIGINT(20) DEFAULT 0,
            `status` ENUM('completed', 'failed', 'in_progress', 'scheduled') DEFAULT 'completed',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `expires_at` TIMESTAMP NULL,
            `created_by` INT(11) NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_type` (`type`),
            INDEX `idx_status` (`status`),
            INDEX `idx_created_at` (`created_at`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->exec($sql);

        // Databases table
        $sql = "CREATE TABLE IF NOT EXISTS `databases` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL UNIQUE,
            `username` VARCHAR(50) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `host` VARCHAR(100) DEFAULT 'localhost',
            `charset` VARCHAR(20) DEFAULT 'utf8mb4',
            `collation` VARCHAR(50) DEFAULT 'utf8mb4_unicode_ci',
            `status` ENUM('active', 'suspended') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `created_by` INT(11) NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_name` (`name`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->exec($sql);

        // Redis config table
        $sql = "CREATE TABLE IF NOT EXISTS `redis_config` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `maxmemory` VARCHAR(20) DEFAULT '2g',
            `maxmemory_policy` VARCHAR(50) DEFAULT 'allkeys-lru',
            `timeout` INT(11) DEFAULT 300,
            `tcp_keepalive` INT(11) DEFAULT 60,
            `password_enabled` TINYINT(1) DEFAULT 1,
            `password` VARCHAR(255) NULL,
            `protected_mode` TINYINT(1) DEFAULT 1,
            `status` ENUM('running', 'stopped', 'error') DEFAULT 'running',
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->exec($sql);
    }

    private function insertDefaultData()
    {
        $conn = $this->connection;

        // Insert default admin user (password: admin123 - change immediately!)
        $defaultPassword = password_hash("admin123", PASSWORD_DEFAULT);
        $sql =
            "INSERT IGNORE INTO `users` (`username`, `password`, `email`, `role`)
                VALUES ('admin', ?, 'admin@" .
            gethostname() .
            "', 'admin')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$defaultPassword]);

        // Insert PHP extensions
        $extensions = PHP_EXTENSIONS_AVAILABLE;
        $extensionInfo = [
            "imagick" => ["Image Processing", "Image manipulation library"],
            "intl" => ["Internationalization", "Unicode and国际化 support"],
            "ioncube" => ["IonCube Loader", "PHP encoder/decoder"],
            "redis" => ["Redis", "Redis caching client"],
            "mysqli" => ["MySQLi", "MySQL improved extension"],
            "pdo" => ["PDO", "PHP Data Objects"],
            "pdo_mysql" => ["PDO MySQL", "MySQL driver for PDO"],
            "zip" => ["Zip", "Zip file compression"],
            "gd" => ["GD", "Image manipulation"],
            "curl" => ["cURL", "URL transfer library"],
            "mbstring" => ["Multibyte String", "Multibyte character support"],
            "xml" => ["XML", "XML parsing"],
            "json" => ["JSON", "JavaScript Object Notation"],
            "opcache" => ["OPcache", "PHP opcode caching"],
            "apcu" => ["APCu", "User cache for APC"],
            "memcached" => ["Memcached", "Memcached client"],
            "imap" => ["IMAP", "Email handling"],
            "exif" => ["EXIF", "Image metadata"],
            "fileinfo" => ["Fileinfo", "File type detection"],
            "soap" => ["SOAP", "SOAP protocol"],
            "xsl" => ["XSL", "XSLT transformations"],
            "bz2" => ["Bzip2", "Bzip2 compression"],
            "zlib" => ["Zlib", "Gzip compression"],
        ];

        foreach ($extensions as $index => $ext) {
            $info = isset($extensionInfo[$ext])
                ? $extensionInfo[$ext]
                : [$ext, ucfirst($ext) . " extension"];
            $enabled = in_array($ext, PHP_EXTENSIONS_DEFAULT) ? 1 : 0;
            $installed = in_array($ext, PHP_EXTENSIONS_DEFAULT) ? 1 : 0;

            $sql = "INSERT IGNORE INTO `php_extensions`
                    (`name`, `display_name`, `description`, `enabled`, `installed`, `priority`)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $ext,
                $info[0],
                $info[1],
                $enabled,
                $installed,
                $index,
            ]);
        }

        // Insert default system settings
        $settings = [
            ["panel_title", PANEL_TITLE, "text", "general", "Panel title"],
            [
                "panel_language",
                PANEL_LANGUAGE,
                "text",
                "general",
                "Panel language",
            ],
            ["panel_theme", PANEL_THEME, "text", "general", "Panel theme"],
            ["timezone", PANEL_TIMEZONE, "text", "general", "Server timezone"],
            [
                "backup_retention",
                BACKUP_RETENTION_DAYS,
                "number",
                "backup",
                "Backup retention in days",
            ],
            [
                "session_lifetime",
                SESSION_LIFETIME,
                "number",
                "security",
                "Session lifetime in seconds",
            ],
            [
                "max_login_attempts",
                5,
                "number",
                "security",
                "Maximum login attempts",
            ],
            [
                "lockout_duration",
                900,
                "number",
                "security",
                "Lockout duration in seconds",
            ],
            [
                "enable_monitoring",
                1,
                "boolean",
                "monitoring",
                "Enable system monitoring",
            ],
            [
                "monitoring_interval",
                60,
                "number",
                "monitoring",
                "Monitoring interval in seconds",
            ],
            [
                "log_retention_days",
                30,
                "number",
                "logging",
                "Log retention in days",
            ],
        ];

        foreach ($settings as $setting) {
            $sql = "INSERT IGNORE INTO `system_settings`
                    (`setting_key`, `setting_value`, `setting_type`, `category`, `description`)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($setting);
        }

        // Insert default firewall rules
        $rules = [
            ["SSH", "allow", "tcp", "22", "any", "SSH access"],
            ["HTTP", "allow", "tcp", "80", "any", "HTTP web server"],
            ["HTTPS", "allow", "tcp", "443", "any", "HTTPS web server"],
            [
                "OLS-Admin",
                "allow",
                "tcp",
                "7080",
                "any",
                "OpenLiteSpeed WebAdmin",
            ],
        ];

        foreach ($rules as $rule) {
            $ruleId = "RULE_" . strtoupper($rule[0]) . "_" . time();
            $sql = "INSERT IGNORE INTO `firewall_rules`
                    (`rule_id`, `action`, `protocol`, `port`, `source`, `description`, `created_by`)
                    VALUES (?, ?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $ruleId,
                $rule[1],
                $rule[2],
                $rule[3],
                $rule[4],
                $rule[5],
            ]);
        }

        // Insert default Redis config
        $sql = "INSERT IGNORE INTO `redis_config`
                (`maxmemory`, `maxmemory_policy`, `timeout`, `tcp_keepalive`)
                VALUES ('2g', 'allkeys-lru', 300, 60)";
        $conn->exec($sql);
    }

    public function migrate()
    {
        // For future migrations
        return true;
    }

    public function backup()
    {
        // Create database backup
        $backupFile = BACKUP_PATH . "/database_" . date("Y-m-d_H-i-s") . ".sql";

        if (!is_dir(BACKUP_PATH)) {
            mkdir(BACKUP_PATH, 0755, true);
        }

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

        if ($returnCode === 0 && file_exists($backupFile)) {
            return $backupFile;
        }

        return false;
    }
}
