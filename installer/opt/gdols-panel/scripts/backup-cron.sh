#!/bin/bash

################################################################################
# GD Panel - Automated Backup Cron Job
# Author: GoDiMyID
# Website: godi.my.id
# Version: 1.0.0
# Description: Automated backup script for databases, virtual hosts, and configs
################################################################################

# Set strict mode
set -e

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Load configuration
if [ -f "$PROJECT_ROOT/config/config.php" ]; then
    eval "$(php -r "
    define('GD_PANEL_ACCESS', true);
    require_once '$PROJECT_ROOT/config/config.php';
    echo 'LOG_PATH=' . LOG_PATH . PHP_EOL;
    echo 'BACKUP_PATH=' . BACKUP_PATH . PHP_EOL;
    echo 'PANEL_TIMEZONE=' . PANEL_TIMEZONE . PHP_EOL;
    echo 'LOG_FILE=' . LOG_PATH . '/backup.log' . PHP_EOL;
    ")"
else
    echo "Error: Configuration file not found"
    exit 1
fi

# Log file
LOGFILE="${LOG_FILE:-$LOG_PATH/backup.log}"

# Function to log messages
log_message() {
    local level="$1"
    shift
    local message="$@"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" | tee -a "$LOGFILE"
}

# Function to check if backup is enabled
is_backup_enabled() {
    php -r "
    define('GD_PANEL_ACCESS', true);
    require_once '$PROJECT_ROOT/config/backup.php';
    \$config = require '$PROJECT_ROOT/config/backup.php';
    echo \$config['enabled'] ? '1' : '0';
    "
}

# Function to execute PHP backup script
run_backup() {
    log_message "INFO" "Starting scheduled backup..."

    cd "$PROJECT_ROOT"

    # Run PHP backup script
    php -r "
    define('GD_PANEL_ACCESS', true);
    require_once 'api/bootstrap.php';
    require_once 'api/classes/BackupManager.php';

    try {
        \$backupManager = new BackupManager();
        \$result = \$backupManager->createScheduledBackup();

        if (\$result['success']) {
            echo json_encode(\$result);
        } else {
            throw new Exception(\$result['error']);
        }
    } catch (Exception \$e) {
        echo 'Error: ' . \$e->getMessage();
        exit(1);
    }
    " 2>&1 | while IFS= read -r line; do
        log_message "INFO" "$line"
    done

    local exit_code=${PIPESTATUS[0]}

    if [ $exit_code -eq 0 ]; then
        log_message "INFO" "Backup completed successfully"
        return 0
    else
        log_message "ERROR" "Backup failed with exit code $exit_code"
        return 1
    fi
}

# Function to cleanup old backups
cleanup_old_backups() {
    log_message "INFO" "Starting cleanup of old backups..."

    cd "$PROJECT_ROOT"

    php -r "
    define('GD_PANEL_ACCESS', true);
    require_once 'api/classes/BackupManager.php';

    \$backupManager = new BackupManager();
    \$backupManager->cleanupOldBackups();
    " 2>&1 | while IFS= read -r line; do
        log_message "INFO" "$line"
    done
}

