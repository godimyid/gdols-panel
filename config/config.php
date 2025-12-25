<?php
/**
 * GDOLS Panel - Main Configuration File
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Server Management Panel for OpenLiteSpeed with PHP 8.3, MariaDB, and Redis
 */

// Prevent direct access
if (!defined("GDOLS_PANEL_ACCESS")) {
    die("Direct access not permitted");
}

// Environment Configuration
define("GDOLS_PANEL_VERSION", "1.0.0");
define("GDOLS_PANEL_AUTHOR", "GoDiMyID");
define("GDOLS_PANEL_WEBSITE", "https://godi.my.id");
define("GDOLS_PANEL_REPO", "https://github.com/godimyid/gdols-panel");

// Paths
define("GDOLS_PANEL_ROOT", dirname(__DIR__));
define("GDOLS_PANEL_CONFIG", GDOLS_PANEL_ROOT . "/config");
define("GDOLS_PANEL_LOGS", GDOLS_PANEL_ROOT . "/logs");
define("GDOLS_PANEL_SCRIPTS", GDOLS_PANEL_ROOT . "/scripts");
define("GDOLS_PANEL_TEMPLATES", GDOLS_PANEL_ROOT . "/templates");

// Server Paths
define("OLS_ROOT", "/usr/local/lsws");
define("OLS_CONF", OLS_ROOT . "/conf");
define("OLS_BIN", OLS_ROOT . "/bin/lswsctrl");
define("OLS_VHOSTS", OLS_CONF . "/vhosts");
define("OLS_HTTPD_CONF", OLS_CONF . "/httpd_config.conf");

// PHP Configuration
define("PHP_VERSION", "83");
define("PHP_BIN", "/usr/local/lsws/lsphp" . PHP_VERSION . "/bin/php");
define("PHP_INI", "/usr/local/lsws/lsphp" . PHP_VERSION . "/etc/php.ini");

// Database Configuration
define("DB_HOST", "localhost");
define("DB_PORT", 3306);
define("DB_NAME", "gdolspanel");
define("DB_USER", "gdolspanel_user");
define("DB_PASS", ""); // Set during installation

// Redis Configuration
define("REDIS_HOST", "127.0.0.1");
define("REDIS_PORT", 6379);
define("REDIS_PASS", ""); // Set during installation

// Session Configuration
define("SESSION_NAME", "GDOLS_PANEL_SESSION");
define("SESSION_LIFETIME", 7200); // 2 hours
define("SESSION_PATH", GDOLS_PANEL_ROOT . "/sessions");

// Security
define("ENCRYPTION_KEY", ""); // Generate during installation
define("CSRF_TOKEN_NAME", "gd_panel_csrf");
define("MIN_PASSWORD_LENGTH", 8);

// API Configuration
define("API_RATE_LIMIT", 100); // requests per minute
define("API_TIMEOUT", 30); // seconds

// Panel Configuration
define("PANEL_TITLE", "GDOLS Panel");
define("PANEL_LANGUAGE", "id_ID");
define("PANEL_THEME", "dark");
define("PANEL_TIMEZONE", "Asia/Jakarta");

// Virtual Host Defaults
define("DEFAULT_DOCROOT", "/var/www");
define("DEFAULT_EMAIL", "admin@" . gethostname());

// Firewall Configuration
define("UFW_CONF", "/etc/ufw/ufw.conf");
define("UFW_APPS", "/etc/ufw/applications.d");

// Log Files
define("ACCESS_LOG", GDOLS_PANEL_LOGS . "/access.log");
define("ERROR_LOG", GDOLS_PANEL_LOGS . "/error.log");
define("SYSTEM_LOG", GDOLS_PANEL_LOGS . "/system.log");

// Backup Configuration
define("BACKUP_PATH", GDOLS_PANEL_ROOT . "/backups");
define("BACKUP_RETENTION_DAYS", 30);

// Feature Flags
define("FEATURE_PHP_EXTENSIONS", true);
define("FEATURE_VIRTUAL_HOSTS", true);
define("FEATURE_FIREWALL", true);
define("FEATURE_REDIS", true);
define("FEATURE_DATABASE", true);
define("FEATURE_MONITORING", true);
define("FEATURE_BACKUP", true);

// PHP Extensions Available
define("PHP_EXTENSIONS_AVAILABLE", [
    "imagick",
    "intl",
    "ioncube",
    "redis",
    "mysqli",
    "pdo",
    "pdo_mysql",
    "zip",
    "gd",
    "curl",
    "mbstring",
    "xml",
    "json",
    "opcache",
    "apcu",
    "memcached",
    "imap",
    "exif",
    "fileinfo",
    "soap",
    "xsl",
    "bz2",
    "zlib",
]);

// PHP Extensions Enabled by Default
define("PHP_EXTENSIONS_DEFAULT", [
    "mysqli",
    "pdo",
    "pdo_mysql",
    "zip",
    "gd",
    "curl",
    "mbstring",
    "json",
    "xml",
    "opcache",
]);

// System Requirements Check
define("MIN_RAM_GB", 2);
define("RECOMMENDED_RAM_GB", 4);
define("MIN_DISK_GB", 20);

// Error Reporting (Development: true, Production: false)
define("DEBUG_MODE", false);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set("display_errors", "1");
} else {
    error_reporting(E_ALL);
    ini_set("display_errors", "0");
    ini_set("log_errors", "1");
    ini_set("error_log", ERROR_LOG);
}

// Load local configuration if exists (overrides above settings)
if (file_exists(GDOLS_PANEL_CONFIG . "/config.local.php")) {
    require_once GDOLS_PANEL_CONFIG . "/config.local.php";
}
