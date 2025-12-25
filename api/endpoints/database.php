/**
 * GDOLS Panel - Database Management API Endpoint
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles MariaDB database and user management
 */

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleDatabaseList();
            break;

        case 'create':
            handleDatabaseCreate();
            break;

        case 'delete':
            handleDatabaseDelete();
            break;

        case 'users':
            handleDatabaseUsers();
            break;

        case 'create_user':
            handleCreateUser();
            break;

        case 'delete_user':
            handleDeleteUser();
            break;

        case 'backup':
            handleDatabaseBackup();
            break;

        case 'restore':
            handleDatabaseRestore();
            break;

        case 'import':
            handleDatabaseImport();
            break;

        case 'export':
            handleDatabaseExport();
            break;

        default:
            sendResponse('error', 'Invalid action');
    }
} catch (Exception $e) {
    logError('Database API Error: ' . $e->getMessage());
    sendResponse('error', $e->getMessage());
}

/**
 * Get list of all databases
 */
function handleDatabaseList() {
    requireAuth();

    $databases = [];
    $config = require CONFIG_PATH . '/database.php';

    try {
        // Connect to MariaDB
        $conn = new mysqli(
            $config['db_host'],
            'root',
            $config['db_root_password'] ?? '',
            '',
            $config['db_port']
        );

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Get list of databases (exclude system databases)
        $result = $conn->query("SHOW DATABASES");
        while ($row = $result->fetch_array()) {
            $db_name = $row[0];
            if (!in_array($db_name, ['information_schema', 'mysql', 'performance_schema', 'phpmyadmin'])) {
                // Get database size
                $size_result = $conn->query("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size
                    FROM information_schema.TABLES
                    WHERE table_schema = '$db_name'
                ");
                $size_row = $size_result->fetch_assoc();

                $databases[] = [
                    'name' => $db_name,
                    'size' => $size_row['size'] ?? 0,
                    'tables' => getTableCount($conn, $db_name)
                ];
            }
        }

        $conn->close();
        sendResponse('success', 'Databases retrieved successfully', $databases);

    } catch (Exception $e) {
        throw new Exception("Failed to list databases: " . $e->getMessage());
    }
}

/**
 * Get table count for a database
 */
function getTableCount($conn, $database) {
    $result = $conn->query("
        SELECT COUNT(*) as count
        FROM information_schema.TABLES
        WHERE table_schema = '$database'
    ");
    $row = $result->fetch_assoc();
    return $row['count'] ?? 0;
}

/**
 * Create new database
 */
function handleDatabaseCreate() {
    requireAuth();
    checkCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true);
    $db_name = $data['name'] ?? '';
    $charset = $data['charset'] ?? 'utf8mb4';
    $collation = $data['collation'] ?? 'utf8mb4_unicode_ci';

    // Validate database name
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
        throw new Exception('Invalid database name. Only alphanumeric characters and underscores allowed.');
    }

    $config = require CONFIG_PATH . '/database.php';

    try {
        $conn = new mysqli(
            $config['db_host'],
            'root',
            $config['db_root_password'] ?? '',
            '',
            $config['db_port']
        );

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Create database
        $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET $charset COLLATE $collation");

        if ($conn->error) {
            throw new Exception("Failed to create database: " . $conn->error);
        }

        $conn->close();

        // Log action
        logAction('database_create', "Created database: $db_name");

        sendResponse('success', "Database '$db_name' created successfully");

    } catch (Exception $e) {
        throw new Exception("Failed to create database: " . $e->getMessage());
    }
}

/**
 * Delete database
 */
function handleDatabaseDelete() {
    requireAuth();
    checkCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true);
    $db_name = $data['name'] ?? '';

    if (empty($db_name)) {
        throw new Exception('Database name is required');
    }

    // Protect system databases
    if (in_array($db_name, ['information_schema', 'mysql', 'performance_schema', 'phpmyadmin'])) {
        throw new Exception('Cannot delete system database');
    }

    $config = require CONFIG_PATH . '/database.php';

    try {
        $conn = new mysqli(
            $config['db_host'],
            'root',
            $config['db_root_password'] ?? '',
            '',
            $config['db_port']
        );

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Drop database
        $conn->query("DROP DATABASE IF EXISTS `$db_name`");

        if ($conn->error) {
            throw new Exception("Failed to delete database: " . $conn->error);
        }

        $conn->close();

        // Log action
        logAction('database_delete', "Deleted database: $db_name");

        sendResponse('success', "Database '$db_name' deleted successfully");

    } catch (Exception $e) {
        throw new Exception("Failed to delete database: " . $e->getMessage());
    }
}

