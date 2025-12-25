# GDOLS Panel - FHS-Compliant Installation Structure

## 🎯 Overview

This directory contains the **production-ready, FHS-compliant** installation structure for GDOLS Panel v1.1.0. This structure follows the Linux Filesystem Hierarchy Standard (FHS) and is optimized for Ubuntu 24.04 LTS deployments.

### Why This Structure?

✅ **Professional**: Follows Linux standards for third-party applications  
✅ **Secure**: Configuration isolated from application code  
✅ **Maintainable**: Easy to backup, update, and scale  
✅ **Portable**: Works across different Linux distributions  
✅ **Production-Ready**: Suitable for SaaS and multi-server deployments  

---

## 📁 Directory Structure

```
installer/
├── install.sh                 # Main automated installer script
├── INSTALL.md                 # Detailed installation guide
├── README.md                  # This file
│
├── opt/
│   └── gdols-panel/          # Core application directory
│       ├── app/              # Source code (API, templates)
│       ├── bin/              # Executable scripts (start, stop, restart, status)
│       ├── config/           # Default configuration templates
│       ├── storage/          # Application data (cache, sessions, uploads)
│       ├── public/           # Web-accessible files
│       ├── logs/             # Internal application logs
│       ├── scripts/          # Automation and maintenance scripts
│       └── VERSION           # Version information
│
├── etc/
│   ├── gdols/
│   │   └── gdols.conf       # Main configuration (SECURE - not in repo)
│   └── systemd/system/
│       └── gdols-panel.service  # Systemd service definition
│
└── (Other system directories will be created during installation)
    ├── /var/log/gdols/      # System logs
    ├── /var/lib/gdols/      # Runtime data and backups
    └── /usr/local/lsws/vhosts/gdols-panel/  # OpenLiteSpeed vhost
```

---

## 🚀 Quick Installation

### Method 1: Automated Installer (Recommended)

```bash
# From this directory
sudo bash install.sh

# Or download and run directly
wget https://github.com/godimyid/gdols-panel/raw/main/installer/install.sh
sudo bash install.sh
```

### Method 2: Manual Installation

See [INSTALL.md](INSTALL.md) for detailed manual installation steps.

---

## 📋 System Locations

After installation, GDOLS Panel will be organized as follows:

### Application Files

| Location | Purpose | Permissions |
|----------|---------|-------------|
| `/opt/gdols-panel/` | Core application | 755 |
| `/opt/gdols-panel/app/` | Source code | 755 |
| `/opt/gdols-panel/bin/` | Executable scripts | 755 (executable) |
| `/opt/gdols-panel/config/` | Config templates | 644 |
| `/opt/gdols-panel/storage/` | Application data | 750 |
| `/opt/gdols-panel/public/` | Web files | 755 |

### System Configuration

| Location | Purpose | Permissions |
|----------|---------|-------------|
| `/etc/gdols/gdols.conf` | Main config | **600** (root:root) |
| `/etc/systemd/system/gdols-panel.service` | Service definition | 644 |

### Runtime Data

| Location | Purpose | Permissions |
|----------|---------|-------------|
| `/var/log/gdols/` | System logs | 750 |
| `/var/lib/gdols/runtime/` | Runtime files | 750 |
| `/var/lib/gdols/backups/` | Automated backups | 750 |

### Web Server

| Location | Purpose | Type |
|----------|---------|------|
| `/usr/local/lsws/vhosts/gdols-panel/html` | Document root | Symlink → `/opt/gdols-panel/public` |

---

## 🛠️ Management Commands

### Service Management

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
```

### Manual Control

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

# View systemd journal
sudo journalctl -u gdols-panel -f
```

---

## ⚙️ Configuration

### Main Configuration File

**Location**: `/etc/gdols/gdols.conf`  
**Permissions**: `600` (root:root only)

Edit configuration:
```bash
sudo nano /etc/gdols/gdols.conf
```

### Key Settings to Update

```php
// Database credentials
'database' => [
    'password' => 'CHANGE_THIS_TO_SECURE_PASSWORD',
],

// Application security
'security' => [
    'app_key' => 'CHANGE_THIS_TO_RANDOM_32_CHAR_STRING',
],

// Let's Encrypt SSL
'ssl' => [
    'lets_encrypt' => [
        'email' => 'your-email@example.com',
        'domains' => ['panel.yourdomain.com'],
    ],
],
```

---

## 🔒 Security Best Practices

### 1. File Permissions

```bash
# Secure configuration file
sudo chmod 600 /etc/gdols/gdols.conf
sudo chown root:root /etc/gdols/gdols.conf

# Secure runtime directories
sudo chmod 750 /var/log/gdols
sudo chmod 750 /var/lib/gdols

# Verify permissions
ls -la /etc/gdols/
ls -la /opt/gdols-panel/
```

### 2. Configuration Security

- **Never** commit `/etc/gdols/gdols.conf` to version control
- Use strong, unique passwords for database and app_key
- Keep encryption keys secure and backed up
- Review and update security settings regularly

### 3. Service Security

```bash
# Check service runs as root (required for system management)
sudo systemctl cat gdols-panel | grep User

# Verify no unnecessary privileges
sudo systemd-analyze security gdols-panel
```

---

## 🔄 Updates and Maintenance

### Update Application

```bash
# Stop service
sudo systemctl stop gdols-panel

# Backup current version
sudo cp -r /opt/gdols-panel /var/lib/gdols/backups/gdols-panel-backup-$(date +%Y%m%d)

# Download and extract new version
cd /tmp
wget https://github.com/godimyid/gdols-panel/archive/main.zip
unzip main.zip

# Copy new files (preserve config)
sudo cp -r gdols-panel-main/installer/opt/gdols-panel/* /opt/gdols-panel/

# Restart service
sudo systemctl start gdols-panel
```

