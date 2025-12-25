# GDOLS Panel - Installation Troubleshooting Guide

## 📋 Table of Contents

- [Common Installation Issues](#common-installation-issues)
- [Pre-Installation Checks](#pre-installation-checks)
- [Installation Errors](#installation-errors)
- [Post-Installation Issues](#post-installation-issues)
- [Service Problems](#service-problems)
- [Web Server Issues](#web-server-issues)
- [Database Issues](#database-issues)
- [Permission Issues](#permission-issues)
- [Getting Help](#getting-help)

---

## 🔍 Common Installation Issues

### Issue 1: Permission Denied

**Error Message:**
```
bash: ./install.sh: Permission denied
```

**Solution:**
```bash
# Make installer executable
chmod +x install.sh

# Run with sudo
sudo bash install.sh
```

**Prevention:**
Always run installer as root or with sudo privileges.

---

### Issue 2: Package Repository Not Found

**Error Message:**
```
E: Unable to locate package
```

**Solution:**
```bash
# Update package list
sudo apt update

# Add required repositories
sudo add-apt-repository ppa:ondrej/php -y
wget -qO - https://repo.litespeed.sh | sudo bash

# Update again
sudo apt update
```

---

### Issue 3: Existing Installation Detected

**Error Message:**
```
GDOLS Panel is already installed at /opt/gdols-panel
```

**Solution:**
```bash
# Option 1: Remove and reinstall (data loss!)
sudo systemctl stop gdols-panel
sudo rm -rf /opt/gdols-panel
sudo bash install.sh

# Option 2: Keep existing installation
# Press 'N' when prompted to reinstall
# Then manually update: sudo /opt/gdols-panel/bin/status
```

**Backup First:**
```bash
# Backup before removing
sudo cp -r /opt/gdols-panel /tmp/gdols-panel-backup
sudo cp /etc/gdols/gdols.conf /tmp/gdols.conf.backup
```

---

## ✅ Pre-Installation Checks

### Check 1: System Requirements

```bash
# Check OS version
lsb_release -a

# Check RAM
free -h

# Check disk space
df -h

# Check if root
whoami  # Should return "root"
```

**Minimum Requirements:**
- OS: Ubuntu 24.04 LTS
- RAM: 2GB (4GB recommended)
- Disk: 20GB free space
- User: Root or sudo access

---

### Check 2: Network Connectivity

```bash
# Test internet connection
ping -c 3 google.com

# Test DNS resolution
nslookup github.com

# Check firewall
sudo ufw status
```

**If Network Issues:**
```bash
# Check DNS
cat /etc/resolv.conf

# Add Google DNS if needed
echo "nameserver 8.8.8.8" | sudo tee -a /etc/resolv.conf
```

---

### Check 3: Port Availability

```bash
# Check if port 8088 is available
sudo netstat -tulpn | grep 8088

# Check if port 7080 (OLS Admin) is available
sudo netstat -tulpn | grep 7080

# Check if port 3306 (MySQL) is available
sudo netstat -tulpn | grep 3306
```

**If Ports in Use:**
```bash
# Find process using port
sudo lsof -i :8088

# Kill process if needed
sudo kill -9 <PID>
```

---

## ❌ Installation Errors

### Error 1: Dependency Installation Failed

**Error Message:**
```
Failed to install [package-name]
```

**Solution:**
```bash
# Fix broken packages
sudo dpkg --configure -a
sudo apt install -f

# Update and upgrade
sudo apt update && sudo apt upgrade -y

# Reinstall installer dependencies
sudo apt install -y curl wget git unzip software-properties-common
```

---

### Error 2: Database Creation Failed

**Error Message:**
```
ERROR 2002 (HY000): Can't connect to local MySQL server
```

**Solution:**
```bash
# Check if MariaDB is running
sudo systemctl status mysql

# Start MariaDB if not running
sudo systemctl start mysql
sudo systemctl enable mysql

# Secure MariaDB
sudo mysql_secure_installation

# Test connection
mysql -u root -p
```

**Manual Database Setup:**
```bash
# Create database manually
sudo mysql -u root -p << EOF
CREATE DATABASE IF NOT EXISTS gdols_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'gdols_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON gdols_panel.* TO 'gdols_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

---

### Error 3: Redis Installation Failed

**Error Message:**
```
Failed to start redis-server.service
```

**Solution:**
```bash
# Check Redis status
sudo systemctl status redis-server

# Check Redis configuration
sudo redis-cli ping

# If not running, start Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Test Redis
redis-cli ping  # Should return PONG
```

**Manual Redis Installation:**
```bash
# Install Redis manually
sudo apt install -y redis-server

# Configure Redis
sudo nano /etc/redis/redis.conf
# Set: bind 127.0.0.1
# Set: supervised systemd

# Restart Redis
sudo systemctl restart redis-server
```

---

### Error 4: Directory Creation Failed

**Error Message:**
```
mkdir: cannot create directory '/opt/gdols-panel': Permission denied
```

**Solution:**
```bash
# Ensure running as root
sudo su -

# Or use sudo with all commands
sudo mkdir -p /opt/gdols-panel

# Set proper permissions
sudo chmod 755 /opt/gdols-panel
```

---

### Error 5: Configuration File Not Found

**Error Message:**
```
Configuration file not found at /etc/gdols/gdols.conf
```

**Solution:**
```bash
# Create configuration directory
sudo mkdir -p /etc/gdols

# Copy default configuration
sudo cp /opt/gdols-panel/config/gdols.conf.example /etc/gdols/gdols.conf

# If example doesn't exist, create from scratch
sudo nano /etc/gdols/gdols.conf
```

**Minimum Configuration:**
```php
<?php
return [
    'app' => [
        'name' => 'GDOLS Panel',
        'version' => '1.1.0',
        'environment' => 'production',
        'debug' => false,
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'gdols_panel',
        'username' => 'gdols_user',
        'password' => 'your_secure_password',
    ],
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
    ],
];
```

---

## 🔧 Post-Installation Issues

### Issue 1: Service Won't Start

**Error Message:**
```
Job for gdols-panel.service failed
```

**Solution:**
```bash
# Check service status
sudo systemctl status gdols-panel

# View detailed logs
sudo journalctl -u gdols-panel -n 50

# Check for errors
sudo journalctl -u gdols-panel -p err

# Check application logs
sudo tail -n 50 /var/log/gdols/panel.log
```

**Common Fixes:**

1. **Permission Issues:**
```bash
sudo chmod +x /opt/gdols-panel/bin/*
sudo chown -R root:root /opt/gdols-panel
```

2. **Configuration Issues:**
```bash
# Validate PHP syntax
sudo php -l /etc/gdols/gdols.conf

# Check file exists
ls -la /etc/gdols/gdols.conf
```

3. **Missing Dependencies:**
```bash
# Check PHP
php -v

# Check MySQL
mysql --version

# Check Redis
redis-cli --version
```

---

### Issue 2: Cannot Access Web Interface

**Error Message:**
```
404 Not Found
or
Connection refused
```

**Solution:**
```bash
# Check OpenLiteSpeed status
sudo systemctl status lsws

# Check if service is running
sudo systemctl is-active lsws

# Restart OpenLiteSpeed
sudo systemctl restart lsws

# Check virtual host
ls -la /usr/local/lsws/vhosts/gdols-panel/html

# Test symlink
readlink /usr/local/lsws/vhosts/gdols-panel/html
# Should return: /opt/gdols-panel/public
```

**Fix Virtual Host:**
```bash
# Recreate symlink
sudo ln -sf /opt/gdols-panel/public /usr/local/lsws/vhosts/gdols-panel/html

# Restart OpenLiteSpeed
sudo systemctl restart lsws

# Test access
curl -I http://localhost:8088
```

---

### Issue 3: Database Connection Error

**Error Message:**
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solution:**
```bash
# Check MariaDB service
sudo systemctl status mysql

# Test database connection
mysql -u gdols_user -p gdols_panel

# Check configuration
grep -A 10 "database" /etc/gdols/gdols.conf

# Reset database user password if needed
sudo mysql -u root -p << EOF
ALTER USER 'gdols_user'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
EOF

# Update configuration
sudo nano /etc/gdols/gdols.conf
# Update database password
```

---

### Issue 4: Redis Connection Failed

**Error Message:**
```
Connection to Redis failed
```

**Solution:**
```bash
# Check Redis service
sudo systemctl status redis-server

# Test Redis connection
redis-cli ping

# Check Redis configuration
grep -A 10 "redis" /etc/gdols/gdols.conf

# Restart Redis
sudo systemctl restart redis-server

# Check if Redis is listening
sudo netstat -tulpn | grep 6379
```

**Manual Redis Test:**
```bash
# Test Redis with configuration values
redis-cli -h 127.0.0.1 -p 6379 ping

# Set test key
redis-cli SET test "hello"

# Get test key
redis-cli GET test
```

---

## 🔒 Service Problems

### Problem 1: Service Fails to Start on Boot

**Check:**
```bash
# Check if service is enabled
sudo systemctl is-enabled gdols-panel

# Check if service starts on boot
systemctl list-unit-files | grep gdols-panel
```

**Solution:**
```bash
# Enable service
sudo systemctl enable gdols-panel

# Check status
sudo systemctl status gdols-panel

# Test restart
sudo reboot
# After reboot, check: sudo systemctl status gdols-panel
```

---

### Problem 2: Service Keeps Restarting

**Check:**
```bash
# Check restart count
sudo systemctl status gdols-panel

# View logs
sudo journalctl -u gdols-panel -n 100

# Check for crash loops
sudo journalctl -u gdols-panel --since "1 hour ago"
```

**Solution:**
```bash
# Check configuration errors
sudo php -l /etc/gdols/gdols.conf

# Check for missing files
ls -la /opt/gdols-panel/bin/start

# Test manual start
sudo /opt/gdols-panel/bin/start

# Fix permissions
sudo chmod +x /opt/gdols-panel/bin/*
sudo chown root:root /opt/gdols-panel/bin/*
```

---

## 🌐 Web Server Issues

### Issue 1: OpenLiteSpeed Not Starting

**Error Message:**
```
Failed to start lsws.service
```

**Solution:**
```bash
# Check status
sudo systemctl status lsws

# Check logs
sudo tail -n 50 /usr/local/lsws/logs/error.log

# Check configuration
sudo /usr/local/lsws/bin/lswsctrl -t

# Fix configuration if errors found
sudo nano /usr/local/lsws/conf/httpd_config.conf

# Restart
sudo systemctl restart lsws
```

---

### Issue 2: Virtual Host Not Working

**Symptoms:**
- 404 errors
- Default OLS page shown
- Connection refused

**Solution:**
```bash
# Check virtual host exists
ls -la /usr/local/lsws/vhosts/gdols-panel/

# Check document root
ls -la /usr/local/lsws/vhosts/gdols-panel/html

# Verify symlink
readlink /usr/local/lsws/vhosts/gdols-panel/html

# Recreate if needed
sudo rm -f /usr/local/lsws/vhosts/gdols-panel/html
sudo ln -s /opt/gdols-panel/public /usr/local/lsws/vhosts/gdols-panel/html

# Restart OLS
sudo systemctl restart lsws
```

---

### Issue 3: Permissions Error on Web Files

**Error Message:**
```
403 Forbidden
or
Permission denied
```

**Solution:**
```bash
# Check file permissions
ls -la /opt/gdols-panel/public/

# Fix permissions
sudo chmod -R 755 /opt/gdols-panel/public/
sudo chown -R nobody:nogroup /opt/gdols-panel/public/

# For Apache/Nginx:
sudo chown -R www-data:www-data /opt/gdols-panel/public/

# Restart web server
sudo systemctl restart lsws
```

---

## 💾 Database Issues

### Issue 1: Cannot Create Database

**Error Message:**
```
ERROR 1007 (HY000): Can't create database 'gdols_panel'
```

**Solution:**
```bash
# Check if database exists
mysql -u root -p -e "SHOW DATABASES LIKE 'gdols_panel';"

# Drop if exists (BE CAREFUL - data loss!)
mysql -u root -p -e "DROP DATABASE IF EXISTS gdols_panel;"

# Create fresh database
mysql -u root -p -e "CREATE DATABASE gdols_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

### Issue 2: User Permissions Not Working

**Error Message:**
```
ERROR 1044 (42000): Access denied for user
```

**Solution:**
```bash
# Grant permissions again
mysql -u root -p << EOF
GRANT ALL PRIVILEGES ON gdols_panel.* TO 'gdols_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Test connection
mysql -u gdols_user -p gdols_panel

# Update configuration if password changed
sudo nano /etc/gdols/gdols.conf
```

---

### Issue 3: Database Import Failed

**Error Message:**
```
ERROR at line 1: SQL syntax error
```

**Solution:**
```bash
# Check SQL file syntax
mysql -u root -p < database.sql > /dev/null

# Import with error checking
mysql -u gdols_user -p gdols_panel < database.sql 2>&1 | tee import.log

# Fix common issues:
# - Check character set
# - Check for incompatible syntax
# - Split large files
```

---

## 🔐 Permission Issues

### Issue 1: Config File Not Readable

**Error Message:**
```
Failed to load configuration file
```

**Solution:**
```bash
# Check config file permissions
ls -la /etc/gdols/gdols.conf

# Should be 600 (rw-------)
sudo chmod 600 /etc/gdols/gdols.conf
sudo chown root:root /etc/gdols/gdols.conf

# Verify
ls -la /etc/gdols/gdols.conf
```

---

### Issue 2: Cannot Write to Logs

**Error Message:**
```
Failed to write to log file
```

**Solution:**
```bash
# Create log directory
sudo mkdir -p /var/log/gdols

# Set permissions
sudo chmod 750 /var/log/gdols
sudo chown root:adm /var/log/gdols

# Test write access
sudo touch /var/log/gdols/test.log
sudo rm /var/log/gdols/test.log
```

---

### Issue 3: Runtime Directory Not Writable

**Error Message:**
```
Failed to write runtime data
```

**Solution:**
```bash
# Create runtime directory
sudo mkdir -p /var/lib/gdols/runtime

# Set permissions
sudo chmod 750 /var/lib/gdols/runtime
sudo chown root:root /var/lib/gdols/runtime

# Create subdirectories
sudo mkdir -p /var/lib/gdols/runtime/sessions
sudo mkdir -p /var/lib/gdols/runtime/rate_limit
sudo mkdir -p /var/lib/gdols/runtime/brute_force

# Set permissions for subdirectories
sudo chmod 750 /var/lib/gdols/runtime/*
```

---

## 🔍 Debug Mode

### Enable Debug Mode

**Edit Configuration:**
```bash
sudo nano /etc/gdols/gdols.conf
```

**Change:**
```php
'app' => [
    'environment' => 'development',
    'debug' => true,
],
```

**Restart Service:**
```bash
sudo systemctl restart gdols-panel
```

**View Debug Output:**
```bash
sudo journalctl -u gdols-panel -f
sudo tail -f /var/log/gdols/panel.log
```

---

## 📊 Diagnostic Commands

### Full System Check

```bash
# Save this as diagnose.sh
#!/bin/bash

echo "=== GDOLS Panel Diagnostics ==="
echo ""

echo "1. System Info:"
echo "OS: $(lsb_release -d | cut -f2)"
echo "Kernel: $(uname -r)"
echo "Uptime: $(uptime -p)"
echo ""

echo "2. Resources:"
echo "RAM: $(free -h | grep Mem | awk '{print $2}')"
echo "Disk: $(df -h / | tail -1 | awk '{print $4}') available"
echo ""

echo "3. Service Status:"
systemctl is-active gdols-panel && echo "✓ GDOLS Panel: Running" || echo "✗ GDOLS Panel: Not running"
systemctl is-active lsws && echo "✓ OpenLiteSpeed: Running" || echo "✗ OpenLiteSpeed: Not running"
systemctl is-active mysql && echo "✓ MariaDB: Running" || echo "✗ MariaDB: Not running"
systemctl is-active redis-server && echo "✓ Redis: Running" || echo "✗ Redis: Not running"
echo ""

echo "4. File Checks:"
test -f /etc/gdols/gdols.conf && echo "✓ Config exists" || echo "✗ Config missing"
test -f /opt/gdols-panel/VERSION && echo "✓ VERSION exists" || echo "✗ VERSION missing"
test -L /usr/local/lsws/vhosts/gdols-panel/html && echo "✓ Symlink exists" || echo "✗ Symlink missing"
echo ""

echo "5. Port Checks:"
netstat -tulpn | grep 8088 >/dev/null && echo "✓ Port 8088: Listening" || echo "✗ Port 8088: Not listening"
netstat -tulpn | grep 3306 >/dev/null && echo "✓ Port 3306: Listening" || echo "✗ Port 3306: Not listening"
netstat -tulpn | grep 6379 >/dev/null && echo "✓ Port 6379: Listening" || echo "✗ Port 6379: Not listening"
echo ""

echo "6. Recent Errors:"
echo "=== GDOLS Panel logs ==="
tail -n 5 /var/log/gdols/panel.log 2>/dev/null || echo "No logs found"
echo ""
echo "=== Systemd logs ==="
journalctl -u gdols-panel -n 5 --no-pager 2>/dev/null || echo "No systemd logs"
```

**Run Diagnostics:**
```bash
# Create and run diagnostics
sudo nano /tmp/diagnose.sh
# Paste the script above
chmod +x /tmp/diagnose.sh
sudo bash /tmp/diagnose.sh
```

---

## 🆘 Getting Help

### Collect Information Before Asking for Help

```bash
# Create info bundle
mkdir -p /tmp/gdols-debug

# Export system info
lsb_release -a > /tmp/gdols-debug/system.txt
free -h >> /tmp/gdols-debug/system.txt
df -h >> /tmp/gdols-debug/system.txt

# Export service status
sudo systemctl status gdols-panel > /tmp/gdols-debug/service.txt

# Export logs
sudo journalctl -u gdols-panel -n 100 > /tmp/gdols-debug/journal.txt
tail -n 100 /var/log/gdols/panel.log > /tmp/gdols-debug/app.log 2>/dev/null

# Export configuration (remove passwords!)
sudo grep -v "password" /etc/gdols/gdols.conf > /tmp/gdols-debug/config.txt

# Create archive
tar -czf /tmp/gdols-debug-$(date +%Y%m%d).tar.gz /tmp/gdols-debug/

# Output location
echo "Debug info saved to: /tmp/gdols-debug-$(date +%Y%m%d).tar.gz"
```

---

### Where to Get Help

1. **Documentation**
   - [README.md](README.md)
   - [INSTALL.md](INSTALL.md)
   - [FHS_MIGRATION.md](FHS_MIGRATION.md)

2. **Community**
   - [GitHub Issues](https://github.com/godimyid/gdols-panel/issues)
   - [GitHub Discussions](https://github.com/godimyid/gdols-panel/discussions)

3. **Support**
   - [Support Page](https://ko-fi.com/godimyid/goal?g=0)
   - [Website](https://godi.my.id)
   - [Contact](https://godi.my.id/contact)

---

### Report Issues

When reporting issues, include:

1. **System Information:**
   - OS version: `lsb_release -a`
   - PHP version: `php -v`
   - Panel version: `cat /opt/gdols-panel/VERSION`

2. **Error Messages:**
   - Full error message
   - When it occurred
   - What you were doing

3. **Logs:**
   - Service logs: `sudo journalctl -u gdols-panel -n 50`
   - Application logs: `sudo tail -n 50 /var/log/gdols/panel.log`

4. **Steps to Reproduce:**
   - Exact steps taken
   - Expected behavior
   - Actual behavior

---

## 🔄 Common Fixes Summary

### Quick Fix Commands

```bash
# Fix all common issues at once
sudo systemctl stop gdols-panel
sudo chmod +x /opt/gdols-panel/bin/*
sudo chmod 600 /etc/gdols/gdols.conf
sudo mkdir -p /var/log/gdols /var/lib/gdols/runtime
sudo chmod 750 /var/log/gdols /var/lib/gdols/runtime
sudo systemctl daemon-reload
sudo systemctl start gdols-panel
sudo systemctl status gdols-panel
```

---

## 📝 Version-Specific Notes

### Version 1.1.0 Specific Issues

**Migration from 1.0.0:**
If upgrading from v1.0.0, see [FHS_MIGRATION.md](FHS_MIGRATION.md)

**Common 1.1.0 Issues:**

1. **Path Changes:**
   - Old: `/home/ubuntu/gdols-panel/`
   - New: `/opt/gdols-panel/`
   - Update all scripts and references

2. **Static Files (404 Errors):**
   - **Symptom**: CSS and JS files return 404 errors despite files existing
   - **Root Cause**: Incorrect file ownership for OpenLiteSpeed
   - **Quick Fix**:
   ```bash
   # Fix OpenLiteSpeed permissions
   sudo chown -R nobody:nogroup /opt/gdols-panel/public
   sudo chown -R nobody:nogroup /usr/local/lsws/vhosts/gdols-panel
   sudo chmod -R 755 /opt/gdols-panel/public
   
   # Fix symlink
   sudo rm -f /usr/local/lsws/vhosts/gdols-panel/html
   sudo ln -sf /opt/gdols-panel/public /usr/local/lsws/vhosts/gdols-panel/html
   
   # Restart OpenLiteSpeed
   sudo systemctl restart lsws
   ```
   
   - **Verify Fix**:
   ```bash
   # Check files exist
   ls -lh /opt/gdols-panel/public/assets/css/style.css
   ls -lh /opt/gdols-panel/public/assets/js/app.js
   
   # Check ownership
   ls -la /opt/gdols-panel/public/assets/
   
   # Test access
   curl -I http://YOUR_IP:8088/assets/css/style.css
   ```

2. **Config Location:**
   - Old: `/home/ubuntu/gdols-panel/config/`
   - New: `/etc/gdols/gdols.conf`
   - Move or recreate configuration

3. **Service Name:**
   - Old: Manual start/stop
   - New: Systemd service `gdols-panel`
   - Use: `sudo systemctl [start|stop|restart] gdols-panel`

---

**Version:** 1.1.0  
**Last Updated:** December 25, 2025  
**Maintained By:** GDOLS Panel Team