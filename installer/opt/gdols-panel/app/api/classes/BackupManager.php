<?php
/**
 * GDOLS Panel - Backup Manager Class
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Automated backup system for databases, virtual hosts, and configurations
 */

class BackupManager {
    private $db;
    private $config;
    private $backupDir;
    private $logger;

    public function __construct($database = null) {
        $this->config = require CONFIG_PATH . '/backup.php';
        $this->backupDir = BACKUP_PATH;
        $this->db = $database;

        // Initialize logger
        $this->logger = Logger::getInstance();

        // Create backup directories if they don't exist
        $this->initBackupDirectories();

        // Clean up old backups on initialization
        $this->cleanupOldBackups();
    }

    /**
     * Initialize backup directory structure
     */
    private function initBackupDirectories() {
        $directories = [
            $this->backupDir,
            $this->backupDir . '/database',
            $this->backupDir . '/vhosts',
            $this->backupDir . '/config',
            $this->backupDir . '/logs',
            $this->backupDir . '/scheduled',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Create database backup
     *
     * @param string $database Database name
     * @param string $filename Optional custom filename
     * @return array Backup result with filename and path
     */
    public function backupDatabase($database, $filename = null) {
        try {
            $dbConfig = require CONFIG_PATH . '/database.php';

            // Generate filename if not provided
            if (!$filename) {
                $filename = $database . '_' . date('Y-m-d_H-i-s') . '.sql';
            }

            $backupPath = $this->backupDir . '/database/' . $filename;

            // Build mysqldump command
            $command = sprintf(
                'mysqldump -h%s -P%s -u%s -p%s %s > %s 2>&1',
                $dbConfig['db_host'],
                $dbConfig['db_port'],
                'root',
                escapeshellarg($dbConfig['db_root_password'] ?? ''),
                $database,
                $backupPath
            );

            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new Exception('Failed to create database backup');
            }

            // Compress backup
            $this->compressBackup($backupPath);

            // Log backup
            $this->logger->logAction('database_backup', "Database backup created: $database -> $filename");

            return [
                'success' => true,
                'filename' => $filename . '.gz',
                'path' => $backupPath . '.gz',
                'size' => filesize($backupPath . '.gz'),
                'database' => $database
            ];

        } catch (Exception $e) {
            $this->logger->logError('Database backup failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create virtual host backup
     *
     * @param string $domain Domain name
     * @return array Backup result
     */
    public function backupVirtualHost($domain) {
        try {
            $vhostConfig = "/usr/local/lsws/conf/vhosts/{$domain}/vhconf.conf";
            $vhostRoot = "/var/www/{$domain}";

            if (!file_exists($vhostConfig)) {
                throw new Exception('Virtual host configuration not found');
            }

            $timestamp = date('Y-m-d_H-i-s');
            $backupName = $domain . '_' . $timestamp;
            $backupPath = $this->backupDir . '/vhosts/' . $backupName;

            // Create backup directory
            mkdir($backupPath, 0755, true);

            // Backup configuration
            copy($vhostConfig, $backupPath . '/vhconf.conf');

            // Backup files if directory exists
            if (is_dir($vhostRoot)) {
                $this->createZipArchive(
                    $vhostRoot,
                    $backupPath . '/files.zip'
                );
            }

            // Create meta information
            $meta = [
                'domain' => $domain,
                'backup_date' => date('Y-m-d H:i:s'),
                'config_file' => $vhostConfig,
                'root_directory' => $vhostRoot,
                'files_included' => is_dir($vhostRoot),
                'version' => '1.0'
            ];

            file_put_contents($backupPath . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT));

            // Compress entire backup
            $this->createZipArchive($backupPath, $backupPath . '.zip');
            $this->deleteDirectory($backupPath);

            // Log backup
            $this->logger->logAction('vhost_backup', "Virtual host backup created: $domain");

            return [
                'success' => true,
                'filename' => $backupName . '.zip',
                'path' => $backupPath . '.zip',
                'size' => filesize($backupPath . '.zip'),
                'domain' => $domain
            ];

        } catch (Exception $e) {
            $this->logger->logError('Virtual host backup failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create configuration backup
     *
     * @return array Backup result
     */
    public function backupConfiguration() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = 'config_' . $timestamp;
            $backupPath = $this->backupDir . '/config/' . $backupName;

            mkdir($backupPath, 0755, true);

            // Files to backup
            $configFiles = [
                '/usr/local/lsws/conf/httpd_config.conf' => 'httpd_config.conf',
                dirname(__DIR__) . '/../config/config.php' => 'panel_config.php',
                dirname(__DIR__) . '/../config/database.php' => 'database_config.php',
                '/etc/ufw/user.rules' => 'ufw_rules',
            ];

            foreach ($configFiles as $source => $dest) {
                if (file_exists($source)) {
                    copy($source, $backupPath . '/' . $dest);
                }
            }

            // Create meta information
            $meta = [
                'backup_date' => date('Y-m-d H:i:s'),
                'type' => 'configuration',
                'version' => '1.0',
                'files_included' => array_keys($configFiles)
            ];

            file_put_contents($backupPath . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT));

            // Compress backup
            $this->createZipArchive($backupPath, $backupPath . '.zip');
            $this->deleteDirectory($backupPath);

            // Log backup
            $this->logger->logAction('config_backup', "Configuration backup created: $backupName");

            return [
                'success' => true,
                'filename' => $backupName . '.zip',
                'path' => $backupPath . '.zip',
                'size' => filesize($backupPath . '.zip')
            ];

        } catch (Exception $e) {
            $this->logger->logError('Configuration backup failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restore database from backup
     *
     * @param string $backupFile Backup filename
     * @param string $database Target database name
     * @return array Restore result
     */
    public function restoreDatabase($backupFile, $database) {
        try {
            $backupPath = $this->backupDir . '/database/' . $backupFile;

            if (!file_exists($backupPath)) {
                throw new Exception('Backup file not found');
            }

            // Decompress if needed
            if (substr($backupPath, -3) === '.gz') {
                $backupPath = $this->decompressBackup($backupPath);
            }

            $dbConfig = require CONFIG_PATH . '/database.php';

            // Restore database
            $command = sprintf(
                'mysql -h%s -P%s -u%s -p%s %s < %s 2>&1',
                $dbConfig['db_host'],
                $dbConfig['db_port'],
                'root',
                escapeshellarg($dbConfig['db_root_password'] ?? ''),
                $database,
                $backupPath
            );

            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new Exception('Failed to restore database');
            }

            // Clean up temporary decompressed file
            if (substr($backupPath, -3) !== '.gz' && file_exists($backupPath)) {
                @unlink($backupPath);
            }

            // Log restore
            $this->logger->logAction('database_restore', "Database restored: $database from $backupFile");

            return [
                'success' => true,
                'message' => 'Database restored successfully'
            ];

        } catch (Exception $e) {
            $this->logger->logError('Database restore failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create scheduled backup based on configuration
     *
     * @return array Results of scheduled backup
     */
    public function createScheduledBackup() {
        $results = [];

        try {
            // Backup all configured databases
            if (!empty($this->config['databases'])) {
                foreach ($this->config['databases'] as $database) {
                    $results['databases'][$database] = $this->backupDatabase($database);
                }
            }

            // Backup all configured virtual hosts
            if (!empty($this->config['vhosts'])) {
                foreach ($this->config['vhosts'] as $domain) {
                    $results['vhosts'][$domain] = $this->backupVirtualHost($domain);
                }
            }

            // Backup configuration if enabled
            if ($this->config['backup_config']) {
                $results['config'] = $this->backupConfiguration();
            }

            // Store scheduled backup metadata
            $this->storeScheduledBackupMetadata($results);

            return [
                'success' => true,
                'results' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            $this->logger->logError('Scheduled backup failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get list of available backups
     *
     * @param string $type Backup type (database, vhost, config, all)
     * @return array List of backups
     */
    public function getBackupList($type = 'all') {
        $backups = [];

        try {
            $types = $type === 'all'
                ? ['database', 'vhosts', 'config', 'scheduled']
                : [$type];

            foreach ($types as $backupType) {
                $dir = $this->backupDir . '/' . $backupType;

                if (is_dir($dir)) {
                    $files = scandir($dir);

                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            $filePath = $dir . '/' . $file;

                            if (is_file($filePath)) {
                                $backups[$backupType][] = [
                                    'filename' => $file,
                                    'path' => $filePath,
                                    'size' => filesize($filePath),
                                    'created' => date('Y-m-d H:i:s', filemtime($filePath)),
                                    'type' => $backupType
                                ];
                            }
                        }
                    }
                }
            }

            // Sort by creation date (newest first)
            foreach ($backups as &$backupList) {
                usort($backupList, function($a, $b) {
                    return strtotime($b['created']) - strtotime($a['created']);
                });
            }

            return $backups;

        } catch (Exception $e) {
            $this->logger->logError('Failed to get backup list: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete backup file
     *
     * @param string $type Backup type
     * @param string $filename Backup filename
     * @return bool Success status
     */
    public function deleteBackup($type, $filename) {
        try {
            $filePath = $this->backupDir . '/' . $type . '/' . $filename;

            if (!file_exists($filePath)) {
                throw new Exception('Backup file not found');
            }

            if (!unlink($filePath)) {
                throw new Exception('Failed to delete backup file');
            }

            // Log deletion
            $this->logger->logAction('backup_delete', "Backup deleted: $type/$filename");

            return true;

        } catch (Exception $e) {
            $this->logger->logError('Failed to delete backup: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Download backup file
     *
     * @param string $type Backup type
     * @param string $filename Backup filename
     */
    public function downloadBackup($type, $filename) {
        try {
            $filePath = $this->backupDir . '/' . $type . '/' . $filename;

            if (!file_exists($filePath)) {
                throw new Exception('Backup file not found');
            }

            // Set headers for download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Pragma: no-cache');
            header('Expires: 0');

            // Output file
            readfile($filePath);

            // Log download
            $this->logger->logAction('backup_download', "Backup downloaded: $type/$filename");

            exit;

        } catch (Exception $e) {
            $this->logger->logError('Failed to download backup: ' . $e->getMessage());
            http_response_code(404);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Clean up old backups based on retention policy
     */
    private function cleanupOldBackups() {
        try {
            $retentionDays = $this->config['retention_days'] ?? 7;
            $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);

            $directories = [
                $this->backupDir . '/database',
                $this->backupDir . '/vhosts',
                $this->backupDir . '/config',
                $this->backupDir . '/scheduled'
            ];

            $deletedCount = 0;

            foreach ($directories as $dir) {
                if (is_dir($dir)) {
                    $files = scandir($dir);

                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            $filePath = $dir . '/' . $file;

                            if (is_file($filePath) && filemtime($filePath) < $cutoffTime) {
                                if (unlink($filePath)) {
                                    $deletedCount++;
                                }
                            }
                        }
                    }
                }
            }

            if ($deletedCount > 0) {
                $this->logger->logAction('backup_cleanup', "Cleaned up $deletedCount old backups");
            }

        } catch (Exception $e) {
            $this->logger->logError('Backup cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Compress backup file using gzip
     *
     * @param string $filePath Path to file to compress
     */
    private function compressBackup($filePath) {
        if (file_exists($filePath)) {
            $compressed = gzopen($filePath . '.gz', 'wb9');
            $fileHandle = fopen($filePath, 'rb');

            while (!feof($fileHandle)) {
                gzwrite($compressed, fread($fileHandle, 4096));
            }

            fclose($fileHandle);
            gzclose($compressed);

            // Remove original file
            @unlink($filePath);
        }
    }

    /**
     * Decompress backup file
     *
     * @param string $compressedPath Path to compressed file
     * @return string Path to decompressed file
     */
    private function decompressBackup($compressedPath) {
        $decompressedPath = str_replace('.gz', '', $compressedPath);

        $compressed = gzopen($compressedPath, 'rb');
        $fileHandle = fopen($decompressedPath, 'wb');

        while (!gzeof($compressed)) {
            fwrite($fileHandle, gzread($compressed, 4096));
        }

        fclose($fileHandle);
        gzclose($compressed);

        return $decompressedPath;
    }

    /**
     * Create ZIP archive
     *
     * @param string $source Path to source file/directory
     * @param string $destination Path to destination ZIP file
     */
    private function createZipArchive($source, $destination) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available');
        }

        $zip = new ZipArchive();

        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception('Cannot create ZIP archive');
        }

        if (is_dir($source)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($source) + 1);

                    $zip->addFile($filePath, $relativePath);
                }
            }
        } elseif (is_file($source)) {
            $zip->addFile($source, basename($source));
        }

        $zip->close();
    }

    /**
     * Delete directory recursively
     *
     * @param string $dir Path to directory
     */
    private function deleteDirectory($dir) {
        if (is_dir($dir)) {
            $files = scandir($dir);

            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $path = $dir . '/' . $file;

                    if (is_dir($path)) {
                        $this->deleteDirectory($path);
                    } else {
                        @unlink($path);
                    }
                }
            }

            @rmdir($dir);
        }
    }

    /**
     * Store metadata for scheduled backup
     *
     * @param array $results Backup results
     */
    private function storeScheduledBackupMetadata($results) {
        $metadata = [
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $results,
            'config' => [
                'databases' => $this->config['databases'] ?? [],
                'vhosts' => $this->config['vhosts'] ?? [],
                'backup_config' => $this->config['backup_config'] ?? false
            ]
        ];

        $metaFile = $this->backupDir . '/scheduled/last_backup.json';
        file_put_contents($metaFile, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Get backup statistics
     *
     * @return array Backup statistics
     */
    public function getBackupStatistics() {
        $stats = [
            'total_backups' => 0,
            'total_size' => 0,
            'by_type' => [],
            'latest_backups' => []
        ];

        $backups = $this->getBackupList('all');

        foreach ($backups as $type => $files) {
            $stats['by_type'][$type] = count($files);
            $stats['total_backups'] += count($files);

            foreach ($files as $file) {
                $stats['total_size'] += $file['size'];
            }

            if (!empty($files)) {
                $stats['latest_backups'][$type] = $files[0];
            }
        }

        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);

        return $stats;
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
