<?php
/**
 * GDOLS Panel - Logger Class
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles system logging and audit trail
 */

// Prevent direct access
if (!defined('GDOLS_PANEL_ACCESS')) {
    die('Direct access not permitted');
}

class Logger {
    private static $instance = null;
    private $conn;
    private $logFile;
    private $errorFile;

    public function __construct() {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
        $this->logFile = ERROR_LOG;
        $this->errorFile = SYSTEM_LOG;
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log API access
     */
    public function logAccess() {
        $this->logActivity('api_access', 'API', null, [
            'endpoint' => $_SERVER['REQUEST_URI'],
            'method' => $_SERVER['REQUEST_METHOD']
        ]);
    }

    /**
     * Log user activity
     */
    public function logActivity($action, $entity = null, $entityId = null, $details = []) {
        try {
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $ipAddress = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            $detailsJson = !empty($details) ? json_encode($details) : null;

            $stmt = $this->conn->prepare("
                INSERT INTO system_logs (user_id, action, entity, entity_id, details, ip_address, user_agent, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'success')
            ");

            $stmt->execute([
                $userId,
                $action,
                $entity,
                $entityId,
                $detailsJson,
                $ipAddress,
                $userAgent
            ]);

        } catch (PDOException $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }

    /**
     * Log error
     */
    public function logError($message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] ERROR: {$message}{$contextStr}\n";

        // Write to error log file
        file_put_contents($this->errorFile, $logMessage, FILE_APPEND | LOCK_EX);

        // Also log to database
        try {
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

            $stmt = $this->conn->prepare("
                INSERT INTO system_logs (user_id, action, entity, details, ip_address, user_agent, status)
                VALUES (?, ?, ?, ?, ?, ?, 'failed')
            ");

            $stmt->execute([
                $userId,
                'error',
                null,
                $message,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

        } catch (PDOException $e) {
            // If database logging fails, just write to file
            file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Log warning
     */
    public function logWarning($message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] WARNING: {$message}{$contextStr}\n";

        file_put_contents($this->errorFile, $logMessage, FILE_APPEND | LOCK_EX);

        try {
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

            $stmt = $this->conn->prepare("
                INSERT INTO system_logs (user_id, action, entity, details, ip_address, user_agent, status)
                VALUES (?, ?, ?, ?, ?, ?, 'warning')
            ");

            $stmt->execute([
                $userId,
                'warning',
                null,
                $message,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

        } catch (PDOException $e) {
            file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Log info
     */
    public function logInfo($message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] INFO: {$message}{$contextStr}\n";

        file_put_contents($this->errorFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get logs from database
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        try {
            $sql = "SELECT * FROM system_logs WHERE 1=1";
            $params = [];

            if (isset($filters['user_id'])) {
                $sql .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (isset($filters['action'])) {
                $sql .= " AND action = ?";
                $params[] = $filters['action'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND created_at <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            $this->logError("Failed to get logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get log statistics
     */
    public function getLogStats($days = 7) {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    DATE(created_at) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning
                FROM system_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");

            $stmt->execute([$days]);
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            $this->logError("Failed to get log stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear old logs
     */
    public function clearOldLogs($days = 30) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM system_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            $stmt->execute([$days]);

            $this->logInfo("Cleared logs older than {$days} days", [
                'deleted_rows' => $stmt->rowCount()
            ]);

            return $stmt->rowCount();

        } catch (PDOException $e) {
            $this->logError("Failed to clear old logs: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Export logs to file
     */
    public function exportLogs($filters = [], $format = 'json') {
        try {
            $logs = $this->getLogs($filters, 10000); // Get up to 10000 logs

            $filename = "logs_export_" . date('Y-m-d_H-i-s');
            $filepath = GDOLS_PANEL_ROOT . "/exports/{$filename}.{$format}";

            // Ensure exports directory exists
            if (!is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }

            if ($format === 'json') {
                file_put_contents($filepath, json_encode($logs, JSON_PRETTY_PRINT));
            } elseif ($format === 'csv') {
                $fp = fopen($filepath, 'w');
                if (!empty($logs)) {
                    fputcsv($fp, array_keys($logs[0]));
                    foreach ($logs as $log) {
                        fputcsv($fp, $log);
                    }
                }
                fclose($fp);
            }

            $this->logActivity('logs_export', 'system_logs', null, [
                'format' => $format,
                'count' => count($logs)
            ]);

            return $filepath;

        } catch (Exception $e) {
            $this->logError("Failed to export logs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip = '';

        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Rotate log files
     */
    public function rotateLogs() {
        try {
            $logFiles = [
                ERROR_LOG,
                SYSTEM_LOG,
                ACCESS_LOG
            ];

            foreach ($logFiles as $file) {
                if (file_exists($file) && filesize($file) > 10 * 1024 * 1024) { // 10MB
                    $backupFile = $file . '.' . date('Y-m-d_H-i-s') . '.bak';
                    rename($file, $backupFile);

                    // Compress old log
                    if (function_exists('gzcompress')) {
                        $compressed = gzcompress(file_get_contents($backupFile));
                        file_put_contents($backupFile . '.gz', $compressed);
                        unlink($backupFile);
                    }

                    $this->logInfo("Rotated log file: {$file}");
                }
            }

            return true;

        } catch (Exception $e) {
            $this->logError("Failed to rotate logs: " . $e->getMessage());
            return false;
        }
    }
}
