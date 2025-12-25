<?php
/**
 * GDOLS Panel - PHP Extensions API Endpoint
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles PHP extensions management
 */

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
        case 'extensions':
            handleListExtensions();
            break;

        case 'install':
            handleInstallExtension();
            break;

        case 'toggle':
            handleToggleExtension();
            break;

        case 'update_extensions':
            handleUpdateExtensions();
            break;

        case 'get_config':
            handleGetConfig();
            break;

        case 'save_config':
            handleSaveConfig();
            break;

        case 'reload_php':
            handleReloadPHP();
            break;

        default:
            errorResponse('Invalid action', 400);
    }
} catch (Exception $e) {
    errorResponse('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Handle list PHP extensions
 */
function handleListExtensions() {
    global $conn, $system;

    requireAdmin();

    try {
        $extensions = $system->getPHPExtensions();

        // Get PHP info
        $phpVersion = PHP_VERSION;
        $phpIniPath = PHP_INI;

        // Get currently loaded extensions from PHP
        $loadedExtensions = get_loaded_extensions();
        $loadedExtensions = array_map('strtolower', $loadedExtensions);

        // Update installation status
        foreach ($extensions as &$ext) {
            $ext['is_loaded'] = in_array(strtolower($ext['name']), $loadedExtensions);
            $ext['can_install'] = $ext['is_loaded'] || in_array($ext['name'], [
                'imagick', 'intl', 'ioncube', 'redis', 'memcached', 'xsl'
            ]);
        }

        successResponse([
            'extensions' => $extensions,
            'php_version' => $phpVersion,
            'php_ini_path' => $phpIniPath,
            'total' => count($extensions),
            'installed' => count(array_filter($extensions, fn($e) => $e['installed'])),
            'enabled' => count(array_filter($extensions, fn($e) => $e['enabled']))
        ], 'PHP extensions retrieved successfully');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve PHP extensions: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle install PHP extension
 */
function handleInstallExtension() {
    global $system, $logger;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $extension = $input['extension'] ?? '';

    if (empty($extension)) {
        errorResponse('Extension name is required');
    }

    // Validate extension name
    $validExtensions = PHP_EXTENSIONS_AVAILABLE;
    if (!in_array($extension, $validExtensions)) {
        errorResponse('Invalid extension name');
    }

    try {
        $result = $system->installPHPExtension($extension);

        if ($result['success']) {
            $logger->logActivity('extension_install', 'php_extension', null, [
                'extension' => $extension,
                'result' => 'success'
            ]);

            successResponse([
                'extension' => $extension,
                'output' => $result['output'] ?? ''
            ], 'Extension installed successfully');
        } else {
            errorResponse($result['message'] ?? 'Failed to install extension', 500);
        }

    } catch (Exception $e) {
        $logger->logError("Failed to install extension {$extension}: " . $e->getMessage());
        errorResponse('Failed to install extension: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle toggle extension enable/disable
 */
function handleToggleExtension() {
    global $system, $logger;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $extension = $input['extension'] ?? '';
    $enabled = $input['enabled'] ?? false;

    if (empty($extension)) {
        errorResponse('Extension name is required');
    }

    try {
        $result = $system->togglePHPExtension($extension, $enabled);

        if ($result['success']) {
            $logger->logActivity('extension_toggle', 'php_extension', null, [
                'extension' => $extension,
                'enabled' => $enabled
            ]);

            successResponse([
                'extension' => $extension,
                'enabled' => $enabled
            ], $enabled ? 'Extension enabled' : 'Extension disabled');
        } else {
            errorResponse($result['message'] ?? 'Failed to toggle extension', 500);
        }

    } catch (Exception $e) {
        $logger->logError("Failed to toggle extension {$extension}: " . $e->getMessage());
        errorResponse('Failed to toggle extension: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle update multiple extensions
 */
function handleUpdateExtensions() {
    global $conn, $logger;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $extensions = $input['extensions'] ?? [];

    if (!is_array($extensions)) {
        errorResponse('Extensions must be an array');
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // Get all extensions
        $stmt = $conn->prepare("SELECT name FROM php_extensions");
        $stmt->execute();
        $allExtensions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Disable all first
        $stmt = $conn->prepare("UPDATE php_extensions SET enabled = 0");
        $stmt->execute();

        // Enable selected extensions
        if (!empty($extensions)) {
            $placeholders = str_repeat('?,', count($extensions) - 1) . '?';
            $stmt = $conn->prepare("UPDATE php_extensions SET enabled = 1 WHERE name IN ({$placeholders})");
            $stmt->execute($extensions);
        }

        $conn->commit();

        $logger->logActivity('extensions_bulk_update', 'php_extension', null, [
            'enabled_count' => count($extensions),
            'extensions' => $extensions
        ]);

        // Update PHP ini file
        updatePHPIniExtensions($extensions);

        // Reload PHP
        $reloadResult = reloadOLS();

        successResponse([
            'enabled_extensions' => $extensions,
            'total' => count($extensions),
            'reloaded' => $reloadResult['success']
        ], 'PHP extensions updated successfully');

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $logger->logError("Failed to update extensions: " . $e->getMessage());
        errorResponse('Failed to update extensions: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle get PHP configuration
 */
function handleGetConfig() {
    requireAdmin();

    $phpIniPath = PHP_INI;

    if (!file_exists($phpIniPath)) {
        errorResponse('PHP configuration file not found', 404);
    }

    $config = file_get_contents($phpIniPath);

    // Parse important settings
    $settings = [
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_input_vars' => ini_get('max_input_vars'),
        'date.timezone' => ini_get('date.timezone'),
        'opcache.enable' => ini_get('opcache.enable'),
        'opcache.memory_consumption' => ini_get('opcache.memory_consumption'),
    ];

    successResponse([
        'config' => $config,
        'settings' => $settings,
        'ini_path' => $phpIniPath
    ], 'PHP configuration retrieved');
}

/**
 * Handle save PHP configuration
 */
function handleSaveConfig() {
    global $logger;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $config = $input['config'] ?? '';

    if (empty($config)) {
        errorResponse('Configuration is required');
    }

    $phpIniPath = PHP_INI;

    try {
        // Backup current config
        $backupPath = $phpIniPath . '.bak.' . time();
        if (!copy($phpIniPath, $backupPath)) {
            errorResponse('Failed to backup current configuration');
        }

        // Write new config
        if (file_put_contents($phpIniPath, $config) === false) {
            errorResponse('Failed to write configuration');
        }

        $logger->logActivity('php_config_save', 'php', null, [
            'ini_path' => $phpIniPath,
            'backup_path' => $backupPath
        ]);

        // Reload OLS
        $reloadResult = reloadOLS();

        successResponse([
            'saved' => true,
            'backup' => $backupPath,
            'reloaded' => $reloadResult['success']
        ], 'PHP configuration saved successfully');

    } catch (Exception $e) {
        $logger->logError("Failed to save PHP config: " . $e->getMessage());
        errorResponse('Failed to save configuration: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle reload PHP
 */
function handleReloadPHP() {
    global $logger;

    requireAdmin();

    try {
        $result = reloadOLS();

        if ($result['success']) {
            $logger->logActivity('php_reload', 'php', null);

            successResponse([
                'reloaded' => true
            ], 'PHP reloaded successfully');
        } else {
            errorResponse($result['message'] ?? 'Failed to reload PHP', 500);
        }

    } catch (Exception $e) {
        $logger->logError("Failed to reload PHP: " . $e->getMessage());
        errorResponse('Failed to reload PHP: ' . $e->getMessage(), 500);
    }
}

/**
 * Helper: Update PHP ini extensions
 */
function updatePHPIniExtensions($extensions) {
    $phpIniPath = PHP_INI;
    $config = file_get_contents($phpIniPath);

    // Remove existing extension lines
    $config = preg_replace('/^extension=.+$/m', '', $config);
    $config = preg_replace('/^zend_extension=.+$/m', '', $config);

    // Add new extension lines
    $extensionLines = [];
    foreach ($extensions as $ext) {
        if ($ext === 'ioncube') {
            $extensionLines[] = 'zend_extension = /usr/local/lsws/lsphp' . PHP_VERSION . '/lib/php/20230831/ioncube_loader_lin_8.3.so';
        } else {
            $extensionLines[] = "extension = {$ext}.so";
        }
    }

    // Find position to insert (after [PHP] section or at the end)
    if (preg_match('/^\[PHP\]$/m', $config)) {
        $config = preg_replace(
            '/(\[PHP\]$)/',
            "$1\n" . implode("\n", $extensionLines) . "\n",
            $config,
            1
        );
    } else {
        $config .= "\n" . implode("\n", $extensionLines) . "\n";
    }

    // Backup and save
    $backupPath = $phpIniPath . '.bak.' . time();
    copy($phpIniPath, $backupPath);
    file_put_contents($phpIniPath, $config);

    return $backupPath;
}

/**
 * Helper: Reload OpenLiteSpeed
 */
function reloadOLS() {
    global $system;

    return $system->manageOLS('reload');
}