### Backup Configuration

```bash
# Backup configuration
sudo cp /etc/gdols/gdols.conf /var/lib/gdols/backups/gdols.conf-$(date +%Y%m%d)

# Backup entire installation
sudo tar -czf /var/lib/gdols/backups/full-backup-$(date +%Y%m%d).tar.gz \
    /opt/gdols-panel \
    /etc/gdols \
    /usr/local/lsws/vhosts/gdols-panel
```

---

## 🐛 Troubleshooting

### Service Won't Start

```bash
# Check service status
sudo systemctl status gdols-panel

# View logs
sudo tail -n 50 /var/log/gdols/panel.log

# Check configuration
sudo /opt/gdols-panel/bin/status --verbose

# Verify permissions
ls -la /opt/gdols-panel/bin/
```

### Permission Errors

```bash
# Fix application permissions
sudo chmod -R 755 /opt/gdols-panel
sudo chmod +x /opt/gdols-panel/bin/*

# Fix system permissions
sudo chmod 600 /etc/gdols/gdols.conf
sudo chmod 750 /var/log/gdols
sudo chmod 750 /var/lib/gdols
```

### Web Server Issues

```bash
# Check OpenLiteSpeed
sudo systemctl status lsws

# Verify symlink
ls -la /usr/local/lsws/vhosts/gdols-panel/html

# Test web server configuration
sudo /usr/local/lsws/bin/lswsctrl -t
```

---

## 📊 Performance Optimization

### Systemd Service Tuning

Edit service file:
```bash
sudo systemctl edit gdols-panel
```

Example overrides:
```ini
[Service]
# Increase resource limits
LimitNOFILE=65536
LimitNPROC=4096

# Add environment variables
Environment=GDOLS_PANEL_MEMORY_LIMIT=256M
Environment=GDOLS_PANEL_EXECUTION_TIME=300
```

### Log Rotation

Configuration: `/etc/logrotate.d/gdols-panel`

```bash
# Test log rotation
sudo logrotate -f /etc/logrotate.d/gdols-panel

# View log rotation status
sudo cat /var/lib/logrotate/status | grep gdols
```

---

## 🌐 Network Configuration

### Firewall Rules

```bash
# Allow HTTP (if needed)
sudo ufw allow 80/tcp

# Allow HTTPS
sudo ufw allow 443/tcp

# Allow OpenLiteSpeed admin (if needed)
sudo ufw allow 7080/tcp

# Check status
sudo ufw status
```

### SSL/TLS Setup

```bash
# Install Certbot
sudo apt install -y certbot

# Generate certificate
sudo certbot certonly --standalone -d panel.yourdomain.com

# Update configuration with certificate paths
sudo nano /etc/gdols/gdols.conf
```

---

## 📈 Monitoring

### System Monitoring

```bash
# Check service resource usage
sudo systemctl status gdols-panel
ps aux | grep gdols-panel

# Monitor logs in real-time
sudo tail -f /var/log/gdols/panel.log

# Check disk usage
du -sh /opt/gdols-panel
du -sh /var/log/gdols
du -sh /var/lib/gdols
```

### Health Checks

```bash
# Run status command
sudo /opt/gdols-panel/bin/status --verbose

# Check all dependencies
php -v
mysql --version
redis-cli --version
lshttpd -v
```

---

## 🤝 Contributing

When contributing to GDOLS Panel, please ensure:

1. ✅ Follow FHS standards for any new directories
2. ✅ Update installer script for new files
3. ✅ Document any new configuration options
4. ✅ Test installation on clean Ubuntu 24.04 system
5. ✅ Verify permissions and security settings

---

## 📝 Version Information

**Current Version**: 1.1.0  
**Release Date**: December 25, 2025  
**Compatibility**: Ubuntu 24.04 LTS+

Check installed version:
```bash
cat /opt/gdols-panel/VERSION
```

---

## 📞 Support

- **Documentation**: [https://github.com/godimyid/gdols-panel](https://github.com/godimyid/gdols-panel)
- **Installation Guide**: [INSTALL.md](INSTALL.md)
- **Issues**: [GitHub Issues](https://github.com/godimyid/gdols-panel/issues)
- **Discussions**: [GitHub Discussions](https://github.com/godimyid/gdols-panel/discussions)

---

## ❤️ Support This Project

GDOLS Panel is free and open-source. If you find this project helpful, consider supporting its development:

- **☕ Saweria**: [https://saweria.co/godi](https://saweria.co/godi) - Support locally (Indonesia)
- **☕ Buy Me a Coffee**: [https://ko-fi.com/godimyid/goal?g=0](https://ko-fi.com/godimyid/goal?g=0)
- **⭐ Star on GitHub**: [https://github.com/godimyid/gdols-panel](https://github.com/godimyid/gdols-panel)
- **🐛 Report Issues**: Help us improve by reporting bugs
- **💡 Feature Requests**: Suggest new features you'd like to see
- **📢 Share**: Spread the word about GDOLS Panel

Your support helps us:
- 🛠️ Maintain and improve the panel
- 🐛 Fix bugs faster
- ✨ Add new features
- 📚 Keep documentation up-to-date
- 🌍 Support more users

**Thank you for your support!** 🙏

---

## 📄 License

GDOLS Panel is licensed under the MIT License. See LICENSE file for details.

---

**Last Updated**: December 25, 2025  
**Maintained By**: GDOLS Panel Team