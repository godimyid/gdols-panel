# 📖 GDOLS Panel - Installation Guide

**Author:** GoDiMyID  
**Website:** [godi.my.id](https://godi.my.id)  
**Version:** 1.0.0  

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Quick Installation](#quick-installation)
3. [Detailed Installation Steps](#detailed-installation-steps)
4. [Post-Installation Configuration](#post-installation-configuration)
5. [Verification](#verification)
6. [Uninstallation](#uninstallation)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before installing GDOLS Panel, ensure you have:

### System Requirements
- **OS:** Ubuntu 20.04+ or Debian 11+
- **RAM:** 2GB minimum, 4GB+ recommended
- **Disk:** 20GB minimum free space
- **CPU:** 1+ cores
- **Access:** Root or sudo privileges

### Software Requirements
- OpenLiteSpeed (latest stable)
- PHP 8.1+ (8.3 recommended)
- MariaDB 10.6+
- Redis 6.0+ (optional)

---

## Quick Installation

### 1. One-Line OLS Installation

```bash
bash <( curl -sSk https://raw.githubusercontent.com/litespeedtech/ols1clk/master/ols1clk.sh ) \
  --pure-mariadb \
  --adminuser YOUR_ADMIN_USER \
  --adminpassword YOUR_SECURE_PASSWORD \
  --adminport 7080 \
  --lsphp 83
```

### 2. Install GDOLS Panel

```bash
# Navigate to web root
cd /var/www

# Download GDOLS Panel
git clone https://github.com/godimyid/gdols-panel.git gdolspanel
cd gdolspanel

# Set permissions
sudo chown -R nobody:nogroup .
sudo chmod -R 755 .
mkdir -p config logs sessions backups
chmod 750 config logs sessions backups
```

### 3. Run Web Installer

Open browser: `http://YOUR_SERVER_IP/gdolspanel/install.php`

Follow the 6-step wizard:
1. Welcome
2. Requirements Check
3. Database Setup
4. Configuration
5. Installation
6. Complete

### 4. Secure Installation

```bash
# Delete installer
rm /var/www/gdolspanel/install.php

# Access panel
URL: http://YOUR_SERVER_IP/gdolspanel/public/
```

---

## Detailed Installation Steps

### Step 1: Prepare Your Server

#### Update System

```bash
# Update package list
sudo apt update && sudo apt upgrade -y

# Install essential tools
sudo apt install -y curl wget git unzip vim
```

#### Set Timezone

```bash
sudo timedatectl set-timezone Asia/Jakarta
# Or your preferred timezone
```

### Step 2: Install OpenLiteSpeed

#### Using Official Installer (Recommended)

```bash
bash <( curl -sSk https://raw.githubusercontent.com/litespeedtech/ols1clk/master/ols1clk.sh ) \
  --pure-mariadb \
  --adminuser admin \
  --adminpassword YOUR_STRONG_PASSWORD_HERE \
  --adminport 7080 \
  --lsphp 83 \
  --wordpressplus 1 \
  --email YOUR_EMAIL@example.com
```

**What this installs:**
- ✅ OpenLiteSpeed web server
- ✅ PHP 8.3 (LSPHP)
- ✅ MariaDB database server
- ✅ Default WordPress instance
- ✅ WebAdmin panel (port 7080)

**After installation:**
```bash
# Note down these credentials:
# - WebAdmin URL: https://YOUR_IP:7080
# - WebAdmin Username: admin (or what you set)
# - WebAdmin Password: (what you set)
# - MySQL root password: (generated, check /root/.my.cnf)
```

#### Verify Installation

```bash
# Check OLS status
sudo /usr/local/lsws/bin/lswsctrl status

# Check PHP version
/usr/local/lsws/lsphp83/bin/php -v

# Check MariaDB
sudo systemctl status mariadb
```

### Step 3: Install Redis (Optional but Recommended)

```bash
# Install Redis
sudo apt update
sudo apt install redis-server -y

# Enable Redis
sudo systemctl enable redis

# Start Redis
sudo systemctl start redis

# Verify
sudo systemctl status redis
redis-cli ping
# Should return: PONG
```

### Step 4: Secure Redis Configuration

```bash
# Edit Redis config
sudo nano /etc/redis/redis.conf

# Find and modify these settings:

# 1. Bind to localhost only
bind 127.0.0.1 ::1

# 2. Set password (replace with strong password)
requirepass YOUR_STRONG_REDIS_PASSWORD

# 3. Disable dangerous commands
rename-command FLUSHALL ""
rename-command FLUSHDB ""
rename-command CONFIG ""

# 4. Configure memory (for 8GB RAM server)
maxmemory 2g
maxmemory-policy allkeys-lru

# 5. Set timeouts
timeout 300
tcp-keepalive 60

# Save and restart Redis
sudo systemctl restart redis

# Test with password
redis-cli -a YOUR_STRONG_REDIS_PASSWORD PING
```

### Step 5: Configure Firewall

```bash
# Allow essential ports
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS  
sudo ufw allow 7080/tcp  # OLS WebAdmin

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status verbose
```

### Step 6: Install GDOLS Panel

#### Download and Setup

```bash
# Navigate to web root
cd /var/www

# Clone repository (or upload files)
sudo git clone https://github.com/godimyid/gdols-panel.git gdolspanel

# Navigate to panel directory
cd gdolspanel

# Create necessary directories
mkdir -p config logs sessions backups

# Set ownership to OLS user
sudo chown -R nobody:nogroup .

# Set permissions
sudo chmod -R 755 .
sudo chmod 750 config logs sessions backups

# Protect sensitive directories
echo "Deny from all" | sudo tee .htaccess
sudo cp .htaccess config/.htaccess
sudo cp .htaccess logs/.htaccess
sudo cp .htaccess sessions/.htaccess
sudo cp .htaccess backups/.htaccess
```

#### Verify File Structure

```bash
# Should show:
# ├── api/
# ├── config/
# ├── logs/
# ├── public/
# ├── scripts/
# ├── sessions/
# ├── templates/
# ├── install.php
# └── README.md
ls -la /var/www/gdolspanel
```

### Step 7: Configure Virtual Host for Panel

#### Option A: Via WebAdmin (Recommended)

1. Open `https://YOUR_IP:7080`
2. Login with admin credentials
3. Go to **Configuration** → **Virtual Hosts**
4. Click **Add**
5. Configure:
   - **Virtual Host Name:** gdolspanel
   - **Virtual Host Root:** /var/www/gdolspanel
   - **Config File:** $SERVER_ROOT/conf/vhosts/gdolspanel/vhconf.conf
6. Click **Save**
7. Go to **Listeners** → **Default** → **Virtual Host Mappings**
8. Add mapping: `panel.yourdomain.com` → `gdolspanel`
9. **Save** and **Graceful Restart**

#### Option B: Via Command Line

```bash
# Use vhsetup script
/usr/local/lsws/vhsetup.sh -d panel.yourdomain.com -le admin@yourdomain.com -f --path /var/www/gdolspanel

# Restart OLS
sudo /usr/local/lsws/bin/lswsctrl restart
```

### Step 8: Run Web Installer

#### Access Installer

Open browser and navigate to:
```
http://panel.yourdomain.com/install.php
```

Or during development:
```
http://YOUR_SERVER_IP/gdolspanel/install.php
```

#### Installation Steps

**Step 1: Welcome**
- Read overview
- Click "Begin Installation"

**Step 2: Requirements Check**
- System automatically checks:
  - PHP version (8.1+ required)
  - PHP extensions (PDO, mysqli, etc.)
  - Database connection
  - File permissions
  - OpenLiteSpeed installation
  - Redis (optional)
- All checks should show ✅ (green)
- Click "Next"

**Step 3: Database Configuration**
```
Database Host: localhost
Database Port: 3306
Database Username: root
Database Password: [Your MySQL root password]
Database Name: gdolspanel
```
- Click "Test Connection"
- Should show: "Database connection successful!"
- Click "Next"

**Step 4: Panel Configuration**
```
Panel Title: GDOLS Panel
Admin Username: admin
Admin Email: admin@yourdomain.com
Admin Password: [Strong password - min 8 chars]
Confirm Password: [Same password]
Panel URL: http://panel.yourdomain.com
```
- Click "Next"

**Step 5: Ready to Install**
- Review settings
- Note: Installer will:
  - Create database tables
  - Generate configuration files
  - Create admin account
  - Set up directories
  - Configure security
- Click "Start Installation"

**Step 6: Installation Progress**
- Wait for installation to complete
- Should show progress messages
- Automatically redirects when done

**Step 7: Complete**
- ✅ Installation successful!
- Click "Launch GDOLS Panel"
- **IMPORTANT:** Delete install.php:
  ```bash
  sudo rm /var/www/gdolspanel/install.php
  ```

### Step 9: First Login

1. Navigate to: `http://panel.yourdomain.com/public/`
2. Login with:
   - Username: `admin` (or what you set)
   - Password: (what you set during installation)
3. You should see the dashboard!

---

## Post-Installation Configuration

### 1. Install Additional PHP Extensions

#### Via Command Line

```bash
# ImageMagick
sudo apt install -y lsphp83-imagick

# Internationalization
sudo apt install -y lsphp83-intl

# Redis
sudo apt install -y lsphp83-redis

# IonCube Loader
cd /tmp
wget http://downloads3.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
tar xzf ioncube_loaders_lin_x86-64.tar.gz
sudo cp ioncube/ioncube_loader_lin_8.3.so /usr/local/lsws/lsphp83/lib/php/20230831/
echo "zend_extension = /usr/local/lsws/lsphp83/lib/php/20230831/ioncube_loader_lin_8.3.so" | sudo tee -a /usr/local/lsws/lsphp83/etc/php.ini

# Restart OLS
sudo /usr/local/lsws/bin/lswsctrl restart
```

#### Via GDOLS Panel GUI

1. Login to GDOLS Panel
2. Navigate to **PHP Extensions**
3. See checklist of extensions
4. Check/uncheck to enable/disable
5. Click **Apply Changes**

### 2. Configure First Virtual Host

#### WordPress Site

1. **Virtual Hosts** → **Add New**
2. Select **WordPress**
3. Enter:
   ```
   Domain: blog.yourdomain.com
   Email: admin@yourdomain.com
   Document Root: (auto-filled)
   ```
4. Click **Install**
5. Wait for WordPress installation
6. Access: `http://blog.yourdomain.com`

#### Custom PHP Application

1. **Virtual Hosts** → **Add New**
2. Select **Custom**
3. Enter domain and email
4. Click **Create**
5. Upload files to document root via:
   - SFTP/FTP
   - Git clone
   - File manager (if available)

#### Reverse Proxy (Node.js/Python)

1. **Virtual Hosts** → **Add New**
2. Select **Proxy**
3. Enter:
   ```
   Domain: api.yourdomain.com
   Backend Host: 127.0.0.1
   Backend Port: 3001
   URI: /
   ```
4. Click **Create**
5. Start your Node.js/Python app on port 3001

### 3. Set Up SSL Certificate

#### Using Let's Encrypt (Certbot)

```bash
# Install Certbot
sudo apt install certbot

# Generate certificate
sudo certbot certonly --standalone -d panel.yourdomain.com

# Certificates saved to:
# /etc/letsencrypt/live/panel.yourdomain.com/fullchain.pem
# /etc/letsencrypt/live/panel.yourdomain.com/privkey.pem
```

#### Configure OLS to Use SSL

1. Open WebAdmin: `https://YOUR_IP:7080`
2. **Configuration** → **Virtual Hosts** → **gdolspanel**
3. **SSL** tab
4. **Private Key File:** `/etc/letsencrypt/live/panel.yourdomain.com/privkey.pem`
5. **Certificate File:** `/etc/letsencrypt/live/panel.yourdomain.com/fullchain.pem`
6. **Save** and **Graceful Restart**

### 4. Configure Automated Backups

1. **Settings** → **Backup**
2. Enable **Automated Backups**
3. Set schedule:
   - Frequency: Daily/Weekly
   - Time: 2:00 AM
4. Set retention: 30 days
5. Select backup types:
   - ✅ Database
   - ✅ Configuration files
   - ✅ Virtual hosts
6. Click **Save**

### 5. Optimize Redis

1. **Redis** → **Configuration**
2. Adjust for your RAM:
   ```
   Max Memory: 2g (for 8GB RAM)
   Policy: allkeys-lru
   Timeout: 300
   TCP Keepalive: 60
   ```
3. Click **Save & Restart**

### 6. Set Up Firewall Rules

1. **Firewall** → **Rules**
2. Add rules:
   ```
   Name: MySQL
   Action: Allow
   Port: 3306
   Protocol: TCP
   Source: 127.0.0.1
   ```

---

## Verification

### Test All Components

#### 1. Test OpenLiteSpeed

```bash
# Check status
sudo /usr/local/lsws/bin/lswsctrl status

# Test web server
curl -I http://localhost

# Should return: HTTP/1.1 200 OK
```

#### 2. Test PHP

```bash
# Check PHP version
/usr/local/lsws/lsphp83/bin/php -v

# Test PHP info page
echo "<?php phpinfo(); ?>" | sudo tee /var/www/gdolspanel/public/test.php
curl http://localhost/test.php
sudo rm /var/www/gdolspanel/public/test.php
```

#### 3. Test MariaDB

```bash
# Test connection
mysql -u root -p

# In MySQL prompt:
SHOW DATABASES;
EXIT;
```

#### 4. Test Redis

```bash
# Test connection
redis-cli -a YOUR_PASSWORD PING

# Should return: PONG
```

#### 5. Test GDOLS Panel

```bash
# Check panel access
curl -I http://panel.yourdomain.com/public/

# Should return: HTTP/1.1 200 OK
# Or redirect to login page
```

### Check Dashboard

1. Login to GDOLS Panel
2. Verify dashboard shows:
   - ✅ System resources (CPU, Memory, Disk)
   - ✅ Service status (OLS, MariaDB, Redis)
   - ✅ Recent activity
   - ✅ Quick actions

---

## Uninstallation

If you need to remove GDOLS Panel:

### 1. Stop All Services

```bash
# Stop OLS
sudo /usr/local/lsws/bin/lswsctl stop

# Stop Redis (optional)
sudo systemctl stop redis
```

### 2. Remove GDOLS Panel Files

```bash
# Backup important data first!
cd /var/www
sudo tar -czf gdolspanel_backup_$(date +%Y%m%d).tar.gz gdolspanel

# Remove panel
sudo rm -rf /var/www/gdolspanel
```

### 3. Remove Database

```bash
# Login to MySQL
mysql -u root -p

# Drop database
DROP DATABASE gdolspanel;

# Drop database user (if created)
DROP USER 'gdolspanel_user'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

### 4. Remove Virtual Host Configuration

```bash
# Remove vhost config
sudo rm -rf /usr/local/lsws/conf/vhosts/gdolspanel

# Remove from httpd_config.conf
sudo nano /usr/local/lsws/conf/httpd_config.conf
# Remove gdolspanel references

# Restart OLS
sudo /usr/local/lsws/bin/lswsctrl restart
```

---

## Troubleshooting

### Common Installation Issues

#### Issue 1: Requirements Check Fails

**Problem:** PHP version too old

```bash
# Check PHP version
php -v

# If < 8.1, install PHP 8.3
sudo apt install lsphp83
```

**Problem:** Missing PHP extensions

```bash
# Install required extensions
sudo apt install -y lsphp83-common lsphp83-curl lsphp83-mysql \
  lsphp83-json lsphp83-mbstring lsphp83-xml lsphp83-zip
```

#### Issue 2: Database Connection Failed

**Problem:** Can't connect to MySQL

```bash
# Check MariaDB status
sudo systemctl status mariadb

# If not running, start it
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Test connection
mysql -u root -p

# If password issue, reset root password
sudo mysql
ALTER USER 'root'@'localhost' IDENTIFIED BY 'NEW_PASSWORD';
FLUSH PRIVILEGES;
EXIT;
```

#### Issue 3: Permission Errors

**Problem:** Cannot write to config/logs

```bash
# Fix permissions
cd /var/www/gdolspanel
sudo chown -R nobody:nogroup .
sudo chmod -R 755 .
sudo chmod 750 config logs sessions backups
```

#### Issue 4: 403 Forbidden After Installation

**Problem:** Access forbidden

```bash
# Check .htaccess
cat /var/www/gdolspanel/public/.htaccess

# Should NOT contain "Deny from all"
# If it does, remove it:

# Check OLS error log
sudo tail -f /usr/local/lsws/logs/error.log

# Fix vhost configuration
# Via WebAdmin or edit config file
```

#### Issue 5: Installer Won't Load

**Problem:** Blank page or 500 error

```bash
# Enable PHP error display
sudo nano /usr/local/lsws/lsphp83/etc/php.ini
# Add: display_errors = On
# Add: error_reporting = E_ALL

# Restart OLS
sudo /usr/local/lsws/bin/lswsctrl restart

# Check PHP error log
sudo tail -f /usr/local/lsws/lsphp83/logs/error_log
```

### Get Help

If issues persist:

1. **Check Logs:**
   ```bash
   # GDOLS Panel logs
   tail -f /var/www/gdolspanel/logs/error.log
   
   # OLS logs
   tail -f /usr/local/lsws/logs/error.log
   
   # PHP logs
   tail -f /usr/local/lsws/lsphp83/logs/error_log
   ```

2. **Enable Debug Mode:**
   ```bash
   sudo nano /var/www/gdolspanel/config/config.local.php
   # Add: define('DEBUG_MODE', true);
   ```

3. **Visit Resources:**
   - Website: [godi.my.id](https://godi.my.id)
   - GitHub: [github.com/godimyid/gd-panel](https://github.com/godimyid/gd-panel)
   - Email: support@godi.my.id

---

## Next Steps

After successful installation:

1. ✅ **Change Admin Password**
   - Go to **Settings** → **Account**
   - Set strong password

2. ✅ **Configure SSL**
   - Install Let's Encrypt certificate
   - Force HTTPS redirect

3. ✅ **Set Up Backups**
   - Enable automated backups
   - Test restore process

4. ✅ **Create First Virtual Host**
   - WordPress or custom app
   - Configure DNS

5. ✅ **Optimize Performance**
   - Configure Redis
   - Enable OPcache
   - Tune MySQL

6. ✅ **Secure Panel**
   - Limit access by IP
   - Set up fail2ban
   - Monitor logs

---

## Support

- **Documentation:** [docs.godi.my.id](https://docs.godi.my.id)
- **Issues:** [GitHub Issues](https://github.com/godimyid/gd-panel/issues)
- **Email:** support@godi.my.id
- **Website:** [godi.my.id](https://godi.my.id)

---

**Installation Complete! Welcome to GDOLS Panel! 🎉**

*Made with ❤️ by GoDiMyID*