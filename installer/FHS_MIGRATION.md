# GDOLS Panel - FHS Migration Guide

## 📋 Overview

This guide explains how to migrate GDOLS Panel from the old directory structure to the new **FHS-compliant** (Filesystem Hierarchy Standard) structure optimized for Ubuntu 24.04 LTS and production deployments.

---

## 🔄 Migration Summary

### Old Structure (Non-FHS)
```
/home/ubuntu/gdols-panel/     # ❌ Not portable
├── api/                      # ❌ User home directory
├── public/
├── config/
└── scripts/
```

### New Structure (FHS-Compliant)
```
/opt/gdols-panel/             # ✅ Standard Linux location
├── app/                      # ✅ Source code isolated
├── bin/                      # ✅ Executable scripts
├── config/                   # ✅ Default configs
├── storage/                  # ✅ Application data
├── public/                   # ✅ Web root
└── scripts/                  # ✅ Automation scripts

/etc/gdols/                   # ✅ System configuration
├── gdols.conf               # ✅ Secure, not in repo

/var/log/gdols/              # ✅ Centralized logging
/var/lib/gdols/              # ✅ Runtime data & backups
```

---

## 🎯 Why Migrate to FHS?

### Benefits

1. **Professional Standards**
   - Follows Linux Filesystem Hierarchy Standard
   - Recognizable by Linux administrators
   - Industry best practices

2. **Security**
   - Configuration isolated from application code
   - Proper file permissions (600 for configs)
   - Secrets never in repository

3. **Maintainability**
   - Easy to backup specific components
   - Simple to update without touching configs
   - Clear separation of concerns

4. **Portability**
   - Works across different distributions
   - Easy deployment automation
   - Container-friendly

5. **Production Ready**
   - Suitable for SaaS deployments
   - Multi-server support
   - CI/CD friendly

---

## 📦 Pre-Migration Checklist

### 1. Backup Current Installation

```bash
# Create backup directory
sudo mkdir -p /var/backups/gdols-panel

# Backup current installation
sudo tar -czf /var/backups/gdols-panel/pre-migration-$(date +%Y%m%d_%H%M%S).tar.gz \
    /home/ubuntu/gdols-panel \
    /etc/gdols 2>/dev/null || true

# Verify backup
ls -lh /var/backups/gdols-panel/
```

### 2. Document Current Configuration

```bash
# Export database credentials
sudo cat /home/ubuntu/gdols-panel/config/database.php

# Export SSL certificate paths (if any)
sudo cat /home/ubuntu/gdols-panel/config/config.php | grep -i ssl

# Document custom settings
sudo cp /home/ubuntu/gdols-panel/config/*.php /tmp/config-backup/
```

### 3. Stop Services

```bash
# Stop any running services
sudo systemctl stop gdols-panel 2>/dev/null || true
sudo /home/ubuntu/gdols-panel/scripts/stop 2>/dev/null || true
```

---

## 🚀 Step-by-Step Migration

### Step 1: Install New Structure

```bash
# Download installer
cd /tmp
wget https://github.com/godimyid/gdols-panel/raw/main/installer/install.sh

# Run installer (choose "keep existing" when prompted)
sudo bash install.sh
```

### Step 2: Migrate Configuration

```bash
# Backup existing configuration
sudo cp /etc/gdols/gdols.conf /etc/gdols/gdols.conf.new

# Extract database credentials from old config
OLD_DB_HOST=$(grep -oP "'host'\s*=>\s*'\K[^']+" /home/ubuntu/gdols-panel/config/database.php 2>/dev/null || echo "localhost")
OLD_DB_NAME=$(grep -oP "'database'\s*=>\s*'\K[^']+" /home/ubuntu/gdols-panel/config/database.php 2>/dev/null || echo "gdols_panel")
OLD_DB_USER=$(grep -oP "'username'\s*=>\s*'\K[^']+" /home/ubuntu/gdols-panel/config/database.php 2>/dev/null || echo "gdols_user")
OLD_DB_PASS=$(grep -oP "'password'\s*=>\s*'\K[^']+" /home/ubuntu/gdols-panel/config/database.php 2>/dev/null || echo "")

# Update new configuration with old credentials
sudo sed -i "s/'host' => 'localhost'/'host' => '$OLD_DB_HOST'/" /etc/gdols/gdols.conf
sudo sed -i "s/'database' => 'gdols_panel'/'database' => '$OLD_DB_NAME'/" /etc/gdols/gdols.conf
sudo sed -i "s/'username' => 'gdols_user'/'username' => '$OLD_DB_USER'/" /etc/gdols/gdols.conf
if [ ! -z "$OLD_DB_PASS" ]; then
    sudo sed -i "s/'password' => 'CHANGE_THIS_PASSWORD'/'password' => '$OLD_DB_PASS'/" /etc/gdols/gdols.conf
fi

# Verify configuration
sudo cat /etc/gdols/gdols.conf | grep -A 5 database
```

### Step 3: Migrate Data