/**
 * Get list of database users
 */
function handleDatabaseUsers() {
    requireAuth();

    $users = [];
    $config = require CONFIG_PATH . '/database.php';

    try {
        $conn = new mysqli(
            $config['db_host'],
            'root',
            $config['db_root_password'] ?? '',
            '',
            $config['db_port']
        );

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Get list of users
        $result = $conn->query("SELECT User, Host FROM mysql.user WHERE User NOT IN ('root', 'mariadb.sys', 'mysql')");
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'username' => $row['User'],
                'host' => $row['Host']
            ];
        }

        $conn->close();
        sendResponse('success', 'Database users retrieved successfully', $users);

    } catch (Exception $e) {
        throw new Exception("Failed to list users: " . $e->getMessage());
    }
}

/**
 * Create database user
 */
function handleCreateUser() {
    requireAuth();
    checkCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $host = $data['host'] ?? 'localhost';
    $database = $data['database'] ?? '';

    // Validate username
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        throw new Exception('Invalid username. Only alphanumeric characters and underscores allowed.');
    }

    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }

    $config = require CONFIG_PATH . '/database.php';

    try {
        $conn = new mysqli(
            $config['db_host'],
            'root',
            $config['db_root_password'] ?? '',
            '',
            $config['db_port']
        );

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Create user
        $conn->query("CREATE USER IF NOT EXISTS '$username'@'$host' IDENTIFIED BY '$password'");

        if ($conn->error) {
            throw new Exception("Failed to create user: " . $conn->error);
        }

        // Grant privileges if database specified
        if (!empty($database)) {
            $conn->query("GRANT ALL PRIVILEGES ON `$database`.* TO '$username'@'$host'");
            $conn->query("FLUSH PRIVILEGES");

            if ($conn->error) {
                throw new Exception("Failed to grant privileges: " . $conn->error);
            }
        }

        $conn->close();

        // Log action
        logAction('db_user_create', "Created database user: $username@$host");

        sendResponse('success', "User '$username' created successfully");

    } catch (Exception $e) {
        throw new Exception("Failed to create user: " . $e->getMessage());
    }
}

/**
 * Delete database user
 */
function handleDeleteUser() {
    requireAuth();
    checkCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $host = $data['host'] ?? 'localhost';

    if (empty($username)) {
        throw new Exception('Username is required');
    }

    // Protect system users
    if (in_array($username, ['root', 'mariadb.sys', 'mysql'])) {
        throw new Exception('Cannot delete system user');
    }

    $config = require CONFIG_PATH . '/database.php';

    try {
        $conn = new mysqli(
            $config['db_host'],
            'root',
            $config['db_root_password'] ?? '',
            '',
            $config['db_port']
        );

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Drop user
        $conn->query("DROP USER IF EXISTS '$username'@'$host'");

        if ($conn->error) {
            throw new Exception("Failed to delete user: " . $conn->error);
        }

        $conn->close();

        // Log action
        logAction('db_user_delete', "Deleted database user: $username@$host");

        sendResponse('success', "User '$username' deleted successfully");

    } catch (Exception $e) {
        throw new Exception("Failed to delete user: " . $e->getMessage());
    }
}

/**
 * Backup database
 */
