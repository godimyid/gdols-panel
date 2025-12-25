# GDOLS Panel - Automated Backup System

## 📋 Overview

GDOLS Panel includes a comprehensive automated backup system that can backup databases, virtual hosts, and system configurations on a scheduled basis. This guide will help you set up and configure automated backups.

## ✨ Features

- **Automated Scheduled Backups**: Set up cron jobs for automatic backups
- **Multiple Backup Types**: Databases, virtual hosts, and configurations
- **Retention Policies**: Automatic cleanup of old backups
- **Compression**: Built-in gzip compression to save space
- **Encryption Support**: Optional encryption for sensitive backups
- **Remote Storage**: Integration with S3, FTP, and SSH for off-site backups
- **Email Notifications**: Get notified about backup success/failure
- **Backup Reports**: Detailed reports about backup operations

## 🚀 Installation

### Step 1: Configure Backup Settings

Edit the backup configuration file:

```bash
nano /var/www/gdolspanel/config/backup.php
```

Key configuration options:

```php
// Enable/disable automated backups
'enabled' => true,

// Backup schedule (cron format)
// Default: Daily at 2:00 AM
'schedule' => '0 2 * * *',

// Retention policy - how many days to keep backups
'retention_days' => 7,

// Databases to backup automatically
'databases' => [
    'my_database',
    'wordpress_db',
],

// Virtual hosts to backup automatically
'vhosts' => [
    'example.com',
    'mysite.com',
],
```

### Step 2: Make Backup Script Executable

```bash
chmod +x /var/www/gdolspanel/scripts/backup-cron.sh
```

### Step 3: Test the Backup Script

Run the backup script manually to test:

```bash
/var/www/gdolspanel/scripts/backup-cron.sh
```

Check the log output for any errors:

```bash
tail -f /var/www/gdolspanel/logs/backup.log
```

### Step 4: Set Up Cron Job

#### Option A: Using GDOLS Panel Web Interface (Recommended)

1. Access your GDOLS Panel dashboard
2. Navigate to **Settings** → **Automation**
3. Enable **Automated Backups**
4. Set your preferred schedule
5. Click **Save**

#### Option B: Manual Cron Setup

Edit the crontab:

```bash
crontab -e
```

Add the following line for daily backups at 2:00 AM:

```bash
0 2 * * * /var/www/gdolspanel/scripts/backup-cron.sh >> /var/www/gdolspanel/logs/cron.log 2>&1
```

Other common schedules:

```bash
# Every 6 hours
0 */6 * * * /var/www/gdolspanel/scripts/backup-cron.sh

# Every Sunday at 3:00 AM
0 3 * * 0 /var/www/gdolspanel/scripts/backup-cron.sh

# Every 1st of the month at 4:00 AM
0 4 1 * * /var/www/gdolspanel/scripts/backup-cron.sh
```

## 📊 Monitoring Backups

### View Backup Statistics via API

```bash
curl -X GET http://your-server/gdolspanel/api/endpoints/backup.php?action=stats
```

### View Backup Logs

```bash
# Real-time log monitoring
tail -f /var/www/gdolspanel/logs/backup.log

# View last 50 lines
tail -n 50 /var/www/gdolspanel/logs/backup.log

# Search for errors
grep ERROR /var/www/gdolspanel/logs/backup.log
```

### List Available Backups

```bash
# Database backups
ls -lh /var/www/gdolspanel/backups/database/

# Virtual host backups
ls -lh /var/www/gdolspanel/backups/vhosts/

# Configuration backups
ls -lh /var/www/gdolspanel/backups/config/
```

## 🔧 Configuration Options

### Cron Schedule Examples

| Schedule Pattern | Description |
|-----------------|-------------|
| `0 2 * * *` | Daily at 2:00 AM |
| `0 */6 * * *` | Every 6 hours |
| `0 2 * * 0` | Weekly on Sunday at 2:00 AM |
| `0 2 1 * *` | Monthly on the 1st at 2:00 AM |
| `*/30 * * * *` | Every 30 minutes |

### Retention Policy

Configure how long to keep backups:

```php
'retention_days' => 7,  // Keep backups for 7 days
```

You can also set up different retention periods:

```php
'cleanup' => [
    'enabled' => true,
    'keep_weekly' => 4,   // Keep 4 weekly backups
    'keep_monthly' => 12, // Keep 12 monthly backups
],
```

### Compression Settings

Enable/disable compression:

```php
'compress' => true,  // Enable gzip compression
```

