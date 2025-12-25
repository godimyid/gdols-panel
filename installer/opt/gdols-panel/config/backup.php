<?php
/**
 * GDOLS Panel - Backup Configuration
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Automated backup system configuration
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    |
    | Configure automated backup settings for databases, virtual hosts,
    | and system configurations.
    |
    */

    // Enable/disable automated backups
    "enabled" => true,

    // Backup schedule (cron format)
    // Default: Daily at 2:00 AM
    "schedule" => "0 2 * * *",

    // Retention policy - how many days to keep backups
    "retention_days" => 7,

    // Maximum backup size in MB (0 = unlimited)
    "max_backup_size" => 0,

    // Compression (gzip)
    "compress" => true,

    // Email notifications for backup results
    "notifications" => [
        "enabled" => false,
        "email" => "your-email@example.com", // CHANGE THIS to your email address
        "on_success" => true,
        "on_failure" => true,
    ],

    // Databases to backup automatically
    "databases" => [
        // Add your actual database names here
        // 'database_name_1',
        // 'database_name_2',
    ],

    // Virtual hosts to backup automatically
    "vhosts" => [
        // Add your actual domain names here
        // 'yourdomain.com',
        // 'yoursubdomain.domain.com',
    ],

    // Backup panel configuration
    "backup_config" => true,

    // Backup locations
    "directories" => [
        // Additional directories to include in backups
        // '/path/to/directory',
    ],

    // Exclude patterns (for file backups)
    "exclude" => [
        "node_modules",
        ".git",
        "vendor",
        "*.log",
        "cache",
        "tmp",
        "temp",
    ],

    // Backup storage locations
    "storage" => [
        "local" => [
            "enabled" => true,
            "path" => BACKUP_PATH,
        ],

        // Remote storage (future implementations)
        "s3" => [
            "enabled" => false,
            "bucket" => "",
            "region" => "",
            "key" => "",
            "secret" => "",
        ],

        "ftp" => [
            "enabled" => false,
            "host" => "",
            "username" => "",
            "password" => "",
            "path" => "/backups",
        ],

        "ssh" => [
            "enabled" => false,
            "host" => "",
            "username" => "",
            "path" => "/backups",
        ],
    ],

    // Backup verification
    "verification" => [
        "enabled" => true,
        "verify_integrity" => true,
        "test_restore" => false, // Experimental - performs test restore on random backup
    ],

    // Backup naming format
    "naming" => [
        "format" => "{type}_{name}_{date}_{time}", // Available: {type}, {name}, {date}, {time}
        "date_format" => "Y-m-d",
        "time_format" => "H-i-s",
    ],

    // Database backup settings
    "database" => [
        "use_mysqldump" => true,
        "single_transaction" => true,
        "quick" => true,
        "lock_tables" => false,
        "routines" => true,
        "triggers" => true,
        "events" => true,
    ],

    // Virtual host backup settings
    "vhost" => [
        "include_files" => true,
        "include_config" => true,
        "include_ssl" => true,
        "max_file_size" => 0, // 0 = unlimited (in bytes)
    ],

    // Performance settings
    "performance" => [
        "max_concurrent_backups" => 3,
        "timeout" => 3600, // 1 hour
        "memory_limit" => "512M",
        "chunk_size" => 10485760, // 10MB chunks for large files
    ],

    // Logging
    "logging" => [
        "log_file" => LOG_PATH . "/backup.log",
        "log_level" => "info", // debug, info, warning, error
        "log_rotation" => true,
        "max_log_size" => 10485760, // 10MB
    ],

    // Security
    "security" => [
        "encrypt" => false, // Encrypt backups
        "encryption_method" => "aes-256-cbc",
        "encryption_key" => "", // Leave empty to auto-generate
        "set_permissions" => true,
        "file_permissions" => 0640,
        "dir_permissions" => 0750,
    ],

    // Pre/Post backup commands
    "hooks" => [
        "pre_backup" => [
            // Commands to run before backup starts
            // '/path/to/script.sh',
        ],
        "post_backup" => [
            // Commands to run after backup completes
            // '/path/to/notify.sh',
        ],
        "on_failure" => [
            // Commands to run if backup fails
            // '/path/to/alert.sh',
        ],
    ],

    // Cleanup settings
    "cleanup" => [
        "enabled" => true,
        "schedule" => "0 3 * * *", // Daily at 3:00 AM
        "keep_weekly" => 4,
        "keep_monthly" => 12,
    ],

    // Report generation
    "reports" => [
        "enabled" => true,
        "save_path" => BACKUP_PATH . "/reports",
        "include_statistics" => true,
        "include_details" => true,
    ],
];