function handleDatabaseBackup() {
    requireAuth();
    checkCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true);
    $database = $data['database'] ?? '';

    if (empty($database)) {
        throw new Exception('Database name is required');
    }

    $config = require CONFIG_PATH . '/database.php';

    try {
        // Create backup directory if not exists
        $backup_dir = BACKUP_PATH . '/database';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        // Generate backup filename
        $filename = $database . '_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_path = $backup_dir . '/' . $filename;

        // Use mysqldump to create backup
        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s %s > %s 2>&1',
            $config['db_host'],
            $config['db_port'],
            'root',
            escapeshellarg($config['db_root_password'] ?? ''),
            $database,
            $backup_path
        );

        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new Exception('Failed to create database backup');
        }

        // Log action
        logAction('database_backup', "Created backup for database: $database");

        sendResponse('success', "Database backup created successfully", [
            'filename' => $filename,
            'path' => $backup_path
        ]);

    } catch (Exception $e) {
        throw new Exception("Failed to backup database: " . $e->getMessage());
    }
}

/**
 * Restore database from backup
 */
function handleDatabaseRestore() {
    requireAuth();
    checkCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true);
    $database = $data['database'] ?? '';
    $backup_file = $data['backup_file'] ?? '';

    if (empty($database) || empty($backup_file)) {
        throw new Exception('Database name and backup file are required');
    }

    $config = require CONFIG_PATH . '/database.php';

    try {
        $backup_path = BACKUP_PATH . '/database/' . $backup_file;

        if (!file_exists($backup_path)) {
            throw new Exception('Backup file not found');
        }

        // Restore database
        $command = sprintf(
            'mysql -h%s -P%s -u%s -p%s %s < %s 2>&1',
            $config['db_host'],
            $config['db_port'],
            'root',
            escapeshellarg($config['db_root_password'] ?? ''),
            $database,
            $backup_path
        );

        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new Exception('Failed to restore database');
        }

        // Log action
        logAction('database_restore', "Restored database: $database from $backup_file");

        sendResponse('success', "Database restored successfully");

    } catch (Exception $e) {
        throw new Exception("Failed to restore database: " . $e->getMessage());
    }
}

/**
 * Import SQL file into database
 */
function handleDatabaseImport() {
    requireAuth();
    checkCsrfToken();

    if (!isset($_FILES['sql_file'])) {
        throw new Exception('No file uploaded');
    }

    $database = $_POST['database'] ?? '';
    if (empty($database)) {
        throw new Exception('Database name is required');
    }

    $config = require CONFIG_PATH . '/database.php';

    try {
        $file = $_FILES['sql_file'];
        $tmp_path = $file['tmp_name'];

        // Validate file type
        if ($file['type'] !== 'application/sql' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'sql') {
            throw new Exception('Invalid file type. Only SQL files are allowed.');
        }

        // Import database
        $command = sprintf(
            'mysql -h%s -P%s -u%s -p%s %s < %s 2>&1',
            $config['db_host'],
            $config['db_port'],
            'root',
            escapeshellarg($config['db_root_password'] ?? ''),
            $database,
            $tmp_path
        );

        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new Exception('Failed to import database');
        }

        // Clean up temp file
        unlink($tmp_path);

        // Log action
        logAction('database_import', "Imported SQL file into database: $database");

        sendResponse('success', "Database imported successfully");

    } catch (Exception $e) {
        throw new Exception("Failed to import database: " . $e->getMessage());
    }
}

/**
 * Export database to SQL file
 */
function handleDatabaseExport() {
    requireAuth();
    checkCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true);
    $database = $data['database'] ?? '';

    if (empty($database)) {
        throw new Exception('Database name is required');
    }

    $config = require CONFIG_PATH . '/database.php';

    try {
        // Create export directory if not exists
        $export_dir = BACKUP_PATH . '/exports';
        if (!is_dir($export_dir)) {
            mkdir($export_dir, 0755, true);
        }

        // Generate export filename
        $filename = $database . '_export_' . date('Y-m-d_H-i-s') . '.sql';
        $export_path = $export_dir . '/' . $filename;

        // Export database
        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s %s > %s 2>&1',
            $config['db_host'],
            $config['db_port'],
            'root',
            escapeshellarg($config['db_root_password'] ?? ''),
            $database,
            $export_path
        );

        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new Exception('Failed to export database');
        }

        // Log action
        logAction('database_export', "Exported database: $database");

        sendResponse('success', "Database exported successfully", [
            'filename' => $filename,
            'download_url' => '/api/backup/download?file=' . basename($export_path)
        ]);

    } catch (Exception $e) {
        throw new Exception("Failed to export database: " . $e->getMessage());
    }
}