### Encryption

Enable backup encryption:

```php
'security' => [
    'encrypt' => true,
    'encryption_method' => 'aes-256-cbc',
    'encryption_key' => 'your-secret-key',
],
```

## 🌐 Remote Storage Configuration

### Amazon S3

```php
's3' => [
    'enabled' => true,
    'bucket' => 'my-backup-bucket',
    'region' => 'us-east-1',
    'key' => 'your-access-key',
    'secret' => 'your-secret-key',
],
```

### FTP

```php
'ftp' => [
    'enabled' => true,
    'host' => 'ftp.example.com',
    'username' => 'backup-user',
    'password' => 'your-password',
    'path' => '/backups',
],
```

### SSH/SCP

```php
'ssh' => [
    'enabled' => true,
    'host' => 'backup.example.com',
    'username' => 'backup-user',
    'path' => '/backups',
],
```

## 📧 Email Notifications

Configure email notifications:

```php
'notifications' => [
    'enabled' => true,
    'email' => 'admin@example.com',
    'on_success' => true,  // Notify on successful backups
    'on_failure' => true,  // Notify on failed backups
],
```

## 🛠️ Manual Backup Operations

### Backup All Databases

```bash
/var/www/gdolspanel/scripts/backup-cron.sh
```

### Backup Specific Database

Use the GDOLS Panel web interface or API:

```bash
curl -X POST http://your-server/gdolspanel/api/endpoints/database.php?action=backup \
  -H "Content-Type: application/json" \
  -d '{"database":"my_database"}'
```

### Backup Virtual Host

```bash
curl -X POST http://your-server/gdolspanel/api/endpoints/vhost.php?action=backup \
  -H "Content-Type: application/json" \
  -d '{"domain":"example.com"}'
```

### Backup Configuration

```bash
curl -X POST http://your-server/gdolspanel/api/endpoints/system.php?action=backup_config \
  -H "Content-Type: application/json"
```

## 🔄 Restore from Backup

### Restore Database

```bash
# Using API
curl -X POST http://your-server/gdolspanel/api/endpoints/database.php?action=restore \
  -H "Content-Type: application/json" \
  -d '{"database":"my_database","backup_file":"my_database_2024-12-19.sql.gz"}'

# Or manually
gunzip < /var/www/gdolspanel/backups/database/my_database_2024-12-19.sql.gz | mysql -u root -p my_database
```

### Restore Virtual Host

1. Extract the backup archive
2. Copy files to the virtual host directory
3. Restore the configuration
4. Reload OpenLiteSpeed

## 🔍 Troubleshooting

### Backup Script Not Running

Check if cron is running:

```bash
# Check if cron service is active
systemctl status cron

# View cron logs
grep CRON /var/log/syslog
```

### Permission Issues

Ensure proper permissions:

```bash
chmod +x /var/www/gdolspanel/scripts/backup-cron.sh
chown www-data:www-data /var/www/gdolspanel/backups -R
chmod 755 /var/www/gdolspanel/backups
```

### Disk Space Issues

Check available disk space:

```bash
df -h
```

If disk space is low, consider:
- Reducing retention days
- Enabling compression
- Moving old backups to remote storage

### Database Backup Fails

Check MariaDB credentials:

```bash
cat /var/www/gdolspanel/config/database.php
```

Test database connection:

```bash
mysql -u root -p -e "SHOW DATABASES;"
```

## 📈 Best Practices

1. **Test Your Backups**: Regularly test restoring from backups
2. **Off-Site Storage**: Use remote storage for disaster recovery
3. **Encryption**: Enable encryption for sensitive data
4. **Monitoring**: Set up monitoring for backup failures
5. **Retention Policy**: Balance between retention and disk space
6. **Schedule**: Choose off-peak hours for backups
7. **Documentation**: Document your backup and restore procedures

## 🔒 Security Considerations

- Protect backup directories with appropriate permissions
- Use encryption for sensitive backups
- Store backup credentials securely
- Restrict access to backup files
- Regularly update encryption keys
- Monitor backup log access

## 📞 Support

If you encounter any issues:

- **Documentation**: [docs.godi.my.id](https://docs.godi.my.id)
- **Email**: support@godi.my.id
- **GitHub**: [github.com/godimyid/gd-panel](https://github.com/godimyid/gd-panel)

---

**Author**: GoDiMyID | **Website**: [godi.my.id](https://godi.my.id)

*Last Updated: December 19, 2024*