```bash
# Migrate storage data
if [ -d "/home/ubuntu/gdols-panel/storage" ]; then
    sudo cp -r /home/ubuntu/gdols-panel/storage/* /opt/gdols-panel/storage/
fi

# Migrate uploads
if [ -d "/home/ubuntu/gdols-panel/public/uploads" ]; then
    sudo cp -r /home/ubuntu/gdols-panel/public/uploads/* /opt/gdols-panel/storage/uploads/
fi

# Migrate custom scripts
if [ -d "/home/ubuntu/gdols-panel/scripts" ]; then
    # Check for custom scripts not in new version
    for script in /home/ubuntu/gdols-panel/scripts/*.sh; do
        script_name=$(basename "$script")
        if [ ! -f "/opt/gdols-panel/scripts/$script_name" ]; then
            sudo cp "$script" /opt/gdols-panel/scripts/
        fi
    done
fi
```

### Step 4: Migrate Logs

```bash
# Archive old logs
if [ -d "/home/ubuntu/gdols-panel/logs" ]; then
    sudo tar -czf /var/log/gdols/old-logs-$(date +%Y%m%d).tar.gz \
        -C /home/ubuntu/gdols-panel logs
    
    echo "Old logs archived to: /var/log/gdols/old-logs-$(date +%Y%m%d).tar.gz"
fi
```

### Step 5: Update Web Server Configuration

```bash
# If using Apache
if [ -f "/etc/apache2/sites-available/gdols-panel.conf" ]; then
    sudo sed -i 's|/home/ubuntu/gdols-panel/public|/opt/gdols-panel/public|g' \
        /etc/apache2/sites-available/gdols-panel.conf
    
    sudo systemctl reload apache2
fi

# If using Nginx
if [ -f "/etc/nginx/sites-available/gdols-panel" ]; then
    sudo sed -i 's|/home/ubuntu/gdols-panel/public|/opt/gdols-panel/public|g' \
        /etc/nginx/sites-available/gdols-panel
    
    sudo systemctl reload nginx
fi

# If using OpenLiteSpeed
sudo ln -sf /opt/gdols-panel/public /usr/local/lsws/vhosts/gdols-panel/html
sudo systemctl restart lsws
```

### Step 6: Update Systemd Service

```bash
# If old service exists
if [ -f "/etc/systemd/system/gdols-panel.service" ]; then
    # Disable old service
    sudo systemctl disable gdols-panel
    
    # Update service file
    sudo sed -i 's|/home/ubuntu/gdols-panel|/opt/gdols-panel|g' \
        /etc/systemd/system/gdols-panel.service
    
    # Reload systemd
    sudo systemctl daemon-reload
    
    # Enable new service
    sudo systemctl enable gdols-panel
fi
```

### Step 7: Update Cron Jobs

```bash
# Export current crontab
sudo crontab -l > /tmp/current-cron 2>/dev/null || true

# Update paths in cron jobs
sed -i 's|/home/ubuntu/gdols-panel|/opt/gdols-panel|g' /tmp/current-cron

# Install updated crontab
sudo crontab /tmp/current-cron

# Verify
sudo crontab -l
```

### Step 8: Start New Service

```bash
# Start the service
sudo systemctl start gdols-panel

# Check status
sudo systemctl status gdols-panel

# View logs
sudo tail -f /var/log/gdols/panel.log
```

---

## ✅ Post-Migration Verification

### 1. Service Status

```bash
# Check if service is running
sudo systemctl status gdols-panel

# Run detailed status check
sudo /opt/gdols-panel/bin/status --verbose
```

### 2. Web Interface Access

```bash
# Test web interface
curl -I http://localhost:8088

# Or with domain
curl -I http://your-domain.com
```

### 3. Database Connectivity

```bash
# Test database connection
php -r "
\$config = include '/etc/gdols/gdols.conf';
\$db = \$config['database'];
\$mysqli = new mysqli(\$db['host'], \$db['username'], \$db['password'], \$db['database']);
if (\$mysqli->connect_error) {
    echo 'Connection failed: ' . \$mysqli->connect_error . PHP_EOL;
    exit(1);
}
echo 'Database connection successful!' . PHP_EOL;
\$mysqli->close();
"
```

### 4. Redis Connectivity

```bash
# Test Redis connection
redis-cli ping

# Check if Redis caching works
redis-cli KEYS "gdols:*"
```

### 5. File Permissions

```bash
# Verify critical permissions
echo "Checking permissions..."
ls -la /etc/gdols/gdols.conf           # Should be 600
ls -la /opt/gdols-panel/bin/           # Scripts should be executable
ls -la /usr/local/lsws/vhosts/gdols-panel/html  # Should be symlink
```

---

## 🔧 Troubleshooting Migration Issues

### Issue 1: Service Won't Start

**Problem**: Service fails to start after migration

**Solution**:
```bash
# Check error logs
sudo journalctl -u gdols-panel -n 50

# Check permissions
sudo ls -la /opt/gdols-panel/bin/
sudo chmod +x /opt/gdols-panel/bin/*

# Verify configuration
sudo php -l /etc/gdols/gdols.conf
```

