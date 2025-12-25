# GDOLS Panel - Installation Guide

## 📋 Table of Contents

- [Overview](#overview)
- [System Requirements](#system-requirements)
- [Installation Methods](#installation-methods)
- [Directory Structure](#directory-structure)
- [Step-by-Step Installation](#step-by-step-installation)
- [Post-Installation Configuration](#post-installation-configuration)
- [Service Management](#service-management)
- [Web Server Configuration](#web-server-configuration)
- [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

GDOLS Panel follows the **Filesystem Hierarchy Standard (FHS)** for Linux systems, ensuring a professional, secure, and maintainable installation on Ubuntu 24.04 LTS.

### Key Features

- ✅ **FHS-Compliant**: Follows Linux standards for third-party applications
- ✅ **Systemd Service**: Native service management with auto-restart
- ✅ **Secure by Default**: Proper permissions and isolated configuration
- ✅ **Production Ready**: Optimized for SaaS and multi-server deployments
- ✅ **Easy Maintenance**: Centralized logs, configs, and runtime data

---

## 💻 System Requirements

### Minimum Requirements

- **OS**: Ubuntu 24.04 LTS (or compatible Debian-based system)
- **RAM**: 2GB (Recommended: 4GB)
- **Disk Space**: 20GB free space
- **CPU**: 1 Core (Recommended: 2+ Cores)

### Software Dependencies

- **Web Server**: OpenLiteSpeed 1.7+
- **PHP**: 8.3 or higher
- **Database**: MariaDB 10.x or MySQL 8.x
- **Cache**: Redis 7.x
- **Other**: Git, curl, wget, unzip

---

## 📦 Installation Methods

### Method 1: Automated Installer (Recommended)

The easiest way to install GDOLS Panel is using our automated installer script.

```bash
# Download and run installer
wget https://github.com/godimyid/gdols-panel/raw/main/installer/install.sh
sudo bash install.sh
```

### Method 2: Manual Installation

For advanced users who want full control over the installation process.

### Method 3: Docker Installation

For containerized deployments (see DOCKER.md).

---

## 📁 Directory Structure

GDOLS Panel follows FHS standards with the following structure:

```
/opt/gdols-panel/              # Core application
├── app/                       # Source code (API, templates)
├── bin/                       # Executable scripts (start, stop, restart)
├── config/                    # Default configuration templates
├── storage/                   # Application data
│   ├── cache/                # Cache files
│   ├── sessions/             # Session files
│   └── uploads/              # User uploads
├── public/                    # Web-accessible files
├── logs/                      # Application logs (internal)
├── scripts/                   # Automation scripts
└── VERSION                    # Version information

/etc/gdols/                    # Configuration (NOT in repo)
└── gdols.conf                # Main configuration file (chmod 600)

/var/log/gdols/               # System logs
├── panel.log                 # Main application log
└── panel-error.log           # Error log

/var/lib/gdols/               # Runtime data
├── runtime/                  # Runtime files
│   ├── sessions/            # PHP sessions
│   ├── rate_limit/          # Rate limiting data
│   ├── brute_force/         # Brute force protection
│   └── monitoring/          # Monitoring data
└── backups/                  # Automated backups
    └── database/             # Database backups

/usr/local/lsws/vhosts/gdols-panel/  # OpenLiteSpeed vhost
└── html -> /opt/gdols-panel/public  # Symlink to public/

/etc/systemd/system/          # Systemd service
└── gdols-panel.service       # Service definition
```

---

## 🚀 Step-by-Step Installation

### 1. System Preparation

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install base dependencies
sudo apt install -y curl wget git unzip software-properties-common \
    apt-transport-https ca-certificates gnupg lsb-release bc
```

### 2. Install OpenLiteSpeed

```bash
# Add OpenLiteSpeed repository
wget -qO - https://repo.litespeed.sh | sudo bash

# Install OpenLiteSpeed
sudo apt install -y openlitespeed

# Start and enable service
sudo systemctl enable lsws
sudo systemctl start lsws
```

### 3. Install PHP 8.3

```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3 and extensions
sudo apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-redis \
    php8.3-curl php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip \
    php8.3-bcmath php8.3-intl php8.3-json
```

### 4. Install MariaDB

```bash
# Install MariaDB
sudo apt install -y mariadb-server mariadb-client

# Secure installation
sudo mysql_secure_installation

# Start service
sudo systemctl enable mysql
sudo systemctl start mysql
```

### 5. Install Redis

```bash
# Install Redis
sudo apt install -y redis-server

# Start service
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 6. Create Directory Structure

```bash
# Create base directories
sudo mkdir -p /opt/gdols-panel
sudo mkdir -p /etc/gdols
sudo mkdir -p /var/log/gdols
sudo mkdir -p /var/lib/gdols/runtime
sudo mkdir -p /var/lib/gdols/backups/database

# Create application subdirectories
sudo mkdir -p /opt/gdols-panel/{app,bin,config,storage,public,logs,scripts}
sudo mkdir -p /opt/gdols-panel/storage/{cache,sessions,uploads}
```

### 7. Download Application Files

```bash
# Clone repository (or extract archive)
cd /tmp
git clone https://github.com/godimyid/gdols-panel.git

# Copy application files to /opt
sudo cp -r gdols-panel/installer/opt/gdols-panel/* /opt/gdols-panel/

# Or if using release archive:
# sudo unzip gdols-panel-1.0.0.zip -d /opt/gdols-panel/
```

### 8. Set Up Configuration

```bash
# Copy default configuration
sudo cp /opt/gdols-panel/config/gdols.conf.example /etc/gdols/gdols.conf

# Set secure permissions
sudo chmod 600 /etc/gdols/gdols.conf
sudo chown root:root /etc/gdols/gdols.conf

# Edit configuration
sudo nano /etc/gdols/gdols.conf
```

**Important Configuration Updates:**

```php
// Update these values in /etc/gdols/gdols.conf

// Database settings
'database' => [
    'password' => 'CHANGE_THIS_TO_SECURE_PASSWORD',
],

// Security settings
'security' => [
    'app_key' => 'CHANGE_THIS_TO_RANDOM_32_CHAR_STRING',
],

// SSL settings
'ssl' => [
    'lets_encrypt' => [
        'email' => 'your-email@example.com',
        'domains' => ['panel.yourdomain.com'],
    ],
],
```

### 9. Set Up Database

```bash
# Create database and user
sudo mysql -u root -p << EOF
CREATE DATABASE gdols_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gdols_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON gdols_panel.* TO 'gdols_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### 10. Set Permissions

```bash
# Application permissions
sudo chmod -R 755 /opt/gdols-panel
sudo chmod -R 750 /opt/gdols-panel/storage
sudo chmod +x /opt/gdols-panel/bin/*

# System directory permissions
sudo chmod 750 /etc/gdols
sudo chmod 750 /var/log/gdols
sudo chmod 750 /var/lib/gdols
```

### 11. Set Up Systemd Service

```bash
# Copy service file
sudo cp /opt/gdols-panel/scripts/gdols-panel.service /etc/systemd/system/

# Reload systemd
sudo systemctl daemon-reload

# Enable service
sudo systemctl enable gdols-panel
```

### 12. Configure OpenLiteSpeed

```bash
# Create virtual host directory
sudo mkdir -p /usr/local/lsws/vhosts/gdols-panel

# Create symlink to public directory
sudo ln -s /opt/gdols-panel/public /usr/local/lsws/vhosts/gdols-panel/html

# Restart OpenLiteSpeed
sudo systemctl restart lsws
```

### 13. Start GDOLS Panel

```bash
# Start the service
sudo systemctl start gdols-panel

# Check status
sudo systemctl status gdols-panel

# View logs
sudo tail -f /var/log/gdols/panel.log
```

---

## ⚙️ Post-Installation Configuration

### 1. Access the Panel

Default access URLs:
- **HTTP**: `http://YOUR_SERVER_IP:8088`
- **HTTPS**: `https://panel.yourdomain.com` (after SSL setup)

### 2. Initial Setup

1. Open the panel in your browser
2. Follow the setup wizard
3. Create admin account
4. Configure SSL certificate
5. Set up backup schedules

### 3. Configure SSL with Let's Encrypt

```bash
# Install Certbot
sudo apt install -y certbot

# Generate certificate
sudo certbot certonly --standalone -d panel.yourdomain.com

# Update configuration
sudo nano /etc/gdols/gdols.conf

# Set SSL certificate paths in config:
# 'certificate_path' => '/etc/letsencrypt/live/panel.yourdomain.com/fullchain.pem',
# 'private_key_path' => '/etc/letsencrypt/live/panel.yourdomain.com/privkey.pem',
```

### 4. Set Up Automated Backups

```bash
# Edit backup cron job
sudo crontab -e

# Add daily backup at 2 AM
0 2 * * * /opt/gdols-panel/scripts/backup-cron.sh >> /var/log/gdols/backup-cron.log 2>&1
```

---

## 🔧 Service Management

### Systemd Commands

```bash
# Start service
sudo systemctl start gdols-panel

# Stop service
sudo systemctl stop gdols-panel

# Restart service
sudo systemctl restart gdols-panel

# Check status
sudo systemctl status gdols-panel

# Enable on boot
sudo systemctl enable gdols-panel

# Disable on boot
sudo systemctl disable gdols-panel

# View logs
sudo journalctl -u gdols-panel -f
```

### Manual Control Scripts

```bash
# Start manually
sudo /opt/gdols-panel/bin/start

# Stop manually
sudo /opt/gdols-panel/bin/stop

# Restart manually
sudo /opt/gdols-panel/bin/restart

# Check detailed status
sudo /opt/gdols-panel/bin/status --verbose
```

### Log Management

```bash
# View main log
sudo tail -f /var/log/gdols/panel.log

# View error log
sudo tail -f /var/log/gdols/panel-error.log

# Rotate logs manually
sudo logrotate -f /etc/logrotate.d/gdols-panel
```

---

## 🌐 Web Server Configuration

### OpenLiteSpeed Virtual Host

Edit virtual host configuration:

```bash
sudo nano /usr/local/lsws/vhosts/gdols-panel/vhconf.conf
```

Example configuration:

```apache
docRoot                   /usr/local/lsws/vhosts/gdols-panel/html
vhDomain                  panel.yourdomain.com

vhAliases                 www.panel.yourdomain.com

enableGzip                1
enableIpGeo               1

errorlog /usr/local/lsws/vhosts/gdols-panel/logs/error.log {
  useServer               0
  logLevel                ERROR
  rollingSize             10M
  keepDays                10
}

accesslog /usr/local/lsws/vhosts/gdols-panel/logs/access.log {
  useServer               0
  rollingSize             10M
  keepDays                10
  compressArchive         1
}

# PHP configuration
phpIniOverride  {
php_admin_value open_basedir "/opt/gdols-panel:/tmp:/var/lib/gdols"
}

# Rewrite rules for API
rewrite  {
  enable                  1
  autoLoadHtaccess        1
}
```

### SSL Configuration

```bash
# Restart OpenLiteSpeed after SSL changes
sudo systemctl restart lsws

# Test SSL configuration
openssl s_client -connect panel.yourdomain.com:443
```

---

## 🔍 Troubleshooting

### Common Issues

#### 1. Service Won't Start

```bash
# Check service status
sudo systemctl status gdols-panel

# Check logs
sudo tail -n 50 /var/log/gdols/panel.log

# Check permissions
ls -la /opt/gdols-panel/bin/
ls -la /etc/gdols/gdols.conf
```

#### 2. Database Connection Failed

```bash
# Check MariaDB service
sudo systemctl status mysql

# Test database connection
mysql -u gdols_user -p gdols_panel

# Check configuration
sudo cat /etc/gdols/gdols.conf | grep -A 10 database
```

#### 3. Permission Errors

```bash
# Fix permissions
sudo chmod -R 755 /opt/gdols-panel
sudo chmod 600 /etc/gdols/gdols.conf
sudo chown -R root:root /opt/gdols-panel
```

#### 4. Web Server Issues

```bash
# Check OpenLiteSpeed status
sudo systemctl status lsws

# Check virtual host
ls -la /usr/local/lsws/vhosts/gdols-panel/html

# Test symlink
readlink /usr/local/lsws/vhosts/gdols-panel/html
```

#### 5. Redis Connection Failed

```bash
# Check Redis service
sudo systemctl status redis-server

# Test Redis connection
redis-cli ping

# Check Redis configuration
sudo cat /etc/redis/redis.conf | grep bind
```

### Debug Mode

Enable debug mode in configuration:

```bash
sudo nano /etc/gdols/gdols.conf

# Set debug to true
'environment' => 'development',
'debug' => true,
```

### Reset Installation

If you need to completely reset the installation:

```bash
# Stop service
sudo systemctl stop gdols-panel

# Remove application files
sudo rm -rf /opt/gdols-panel

# Remove configuration
sudo rm -rf /etc/gdols

# Remove logs
sudo rm -rf /var/log/gdols

# Remove runtime data
sudo rm -rf /var/lib/gdols

# Remove systemd service
sudo rm /etc/systemd/system/gdols-panel.service
sudo systemctl daemon-reload

# Reinstall
sudo bash installer/install.sh
```

---

## 📞 Support

For additional help:
- **Documentation**: [https://github.com/godimyid/gdols-panel](https://github.com/godimyid/gdols-panel)
- **Issues**: [https://github.com/godimyid/gdols-panel/issues](https://github.com/godimyid/gdols-panel/issues)
- **Community**: [GitHub Discussions](https://github.com/godimyid/gdols-panel/discussions)

---

## ❤️ Support This Project

GDOLS Panel is free and open-source software. If you find this project helpful, please consider supporting its continued development:

**☕ Buy Me a Coffee**: [https://ko-fi.com/godimyid/goal?g=0](https://ko-fi.com/godimyid/goal?g=0)

### Other Ways to Support

- ⭐ **Star on GitHub** - Show your support and help others discover GDOLS Panel
- 🐛 **Report Bugs** - Help us improve by reporting issues
- 💡 **Feature Requests** - Suggest new features you'd like to see
- 📢 **Share** - Spread the word about GDOLS Panel
- 📚 **Contribute** - Submit pull requests to improve code or documentation

### Your Support Helps

- 🛠️ Maintain and improve the panel
- 🐛 Fix bugs faster
- ✨ Add new features
- 📚 Keep documentation up-to-date
- 🌍 Support more users
- 🔒 Ensure security updates

**Thank you for supporting GDOLS Panel!** 🙏

---

## 📝 License

GDOLS Panel is licensed under the MIT License. See [LICENSE](https://github.com/godimyid/gdols-panel/blob/main/LICENSE) for details.

---

**Version**: 1.1.0  
**Last Updated**: December 25, 2025  
**Maintained By**: GDOLS Panel Team