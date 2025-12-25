<?php
/**
 * GDOLS Panel - Redis API Endpoint
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles Redis server management and configuration
 */

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'status':
            handleStatus();
            break;

        case 'info':
            handleInfo();
            break;

        case 'config':
            handleGetConfig();
            break;

        case 'update_config':
            handleUpdateConfig();
            break;

        case 'start':
            handleStart();
            break;

        case 'stop':
            handleStop();
            break;

        case 'restart':
            handleRestart();
            break;

        case 'flush':
            handleFlush();
            break;

        case 'flush_db':
            handleFlushDB();
            break;

        case 'get_stats':
            handleGetStats();
            break;

        case 'get_keys':
            handleGetKeys();
            break;

        case 'get_key':
            handleGetKey();
            break;

        case 'set_key':
            handleSetKey();
            break;

        case 'delete_key':
            handleDeleteKey();
            break;

        case 'monitor':
            handleMonitor();
            break;

        default:
            errorResponse('Invalid action', 400);
    }
} catch (Exception $e) {
    errorResponse('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Handle get Redis status
 */
function handleStatus() {
    global $system;

    requireAdmin();

    try {
        $result = $system->getRedisStatus();

        successResponse([
            'running' => $result['running'],
            'info' => $result['info']
        ], 'Redis status retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to get Redis status: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle get detailed Redis info
 */
function handleInfo() {
    requireAdmin();

    try {
        // Connect to Redis
        $redis = connectRedis();

        if (!$redis) {
            errorResponse('Redis is not running or connection failed', 503);
        }

        // Get server info
        $info = $redis->info();

        // Get specific sections
        $serverInfo = $redis->info('SERVER');
        $memoryInfo = $redis->info('MEMORY');
        $statsInfo = $redis->info('STATS');
        $replicationInfo = $redis->info('REPLICATION');

        successResponse([
            'server' => $serverInfo,
            'memory' => $memoryInfo,
            'stats' => $statsInfo,
            'replication' => $replicationInfo,
            'connected_clients' => $info['connected_clients'] ?? 0,
            'used_memory_human' => $info['used_memory_human'] ?? '0B',
            'uptime_in_days' => $info['uptime_in_days'] ?? 0,
            'total_connections_received' => $info['total_connections_received'] ?? 0,
            'total_commands_processed' => $info['total_commands_processed'] ?? 0,
            'keyspace' => getKeyspaceInfo($redis)
        ], 'Redis information retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to get Redis info: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle get Redis configuration
 */
function handleGetConfig() {
    global $conn;

    requireAdmin();

    try {
        $stmt = $conn->prepare("SELECT * FROM redis_config ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch();

        if (!$config) {
            // Return default config
            $config = [
                'maxmemory' => '2g',
                'maxmemory_policy' => 'allkeys-lru',
                'timeout' => 300,
                'tcp_keepalive' => 60,
                'password_enabled' => 0,
                'protected_mode' => 1
            ];
        }

        // Also get actual Redis config file
        $redisConfigFile = '/etc/redis/redis.conf';
        $actualConfig = [];

        if (file_exists($redisConfigFile)) {
            $configContent = file_get_contents($redisConfigFile);

            // Parse important settings
            $settings = [
                'maxmemory',
                'maxmemory-policy',
                'timeout',
                'tcp-keepalive',
                'requirepass',
                'protected-mode',
                'bind',
                'port',
                'databases'
            ];

            foreach ($settings as $setting) {
                if (preg_match('/^' . preg_quote($setting) . '\s+(.+)$/m', $configContent, $matches)) {
                    $actualConfig[$setting] = trim($matches[1]);
                }
            }
        }

        successResponse([
            'config' => $config,
            'actual_config' => $actualConfig,
            'config_file' => $redisConfigFile
        ], 'Redis configuration retrieved');

    } catch (PDOException $e) {
        errorResponse('Failed to get Redis configuration: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle update Redis configuration
 */
function handleUpdateConfig() {
    global $conn, $system, $logger;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $validPolicies = [
        'volatile-lru', 'allkeys-lru', 'volatile-random', 'allkeys-random',
        'volatile-ttl', 'noeviction', 'allkeys-lfu', 'volatile-lfu'
    ];

    if (!empty($input['maxmemory_policy']) && !in_array($input['maxmemory_policy'], $validPolicies)) {
        errorResponse('Invalid maxmemory_policy');
    }

    if (!empty($input['timeout']) && (!is_numeric($input['timeout']) || $input['timeout'] < 0)) {
        errorResponse('Invalid timeout value');
    }

    if (!empty($input['tcp_keepalive']) && (!is_numeric($input['tcp_keepalive']) || $input['tcp_keepalive'] < 0)) {
        errorResponse('Invalid tcp_keepalive value');
    }

    try {
        // Update configuration
        $config = [
            'maxmemory' => $input['maxmemory'] ?? '2g',
            'maxmemory_policy' => $input['maxmemory_policy'] ?? 'allkeys-lru',
            'timeout' => (int)($input['timeout'] ?? 300),
            'tcp_keepalive' => (int)($input['tcp_keepalive'] ?? 60)
        ];

        $result = $system->updateRedisConfig($config);

        if ($result['success']) {
            $logger->logActivity('redis_config_update', 'redis', null, $config);

            successResponse([
                'config' => $config,
                'restarted' => true
            ], 'Redis configuration updated successfully');
        } else {
            errorResponse($result['message'] ?? 'Failed to update Redis configuration', 500);
        }

    } catch (Exception $e) {
        $logger->logError("Failed to update Redis config: " . $e->getMessage());
        errorResponse('Failed to update configuration: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle start Redis
 */
function handleStart() {
    global $system, $logger;

    requireAdmin();

    try {
        $result = $system->manageRedis('start');

        if ($result['success']) {
            $logger->logActivity('redis_start', 'redis', null);

            successResponse([
                'started' => true
            ], 'Redis started successfully');
        } else {
            errorResponse($result['message'] ?? 'Failed to start Redis', 500);
        }

    } catch (Exception $e) {
        errorResponse('Failed to start Redis: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle stop Redis
 */
function handleStop() {
    global $system, $logger;

    requireAdmin();

    try {
        $result = $system->manageRedis('stop');

        if ($result['success']) {
            $logger->logActivity('redis_stop', 'redis', null);

            successResponse([
                'stopped' => true
            ], 'Redis stopped successfully');
        } else {
            errorResponse($result['message'] ?? 'Failed to stop Redis', 500);
        }

    } catch (Exception $e) {
        errorResponse('Failed to stop Redis: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle restart Redis
 */
function handleRestart() {
    global $system, $logger;

    requireAdmin();

    try {
        $result = $system->manageRedis('restart');

        if ($result['success']) {
            $logger->logActivity('redis_restart', 'redis', null);

            successResponse([
                'restarted' => true
            ], 'Redis restarted successfully');
        } else {
            errorResponse($result['message'] ?? 'Failed to restart Redis', 500);
        }

    } catch (Exception $e) {
        errorResponse('Failed to restart Redis: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle flush all data
 */
function handleFlush() {
    global $logger;

    requireAdmin();

    // Confirm action
    $confirm = $_GET['confirm'] ?? 'false';
    if ($confirm !== 'true') {
        errorResponse('Please confirm by adding ?confirm=true', 400);
    }

    try {
        $redis = connectRedis();

        if (!$redis) {
            errorResponse('Redis is not running', 503);
        }

        // Get current keys count
        $info = $redis->info();
        $keysCount = $info['db0'] ?? 0;

        // Flush all
        $result = $redis->flushAll();

        if ($result) {
            $logger->logActivity('redis_flush_all', 'redis', null, [
                'previous_keys' => $keysCount
            ]);

            successResponse([
                'flushed' => true,
                'previous_keys' => $keysCount
            ], 'Redis flushed successfully');
        } else {
            errorResponse('Failed to flush Redis', 500);
        }

    } catch (Exception $e) {
        $logger->logError("Failed to flush Redis: " . $e->getMessage());
        errorResponse('Failed to flush Redis: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle flush current database
 */
function handleFlushDB() {
    global $logger;

    requireAdmin();

    try {
        $redis = connectRedis();

        if (!$redis) {
            errorResponse('Redis is not running', 503);
        }

        // Get current keys count
        $info = $redis->info();
        $db = $input['db'] ?? 0;
        $dbKey = 'db' . $db;
        $keysCount = $info[$dbKey] ?? 0;

        // Flush current database
        $result = $redis->flushDB();

        if ($result) {
            $logger->logActivity('redis_flush_db', 'redis', null, [
                'database' => $db,
                'previous_keys' => $keysCount
            ]);

            successResponse([
                'flushed' => true,
                'database' => $db,
                'previous_keys' => $keysCount
            ], 'Redis database flushed successfully');
        } else {
            errorResponse('Failed to flush database', 500);
        }

    } catch (Exception $e) {
        $logger->logError("Failed to flush Redis DB: " . $e->getMessage());
        errorResponse('Failed to flush database: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle get Redis statistics
 */
function handleGetStats() {
    requireAdmin();

    try {
        $redis = connectRedis();

        if (!$redis) {
            errorResponse('Redis is not running', 503);
        }

        $info = $redis->info();
        $statsInfo = $redis->info('STATS');

        $stats = [
            'uptime_in_seconds' => $info['uptime_in_seconds'] ?? 0,
            'uptime_in_days' => $info['uptime_in_days'] ?? 0,
            'connected_clients' => $info['connected_clients'] ?? 0,
            'used_memory_human' => $info['used_memory_human'] ?? '0B',
            'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B',
            'total_connections_received' => $statsInfo['total_connections_received'] ?? 0,
            'total_commands_processed' => $statsInfo['total_commands_processed'] ?? 0,
            'instantaneous_ops_per_sec' => $statsInfo['instantaneous_ops_per_sec'] ?? 0,
            'keyspace' => getKeyspaceInfo($redis),
            'hits' => $statsInfo['keyspace_hits'] ?? 0,
            'misses' => $statsInfo['keyspace_misses'] ?? 0,
            'hit_rate' => calculateHitRate($statsInfo)
        ];

        successResponse($stats, 'Redis statistics retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to get Redis stats: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle get Redis keys
 */
function handleGetKeys() {
    requireAdmin();

    try {
        $redis = connectRedis();

        if (!$redis) {
            errorResponse('Redis is not running', 503);
        }

        $pattern = $_GET['pattern'] ?? '*';
        $limit = min((int)($_GET['limit'] ?? 100), 1000);
        $database = (int)($_GET['db'] ?? 0);

        // Select database
        $redis->select($database);

        // Get keys
        $keys = $redis->keys($pattern);
        $keys = array_slice($keys, 0, $limit);

        // Get key info
        $keyInfo = [];
        foreach ($keys as $key) {
            $type = $redis->type($key);
            $ttl = $redis->ttl($key);
            $size = 0;

            switch ($type) {
                case 'string':
                    $size = $redis->strlen($key);
                    break;
                case 'list':
                    $size = $redis->lLen($key);
                    break;
                case 'set':
                    $size = $redis->sCard($key);
                    break;
                case 'zset':
                    $size = $redis->zCard($key);
                    break;
                case 'hash':
                    $size = $redis->hLen($key);
                    break;
            }

            $keyInfo[] = [
                'key' => $key,
                'type' => $type,
                'ttl' => $ttl,
                'size' => $size,
                'encoding' => $redis->object('encoding', $key) ?: null
            ];
        }

        successResponse([
            'keys' => $keyInfo,
            'total' => count($keyInfo),
            'database' => $database,
            'pattern' => $pattern
        ], 'Redis keys retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to get Redis keys: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle get single key value
 */
function handleGetKey() {
    requireAdmin();

    $key = $_GET['key'] ?? '';

    if (empty($key)) {
        errorResponse('Key is required');
    }

    try {
        $redis = connectRedis();

        if (!$redis) {
            errorResponse('Redis is not running', 503);
        }

        $database = (int)($_GET['db'] ?? 0);
        $redis->select($database);

        if (!$redis->exists($key)) {
            errorResponse('Key does not exist', 404);
        }

        $type = $redis->type($key);
        $value = null;
        $metadata = [
            'ttl' => $redis->ttl($key),
            'encoding' => $redis->object('encoding', $key) ?: null
        ];

        switch ($type) {
            case 'string':
                $value = $redis->get($key);
                $metadata['length'] = $redis->strlen($key);
                break;

            case 'list':
                $start = (int)($_GET['start'] ?? 0);
                $stop = (int)($_GET['stop'] ?? -1);
                $value = $redis->lRange($key, $start, $stop);
                $metadata['length'] = $redis->lLen($key);
                break;

            case 'set':
                $value = $redis->sMembers($key);
                $metadata['count'] = $redis->sCard($key);
                break;

            case 'zset':
                $start = (int)($_GET['start'] ?? 0);
                $stop = (int)($_GET['stop'] ?? -1);
                $value = $redis->zRange($key, $start, $stop, ['withscores' => true]);
                $metadata['count'] = $redis->zCard($key);
                break;

            case 'hash':
                $value = $redis->hGetAll($key);
                $metadata['count'] = $redis->hLen($key);
                break;
        }

        successResponse([
            'key' => $key,
            'type' => $type,
            'value' => $value,
            'metadata' => $metadata,
            'database' => $database
        ], 'Key value retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to get key: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle set key value
 */
function handleSetKey() {
    global $logger;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $key = $input['key'] ?? '';
    $value = $input['value'] ?? null;
    $type = $input['type'] ?? 'string';
    $database = (int)($input['db'] ?? 0);
    $ttl = (int)($input['ttl'] ?? 0);

    if (empty($key)) {
        errorResponse('Key is required');
    }

    if ($value === null && $type !== 'string') {
        errorResponse('Value is required');
    }

    try {
        $redis = connectRedis();

        if (!$redis) {
            errorResponse('Redis is not running', 503);
        }

        $redis->select($database);

        switch ($type) {
            case 'string':
                if ($ttl > 0) {
                    $redis->setex($key, $ttl, $value);
                } else {
                    $redis->set($key, $value);
                }
                break;

            case 'list':
                if (!is_array($value)) {
                    errorResponse('Value must be an array for list type');
                }
                $redis->del($key);
                foreach ($value as $item) {
                    $redis->rPush($key, $item);
                }
                break;

            case 'set':
                if (!is_array($value)) {
                    errorResponse('Value must be an array for set type');
                }
                $redis->del($key);
                $redis->sAddArray($key, $value);
                break;

            case 'hash':
                if (!is_array($value)) {
                    errorResponse('Value must be an array for hash type');
                }
                $redis->del($key);
                $redis->hMSet($key, $value);
                break;

            default:
                errorResponse('Invalid data type');
        }

        // Set TTL if specified
        if ($ttl > 0 && $type !== 'string') {
            $redis->expire($key, $ttl);
        }

        $logger->logActivity('redis_set_key', 'redis_key', null, [
            'key' => $key,
            'type' => $type,
            'database' => $database
        ]);

        successResponse([
            'key' => $key,
            'type' => $type,
            'database' => $database,
            'ttl' => $ttl > 0 ? $ttl : null
        ], 'Key set successfully');

    } catch (Exception $e) {
        $logger->logError("Failed to set Redis key: " . $e->getMessage());
        errorResponse('Failed to set key: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle delete key
 */
function handleDeleteKey() {
    global $logger;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $keys = $input['keys'] ?? [];
    $database = (int)($input['db'] ?? 0);

    if (empty($keys)) {
        errorResponse('Keys are required');
    }

    if (!is_array($keys)) {
        $keys = [$keys];
    }

    try {
        $redis = connectRedis();

        if (!$redis) {
            errorResponse('Redis is not running', 503);
        }

        $redis->select($database);
        $deleted = $redis->del($keys);

        $logger->logActivity('redis_delete_keys', 'redis_key', null, [
            'keys' => $keys,
            'database' => $database,
            'deleted_count' => $deleted
        ]);

        successResponse([
            'deleted' => $deleted,
            'keys' => $keys,
            'database' => $database
        ], 'Keys deleted successfully');

    } catch (Exception $e) {
        $logger->logError("Failed to delete Redis keys: " . $e->getMessage());
        errorResponse('Failed to delete keys: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle monitor Redis commands
 */
function handleMonitor() {
    requireAdmin();

    // This is a long-running operation, consider using WebSocket
    // For now, return recent slowlog

    try {
        $redis = connectRedis();

        if (!$redis) {
            errorResponse('Redis is not running', 503);
        }

        // Get slowlog
        $slowlog = $redis->slowLog('get', 10);

        $commands = [];
        foreach ($slowlog as $entry) {
            $commands[] = [
                'id' => $entry[0],
                'timestamp' => $entry[1],
                'duration' => $entry[2],
                'command' => implode(' ', $entry[3])
            ];
        }

        successResponse([
            'commands' => $commands,
            'slowlog_length' => $redis->slowLog('len')
        ], 'Redis commands retrieved');

    } catch (Exception $e) {
        errorResponse('Failed to get Redis commands: ' . $e->getMessage(), 500);
    }
}

/**
 * Helper: Connect to Redis
 */
function connectRedis() {
    try {
        $redis = new Redis();
        $connected = $redis->connect(
            defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1',
            defined('REDIS_PORT') ? REDIS_PORT : 6379
        );

        if (!$connected) {
            return null;
        }

        // Authenticate if password is set
        if (defined('REDIS_PASS') && REDIS_PASS) {
            if (!$redis->auth(REDIS_PASS)) {
                return null;
            }
        }

        return $redis;

    } catch (Exception $e) {
        return null;
    }
}

/**
 * Helper: Get keyspace info
 */
function getKeyspaceInfo($redis) {
    $info = $redis->info();
    $keyspace = [];

    foreach ($info as $key => $value) {
        if (strpos($key, 'db') === 0 && preg_match('/^db\d+$/', $key)) {
            $keyspace[$key] = $value;
        }
    }

    return $keyspace;
}

/**
 * Helper: Calculate hit rate
 */
function calculateHitRate($statsInfo) {
    $hits = (int)($statsInfo['keyspace_hits'] ?? 0);
    $misses = (int)($statsInfo['keyspace_misses'] ?? 0);
    $total = $hits + $misses;

    if ($total === 0) {
        return 0;
    }

    return round(($hits / $total) * 100, 2);
}