### Issue 2: Database Connection Failed

**Problem**: Cannot connect to database with new configuration

**Solution**:
```bash
# Verify database credentials
mysql -u gdols_user -p gdols_panel

# Update configuration if needed
sudo nano /etc/gdols/gdols.conf

# Test connection again
mysql -u $(grep "'username'" /etc/gdols/gdols.conf | grep -oP "' => '\K[^']+") \
    -p$(grep "'password'" /etc/gdols/gdols.conf | grep -oP "' => '\K[^']+") \
    $(grep "'database'" /etc/gdols/gdols.conf | grep -oP "' => '\K[^']+")
```

### Issue 3: Web Server 404 Errors

**Problem**: Web interface shows 404 errors

**Solution**:
```bash
# Check web server configuration
sudo apache2ctl -S      # For Apache
sudo nginx -t           # For Nginx

# Verify symlink exists
ls -la /usr/local/lsws/vhosts/gdols-panel/html

# Check document root
sudo grep DocumentRoot /etc/apache2/sites-available/gdols-panel.conf
```

### Issue 4: Permission Denied Errors

**Problem**: Application cannot write to storage directories

**Solution**:
```bash
# Fix storage permissions
sudo chmod -R 750 /opt/gdols-panel/storage
sudo chown -R www-data:www-data /opt/gdols-panel/storage  # For Apache/Nginx
# OR
sudo chown -R nobody:nogroup /opt/gdols-panel/storage    # For OpenLiteSpeed

# Fix log permissions
sudo chmod 750 /var/log/gdols
sudo chown -R root:adm /var/log/gdols
```

---

## 🔄 Rollback Procedure

If you need to rollback to the old structure:

```bash
# Stop new service
sudo systemctl stop gdols-panel

# Backup new installation
sudo mv /opt/gdols-panel /opt/gdols-panel-new
sudo mv /etc/gdols /etc/gdols-new

# Restore old installation
sudo tar -xzf /var/backups/gdols-panel/pre-migration-*.tar.gz -C /

# Restart old service
sudo systemctl start gdols-panel

# Verify rollback
curl -I http://localhost:8088
```

---

## 📊 Migration Checklist

### Before Migration
- [ ] Backup current installation
- [ ] Document all configuration settings
- [ ] Export database credentials
- [ ] Note SSL certificate locations
- [ ] Stop all running services

### During Migration
- [ ] Install new FHS structure
- [ ] Migrate configuration files
- [ ] Update database credentials
- [ ] Migrate storage and uploads
- [ ] Archive old logs
- [ ] Update web server configuration
- [ ] Update systemd service
- [ ] Update cron jobs

### After Migration
- [ ] Start new service
- [ ] Verify service status
- [ ] Test web interface
- [ ] Test database connectivity
- [ ] Test Redis connectivity
- [ ] Check file permissions
- [ ] Monitor logs for errors
- [ ] Test all functionality

### Cleanup (After Successful Migration)
- [ ] Remove old installation directory (after 1 week)
- [ ] Update documentation
- [ ] Update deployment scripts
- [ ] Notify team of new paths
- [ ] Update monitoring systems

---

## 🎯 Best Practices After Migration

### 1. Documentation

Update your internal documentation to reflect new paths:
```bash
# Create reference document
cat > /etc/gdols/PATHS.md << 'EOF'
# GDOLS Panel - Installation Paths

Application: /opt/gdols-panel
Configuration: /etc/gdols/gdols.conf
Logs: /var/log/gdols/
Runtime Data: /var/lib/gdols/
Web Root: /opt/gdols-panel/public
Service: gdols-panel
EOF
```

### 2. Monitoring Updates

Update monitoring systems to use new paths:
```bash
# Log monitoring
sudo tail -f /var/log/gdols/panel.log

# Service monitoring
sudo systemctl status gdols-panel
```

### 3. Backup Automation

Update backup scripts:
```bash
# Update backup paths
sudo nano /opt/gdols-panel/scripts/backup-cron.sh

# Ensure these paths are backed up:
# - /opt/gdols-panel
# - /etc/gdols
# - /usr/local/lsws/vhosts/gdols-panel
```

### 4. Deployment Automation

Update CI/CD pipelines:
```yaml
# Example GitLab CI/CD update
deploy:
  script:
    - sudo systemctl stop gdols-panel
    - sudo cp -r * /opt/gdols-panel/app/
    - sudo systemctl start gdols-panel
```

---

## 📞 Support

If you encounter issues during migration:

1. **Check Logs**: `/var/log/gdols/panel.log`
2. **Run Status**: `/opt/gdols-panel/bin/status --verbose`
3. **GitHub Issues**: [https://github.com/godimyid/gdols-panel/issues](https://github.com/godimyid/gdols-panel/issues)
4. **Documentation**: [INSTALL.md](INSTALL.md)

---

## 📝 Version History

- **v1.1.0** (2025-12-25): FHS-compliant installation structure
- **v1.0.0** (2025-12-25): Initial release

---

**Last Updated**: December 25, 2025  
**Maintained By**: GDOLS Panel Team