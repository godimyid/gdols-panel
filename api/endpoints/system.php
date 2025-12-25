<?php
/**
 * GDOLS Panel - System API Endpoint
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles system monitoring and information
 */

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'dashboard':
            handleDashboard();
            break;

        case 'info':
            handleInfo();
            break;

        case 'resources':
            handleResources();
            break;

        case 'services':
            handleServices();
            break;

        case 'service_status':
            handleServiceStatus();
            break;

        case 'processes':
            handleProcesses();
            break;

        case 'logs':
            handleLogs();
            break;

        case 'disk_usage':
            handleDiskUsage();
            break;

        case 'network_stats':
            handleNetworkStats();
            break;

        default:
            errorResponse('Invalid action', 400);
    }
} catch (Exception $e) {
    errorResponse('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Handle dashboard data
 */
function handleDashboard() {
    global $conn, $system;

    requireAdmin();

    try {
        // Get system resources
        $resources = $system->getResourceUsage();

        // Get virtual hosts stats
        $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM virtual_hosts");
        $stmt->execute();
        $vhostsStats = $stmt->fetch();

        // Get recent activity
        $stmt = $conn->prepare("
            SELECT * FROM system_logs
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $recentActivity = $stmt->fetchAll();

        // Format activity for display
        $formattedActivity = [];
        foreach ($recentActivity as $activity) {
            $formattedActivity[] = [
                'icon' => getActivityIcon($activity['action']),
                'title' => getActivityTitle($activity['action'], $activity),
                'time' => timeElapsed($activity['created_at'])
            ];
        }

        // Get service status
        $services = getServicesStatus();

        // Calculate changes (compare with previous if stored)
        $cpuChange = calculateCPUChange($resources['cpu']['usage_percent']);
        $memoryChange = calculateMemoryChange($resources['memory']['usage_percent']);

        successResponse([
            'stats' => [
                'cpu' => [
                    'usage_percent' => $resources['cpu']['usage_percent'],
                    'change' => $cpuChange,
                    'cores' => $resources['cpu']['cores'],
                    'load_average' => $resources['cpu']['load_average']
                ],
                'memory' => [
                    'usage_percent' => $resources['memory']['usage_percent'],
                    'change' => $memoryChange,
                    'total' => formatBytes($resources['memory']['total']),
                    'used' => formatBytes($resources['memory']['used']),
                    'available' => formatBytes($resources['memory']['available'])
                ],
                'disk' => [
                    'usage_percent' => $resources['disk']['usage_percent'],
                    'used' => $resources['disk']['used'],
                    'total' => $resources['disk']['total'],
                    'available' => $resources['disk']['available'],
                    'mount' => $resources['disk']['mount']
                ],
                'vhosts' => [
                    'total' => (int)$vhostsStats['total'],
                    'active' => (int)$vhostsStats['active']
                ]
            ],
            'recent_activity' => $formattedActivity,
            'services' => $services
        ], 'Dashboard data retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve dashboard data: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle system information
 */
function handleInfo() {
    global $system;

    requireAdmin();

    try {
        $info = $system->getSystemInfo();

        // Add more details
        $info['php_ini'] = php_ini_loaded_file();
        $info['php_extensions'] = get_loaded_extensions();
        $info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $info['document_root'] = $_SERVER['DOCUMENT_ROOT'] ?? '';

        // Get disk info for all mounts
        $info['disk_mounts'] = getAllDiskMounts();

        // Get network interfaces
        $info['network_interfaces'] = getNetworkInterfaces();

        successResponse($info, 'System information retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve system information: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle resource usage
 */
function handleResources() {
    global $system;

    requireAdmin();

    try {
        $resources = $system->getResourceUsage();

        successResponse($resources, 'Resource usage retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve resource usage: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle services status
 */
function handleServices() {
    requireAdmin();

    try {
        $services = getServicesStatus();

        successResponse([
            'services' => $services,
            'total' => count($services),
            'running' => count(array_filter($services, fn($s) => $s['status'] === 'running'))
        ], 'Services status retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve services status: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle single service status
 */
function handleServiceStatus() {
    requireAdmin();

    $service = $_GET['service'] ?? '';

    if (empty($service)) {
        errorResponse('Service name is required');
    }

    $validServices = ['ols', 'openlitespeed', 'mariadb', 'mysql', 'redis', 'php', 'lsphp'];
    if (!in_array(strtolower($service), $validServices)) {
        errorResponse('Invalid service name');
    }

    try {
        $status = getServiceStatus($service);

        successResponse($status, 'Service status retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve service status: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle running processes
 */
function handleProcesses() {
    requireAdmin();

    try {
        // Get top processes by CPU
        $cpuOutput = shell_exec("ps aux --sort=-%cpu | head -20");
        $cpuProcesses = parsePsOutput($cpuOutput);

        // Get top processes by Memory
        $memOutput = shell_exec("ps aux --sort=-%mem | head -20");
        $memProcesses = parsePsOutput($memOutput);

        // Get process count
        $processCount = (int)shell_exec("ps aux | wc -l");

        successResponse([
            'by_cpu' => $cpuProcesses,
            'by_memory' => $memProcesses,
            'total' => $processCount
        ], 'Processes retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve processes: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle system logs
 */
function handleLogs() {
    global $logger;

    requireAdmin();

    try {
        $limit = min((int)($_GET['limit'] ?? 100), 1000);
        $offset = (int)($_GET['offset'] ?? 0);
        $filters = [
            'action' => $_GET['action_filter'] ?? null,
            'status' => $_GET['status'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null
        ];

        // Remove null values
        $filters = array_filter($filters, fn($v) => $v !== null);

        $logs = $logger->getLogs($filters, $limit, $offset);

        // Format logs
        $formattedLogs = [];
        foreach ($logs as $log) {
            $formattedLogs[] = [
                'id' => $log['id'],
                'action' => $log['action'],
                'entity' => $log['entity'],
                'details' => json_decode($log['details'], true),
                'ip_address' => $log['ip_address'],
                'status' => $log['status'],
                'created_at' => $log['created_at'],
                'time_ago' => timeElapsed($log['created_at'])
            ];
        }

        successResponse([
            'logs' => $formattedLogs,
            'total' => count($formattedLogs)
        ], 'Logs retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve logs: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle disk usage for all mounts
 */
function handleDiskUsage() {
    requireAdmin();

    try {
        $mounts = getAllDiskMounts();

        successResponse([
            'mounts' => $mounts,
            'total' => count($mounts)
        ], 'Disk usage retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve disk usage: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle network statistics
 */
function handleNetworkStats() {
    requireAdmin();

    try {
        $interfaces = getNetworkInterfaces();

        // Get connection stats
        $connections = shell_exec("ss -s | grep 'TCP:' | awk '{print $2, $4, $6}'");

        // Get network traffic stats (if vnstat available)
        $traffic = [];
        if (file_exists('/usr/bin/vnstat')) {
            $output = shell_exec("vnstat --json 2>/dev/null");
            if ($output) {
                $data = json_decode($output, true);
                if ($data) {
                    $traffic = $data;
                }
            }
        }

        successResponse([
            'interfaces' => $interfaces,
            'connections' => $connections,
            'traffic' => $traffic
        ], 'Network statistics retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to retrieve network stats: ' . $e->getMessage(), 500);
    }
}

/**
 * Helper: Get services status
 */
function getServicesStatus() {
    $services = [];

    // OpenLiteSpeed
    $olsStatus = shell_exec("/usr/local/lsws/bin/lswsctrl status 2>&1");
    $services['OpenLiteSpeed'] = [
        'name' => 'OpenLiteSpeed',
        'status' => strpos($olsStatus, 'running') !== false ? 'running' : 'stopped',
        'version' => getOLSVersion()
    ];

    // MariaDB
    $mysqlStatus = shell_exec("systemctl is-active mariadb 2>&1");
    $services['MariaDB'] = [
        'name' => 'MariaDB',
        'status' => trim($mysqlStatus) === 'active' ? 'running' : 'stopped',
        'version' => getMariaDBVersion()
    ];

    // Redis
    $redisStatus = shell_exec("systemctl is-active redis 2>&1");
    $services['Redis'] = [
        'name' => 'Redis',
        'status' => trim($redisStatus) === 'active' ? 'running' : 'stopped',
        'version' => getRedisVersion()
    ];

    // PHP
    $services['PHP'] = [
        'name' => 'PHP',
        'status' => 'running', // PHP runs via OLS
        'version' => PHP_VERSION
    ];

    return $services;
}

/**
 * Helper: Get single service status
 */
function getServiceStatus($service) {
    $service = strtolower($service);

    switch ($service) {
        case 'ols':
        case 'openlitespeed':
            $status = shell_exec("/usr/local/lsws/bin/lswsctrl status 2>&1");
            return [
                'name' => 'OpenLiteSpeed',
                'status' => strpos($status, 'running') !== false ? 'running' : 'stopped',
                'version' => getOLSVersion(),
                'details' => $status
            ];

        case 'mariadb':
        case 'mysql':
            $status = shell_exec("systemctl is-active mariadb 2>&1");
            return [
                'name' => 'MariaDB',
                'status' => trim($status) === 'active' ? 'running' : 'stopped',
                'version' => getMariaDBVersion(),
                'enabled' => trim(shell_exec("systemctl is-enabled mariadb 2>&1")) === 'enabled'
            ];

        case 'redis':
            $status = shell_exec("systemctl is-active redis 2>&1");
            return [
                'name' => 'Redis',
                'status' => trim($status) === 'active' ? 'running' : 'stopped',
                'version' => getRedisVersion(),
                'enabled' => trim(shell_exec("systemctl is-enabled redis 2>&1")) === 'enabled'
            ];

        case 'php':
        case 'lsphp':
            return [
                'name' => 'PHP',
                'status' => 'running',
                'version' => PHP_VERSION,
                'sapi' => php_sapi_name()
            ];

        default:
            return [
                'name' => ucfirst($service),
                'status' => 'unknown',
                'error' => 'Unknown service'
            ];
    }
}

/**
 * Helper: Get OLS version
 */
function getOLSVersion() {
    $output = shell_exec("/usr/local/lsws/bin/lswsctrl version 2>&1");
    if (preg_match('/(\d+\.\d+\.\d+)/', $output, $matches)) {
        return $matches[1];
    }
    return 'Unknown';
}

/**
 * Helper: Get MariaDB version
 */
function getMariaDBVersion() {
    $output = shell_exec("mysql --version 2>&1");
    if (preg_match('/Distrib\s+(\d+\.\d+\.\d+)/', $output, $matches)) {
        return $matches[1];
    }
    return 'Unknown';
}

/**
 * Helper: Get Redis version
 */
function getRedisVersion() {
    $output = shell_exec("redis-server --version 2>&1");
    if (preg_match(/v=(\d+\.\d+\.\d+)/', $output, $matches)) {
        return $matches[1];
    }
    return 'Unknown';
}

/**
 * Helper: Get all disk mounts
 */
function getAllDiskMounts() {
    $mounts = [];
    $output = shell_exec("df -h | grep -vE '^Filesystem|tmpfs|cdrom'");

    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', $line);
        if (count($parts) >= 6) {
            $mounts[] = [
                'filesystem' => $parts[0],
                'size' => $parts[1],
                'used' => $parts[2],
                'available' => $parts[3],
                'usage_percent' => (int)str_replace('%', '', $parts[4]),
                'mount' => $parts[5]
            ];
        }
    }

    return $mounts;
}

/**
 * Helper: Get network interfaces
 */
function getNetworkInterfaces() {
    $interfaces = [];
    $output = shell_exec("ip -o addr show | awk '{print $2, $4}'");

    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        $parts = explode(' ', $line);
        if (count($parts) == 2) {
            $name = $parts[0];
            $ip = $parts[1];

            if (!isset($interfaces[$name])) {
                $interfaces[$name] = [
                    'name' => $name,
                    'addresses' => [],
                    'stats' => getInterfaceStats($name)
                ];
            }

            $interfaces[$name]['addresses'][] = $ip;
        }
    }

    return array_values($interfaces);
}

/**
 * Helper: Get interface stats
 */
function getInterfaceStats($interface) {
    $stats = [
        'rx_bytes' => 0,
        'tx_bytes' => 0,
        'rx_packets' => 0,
        'tx_packets' => 0
    ];

    $file = "/sys/class/net/{$interface}/statistics/rx_bytes";
    if (file_exists($file)) {
        $stats['rx_bytes'] = (int)trim(file_get_contents($file));
    }

    $file = "/sys/class/net/{$interface}/statistics/tx_bytes";
    if (file_exists($file)) {
        $stats['tx_bytes'] = (int)trim(file_get_contents($file));
    }

    $file = "/sys/class/net/{$interface}/statistics/rx_packets";
    if (file_exists($file)) {
        $stats['rx_packets'] = (int)trim(file_get_contents($file));
    }

    $file = "/sys/class/net/{$interface}/statistics/tx_packets";
    if (file_exists($file)) {
        $stats['tx_packets'] = (int)trim(file_get_contents($file));
    }

    return $stats;
}

/**
 * Helper: Parse ps output
 */
function parsePsOutput($output) {
    $processes = [];
    $lines = explode("\n", trim($output));

    // Skip header
    if (count($lines) > 0 && strpos($lines[0], 'USER') !== false) {
        array_shift($lines);
    }

    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', $line);
        if (count($parts) >= 11) {
            $processes[] = [
                'user' => $parts[0],
                'pid' => (int)$parts[1],
                'cpu' => floatval($parts[2]),
                'mem' => floatval($parts[3]),
                'vsz' => $parts[4],
                'rss' => $parts[5],
                'stat' => $parts[7],
                'start' => $parts[8],
                'time' => $parts[9],
                'command' => implode(' ', array_slice($parts, 10))
            ];
        }
    }

    return $processes;
}

/**
 * Helper: Get activity icon
 */
function getActivityIcon($action) {
    $icons = [
        'vhost_create' => '🌐',
        'vhost_delete' => '🗑️',
        'vhost_update' => '✏️',
        'extension_install' => '🔧',
        'extension_toggle' => '🔄',
        'firewall_add' => '🔥',
        'firewall_delete' => '🔒',
        'redis_restart' => '🔴',
        'redis_flush' => '🗑️',
        'backup_create' => '💾',
        'user_login' => '👤',
        'user_logout' => '🚪',
        'php_reload' => '🔄'
    ];

    return $icons[$action] ?? '📌';
}

/**
 * Helper: Get activity title
 */
function getActivityTitle($action, $activity) {
    $details = json_decode($activity['details'], true);

    switch ($action) {
        case 'vhost_create':
            return "Virtual host created: " . ($details['domain'] ?? 'Unknown');
        case 'vhost_delete':
            return "Virtual host deleted: " . ($details['domain'] ?? 'Unknown');
        case 'extension_install':
            return "PHP extension installed: " . ($details['extension'] ?? 'Unknown');
        case 'firewall_add':
            return "Firewall rule added: " . ($details['action'] ?? '') . " " . ($details['port'] ?? '');
        case 'redis_restart':
            return "Redis restarted";
        case 'backup_create':
            return "Backup created: " . ($details['type'] ?? 'full');
        case 'user_login':
            return "User logged in: " . ($activity['ip_address'] ?? 'Unknown IP');
        default:
            return ucfirst(str_replace('_', ' ', $action));
    }
}

/**
 * Helper: Calculate time elapsed
 */
function timeElapsed($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } else {
        return floor($diff / 86400) . ' days ago';
    }
}

/**
 * Helper: Format bytes
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Helper: Calculate CPU change (placeholder - implement with storage for real comparison)
 */
function calculateCPUChange($current) {
    // In real implementation, compare with stored previous value
    // For now, return small random change for demo
    return rand(-5, 5);
}

/**
 * Helper: Calculate memory change (placeholder)
 */
function calculateMemoryChange($current) {
    // In real implementation, compare with stored previous value
    return rand(-3, 3);
}