# Function to send notification
send_notification() {
    local status="$1"
    local message="$2"

    # Check if notifications are enabled
    local notify_enabled=$(php -r "
    define('GD_PANEL_ACCESS', true);
    require_once '$PROJECT_ROOT/config/backup.php';
    \$config = require '$PROJECT_ROOT/config/backup.php';
    echo \$config['notifications']['enabled'] ? '1' : '0';
    ")

    if [ "$notify_enabled" = "1" ]; then
        local email=$(php -r "
        define('GD_PANEL_ACCESS', true);
        require_once '$PROJECT_ROOT/config/backup.php';
        \$config = require '$PROJECT_ROOT/config/backup.php';
        echo \$config['notifications']['email'];
        ")

        local should_send=false
        if [ "$status" = "success" ]; then
            local on_success=$(php -r "
            define('GD_PANEL_ACCESS', true);
            require_once '$PROJECT_ROOT/config/backup.php';
            \$config = require '$PROJECT_ROOT/config/backup.php';
            echo \$config['notifications']['on_success'] ? '1' : '0';
            ")
            [ "$on_success" = "1" ] && should_send=true
        elif [ "$status" = "failure" ]; then
            local on_failure=$(php -r "
            define('GD_PANEL_ACCESS', true);
            require_once '$PROJECT_ROOT/config/backup.php';
            \$config = require '$PROJECT_ROOT/config/backup.php';
            echo \$config['notifications']['on_failure'] ? '1' : '0';
            ")
            [ "$on_failure" = "1" ] && should_send=true
        fi

        if [ "$should_send" = true ]; then
            echo "$message" | mail -s "GD Panel Backup $status" "$email"
            log_message "INFO" "Notification sent to $email"
        fi
    fi
}

# Function to generate backup report
generate_report() {
    log_message "INFO" "Generating backup report..."

    local report_file="$BACKUP_PATH/reports/backup_report_$(date '+%Y%m%d_%H%M%S').txt"

    cd "$PROJECT_ROOT"

    php -r "
    define('GD_PANEL_ACCESS', true);
    require_once 'api/classes/BackupManager.php';

    \$backupManager = new BackupManager();
    \$stats = \$backupManager->getBackupStatistics();

    \$report = \"GD Panel Backup Report\n\";
    \$report .= \"Generated: \" . date('Y-m-d H:i:s') . \"\n\";
    \$report .= \"=\" . str_repeat('=', 50) . \"\n\n\";

    \$report .= \"Statistics:\n\";
    \$report .= \"  Total Backups: \" . \$stats['total_backups'] . \"\n\";
    \$report .= \"  Total Size: \" . \$stats['total_size_formatted'] . \"\n\n\";

    \$report .= \"Backups by Type:\n\";
    foreach (\$stats['by_type'] as \$type => \$count) {
        \$report .= \"  \$type: \$count\n\";
    }

    \$report .= \"\nLatest Backups:\n\";
    foreach (\$stats['latest_backups'] as \$type => \$backup) {
        \$report .= \"  \$type: \" . \$backup['filename'] . \" (\" . \$backup['created'] . \")\n\";
    }

    echo \$report;
    " > "$report_file"

    log_message "INFO" "Backup report saved to $report_file"
}

# Function to check disk space
check_disk_space() {
    local threshold=90
    local usage=$(df -h "$BACKUP_PATH" | tail -1 | awk '{print $5}' | sed 's/%//')

    if [ "$usage" -gt "$threshold" ]; then
        log_message "WARNING" "Disk space usage is at ${usage}%. Consider freeing up space."
        send_notification "warning" "Disk space warning: ${usage}% used on backup partition"
    fi
}

# Main execution
main() {
    log_message "INFO" "=========================================="
    log_message "INFO" "GD Panel Backup Cron Job Started"
    log_message "INFO" "=========================================="

    # Check if backup is enabled
    if [ "$(is_backup_enabled)" != "1" ]; then
        log_message "WARNING" "Automated backups are disabled in configuration"
        exit 0
    fi

    # Check disk space before starting
    check_disk_space

    # Run backup
    if run_backup; then
        send_notification "success" "GD Panel backup completed successfully at $(date '+%Y-%m-%d %H:%M:%S')"

        # Generate report if enabled
        local reports_enabled=$(php -r "
        define('GD_PANEL_ACCESS', true);
        require_once '$PROJECT_ROOT/config/backup.php';
        \$config = require '$PROJECT_ROOT/config/backup.php';
        echo \$config['reports']['enabled'] ? '1' : '0';
        ")

        if [ "$reports_enabled" = "1" ]; then
            generate_report
        fi
    else
        send_notification "failure" "GD Panel backup failed at $(date '+%Y-%m-%d %H:%M:%S'). Check logs for details."
        exit 1
    fi

    # Cleanup old backups
    cleanup_old_backups

    log_message "INFO" "=========================================="
    log_message "INFO" "GD Panel Backup Cron Job Completed"
    log_message "INFO" "=========================================="
}

# Run main function
main "$@"
