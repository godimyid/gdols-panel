# GDOLS Panel - OpenLiteSpeed Management Panel

![GDOLS Panel Logo](https://img.shields.io/badge/GDOLS_Panel-v1.0.0-purple?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.3-blue?style=for-the-badge)
![OpenLiteSpeed](https://img.shields.io/badge/OpenLiteSpeed-Latest-green?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge)

**Author:** GoDiMyID  
**Website:** [godi.my.id](https://godi.my.id)  
**Version:** 1.0.0

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Post-Installation Setup](#post-installation-setup)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [License](#license)

---

## 🎯 Overview

GDOLS Panel is a comprehensive server management panel designed specifically for **OpenLiteSpeed** web servers with **PHP 8.3**, **MariaDB**, and **Redis**. It provides a web-based GUI to manage your server without needing SSH access, making server administration accessible and efficient.

### Why GDOLS Panel?

- **Lightweight & Fast** - Built with vanilla JavaScript and PHP, no heavy frameworks
- **User-Friendly** - Intuitive interface designed for both beginners and experts
- **Secure** - Built-in security features including CSRF protection, rate limiting, and encryption
- **Comprehensive** - Manage virtual hosts, PHP extensions, firewall, Redis, databases, and more
- **Open Source** - Free to use and modify

---

## ✨ Features

### 🌐 Virtual Host Management
- **WordPress Installer** - One-click WordPress installation
- **Custom Virtual Hosts** - Create custom virtual hosts for any PHP application
- **Reverse Proxy** - Set up reverse proxy for Node.js, Python, and other applications
- **SSL Management** - Easy SSL certificate installation and management

### 🔧 PHP Extensions Manager
- **Extension Checklist** - Enable/disable PHP extensions with a simple checkbox
- **Auto-Installation** - Automatically install missing extensions
- **Popular Extensions**:
  - ImageMagick
  - Internationalization (Intl)
  - IonCube Loader
  - Redis
  - mysqli, PDO
  - And many more...

### 🔥 Firewall Management (UFW)
- **Visual Rule Editor** - Add/remove firewall rules through GUI
- **Predefined Rules** - Quick setup for common services (SSH, HTTP, HTTPS)
- **Port Management** - Open/close ports with one click
- **Rule Templates** - Save and reuse rule configurations

### 🔴 Redis Management
- **Configuration** - Edit Redis settings through web interface
- **Monitoring** - Real-time Redis statistics
- **Memory Management** - Configure memory limits and eviction policies
- **Service Control** - Start/stop/restart Redis

### 🗄️ Database Management
- **MariaDB Integration** - Create and manage databases
- **User Management** - Create database users with permissions
- **Backup** - Automated database backups
- **phpMyAdmin Integration** - Optional phpMyAdmin installation

### 📊 System Monitoring
- **Real-time Stats** - CPU, Memory, Disk usage
- **Process Monitor** - View running processes
- **Log Viewer** - View system and application logs
- **Service Status** - Check status of all services

### 💾 Backup & Restore
- **Automated Backups** - Schedule automatic backups
- **Multiple Backup Types** - Full, database only, files only
- **One-Click Restore** - Restore from backup with single click
- **Remote Backup** - Upload backups to remote storage

### 🔐 Security Features
- **CSRF Protection** - All forms protected with CSRF tokens
- **Rate Limiting** - API rate limiting to prevent abuse
- **Authentication** - Secure login with session management
- **Encryption** - Sensitive data encrypted at rest
- **Audit Log** - Complete audit trail of all actions

---

## 💻 System Requirements

### Minimum Requirements
- **Operating System:** Ubuntu 20.04+ or Debian 11+
- **RAM:** 2GB minimum, 4GB recommended
- **Disk Space:** 20GB minimum
- **CPU:** 1 core minimum, 2+ cores recommended

### Software Requirements
- **OpenLiteSpeed:** Latest stable version
- **PHP:** 8.1 or higher (8.3 recommended)
- **MariaDB:** 10.6 or higher
- **Redis:** 6.0 or higher (optional but recommended)
- **Web Server:** OpenLiteSpeed with PHP support

### PHP Extensions Required
- PDO
- PDO_MySQL
- JSON
- MBString
- cURL
- OpenSSL
- Session

---

## 🚀 Installation

### Step 1: Install OpenLiteSpeed with ols1clk

First, install OpenLiteSpeed using the official installer:

```bash
bash <( curl -sSk https://raw.githubusercontent.com/litespeedtech/ols1clk/master/ols1clk.sh ) \
  --pure-mariadb \
  --adminuser [USERNAME_ANDA] \
  --adminpassword [PASSWORD_ANDA] \
  --adminport [NOMOR_PORT_ANDA] \
  --lsphp 83
```

This will install:
- OpenLiteSpeed web server
- PHP 8.3 (LSPHP)
- MariaDB database
- Default WordPress instance

### Step 2: Install Redis (Optional but Recommended)

```bash
# Update package list
sudo apt update

# Install Redis
sudo apt install redis-server -y

# Enable Redis to start on boot
sudo systemctl enable redis

# Start Redis
sudo systemctl start redis

# Check status
sudo systemctl status redis
```

### Step 3: Secure Redis (Important!)

```bash
# Edit Redis configuration
sudo nano /etc/redis/redis.conf

# Set bind to localhost
bind 127.0.0.1 ::1

# Set password (replace with strong password)
requirepass STRONG_REDIS_PASSWORD

# Disable dangerous commands (optional)
rename-command FLUSHALL ""
rename-command FLUSHDB ""
rename-command CONFIG ""

# Restart Redis
sudo systemctl restart redis
```

### Step 4: Configure Firewall

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

### Step 5: Install GDOLS Panel

```bash
# Navigate to web root
cd /var/www

# Clone or upload GDOLS Panel
git clone https://github.com/godimyid/gd-panel.git gdpanel
# OR upload the files manually

# Set proper permissions
sudo chown -R nobody:nogroup gdpanel
sudo chmod -R 755 gdpanel

# Create necessary directories
cd gdpanel
mkdir -p config logs sessions backups
chmod 750 config logs sessions backups
```

### Step 6: Run Web Installer

1. Open your browser and navigate to:
   ```
   http://your-server-ip/gdpanel/install.php
   ```

2. Follow the installation wizard:
   - **Welcome** - Read the overview
   - **Requirements Check** - Verify system meets requirements
   - **Database Setup** - Enter MariaDB credentials
   - **Configuration** - Set admin credentials
   - **Installation** - Wait for installation to complete
   - **Complete** - Delete install.php and login to panel

3. **Delete install.php after installation:**
   ```bash
   rm /var/www/gdolspanel/install.php
   ```

### Step 7: Access Your Panel

```
URL: http://your-server-ip/gdpanel/public/
Username: admin (or what you set during installation)
Password: (what you set during installation)
```

---

## ⚙️ Post-Installation Setup

### 1. Update PHP Extensions

After installation, install additional PHP extensions:

```bash
# ImageMagick
sudo apt install lsphp83-imagick -y

# Internationalization
sudo apt install lsphp83-intl -y

# IonCube Loader (download from ioncube.com)
wget http://downloads3.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
tar xzf ioncube_loaders_lin_x86-64.tar.gz
sudo cp ioncube/ioncube_loader_lin_8.3.so /usr/local/lsws/lsphp83/lib/php/20230831/
echo "zend_extension = /usr/local/lsws/lsphp83/lib/php/20230831/ioncube_loader_lin_8.3.so" >> /usr/local/lsws/lsphp83/etc/php.ini
```

Then enable them through the GDOLS Panel GUI at **PHP Extensions** section.

### 2. Configure Default Virtual Host

1. Log in to GDOLS Panel
2. Go to **Virtual Hosts** → **Add New**
3. Choose type:
   - **WordPress** - Auto-install WordPress
   - **Custom** - Empty virtual host
   - **Proxy** - Reverse proxy to backend

### 3. Set Up Automated Backups

1. Go to **Settings** → **Backup**
2. Enable automated backups
3. Set backup schedule (daily, weekly)
4. Choose backup retention period
5. Save settings

### 4. Configure Redis (if installed)

1. Go to **Redis** → **Configuration**
2. Adjust settings:
   - Max Memory: 2g (for 8GB RAM server)
   - Policy: allkeys-lru
   - Timeout: 300
3. Click **Save & Restart**

### 5. Set Up Firewall Rules

1. Go to **Firewall** → **Rules**
2. Add custom rules as needed
3. Enable/disable rules
4. Apply changes

---

## 📖 Usage

### Dashboard

The dashboard provides an overview of your server:

- **System Resources** - CPU, Memory, Disk usage
- **Service Status** - OLS, MariaDB, Redis status
- **Recent Activity** - Latest system logs
- **Quick Actions** - Common tasks shortcuts

### Virtual Host Management

#### Add WordPress Site

1. **Virtual Hosts** → **Add New**
2. Select **WordPress**
3. Enter:
   - Domain: `blog.example.com`
   - Email: `admin@example.com`
   - Document Root: (auto-generated or custom)
4. Click **Install**

#### Add Custom PHP Application

1. **Virtual Hosts** → **Add New**
2. Select **Custom**
3. Enter domain and email
4. Click **Create**
5. Upload files to document root

#### Add Reverse Proxy (Node.js/Python)

1. **Virtual Hosts** → **Add New**
2. Select **Proxy**
3. Enter:
   - Domain: `api.example.com`
   - Backend Host: `127.0.0.1`
   - Backend Port: `3001`
   - URI: `/`
4. Click **Create**

### PHP Extensions

1. **PHP Extensions** → **Extensions**
2. See list of all available extensions
3. Check/uncheck to enable/disable
4. Click **Install** to add new extensions
5. Click **Apply Changes** to reload PHP

### Firewall Management

1. **Firewall** → **Rules**
2. Click **Add Rule**
3. Configure:
   - Action: Allow/Deny
   - Port: e.g., 3306 for MySQL
   - Protocol: TCP/UDP
   - Source: IP or "any"
4. Click **Add**

### Redis Management

1. **Redis** → **Dashboard**
2. View real-time statistics
3. **Configuration** - Edit settings
4. **Flush** - Clear all data (be careful!)

### Database Management

1. **Databases** → **List**
2. **Add Database** - Create new database
3. **Add User** - Create database user
4. **Backup** - Create database backup

### System Logs

1. **Logs** → **System Logs**
2. Filter by:
   - Date range
   - Action type
   - User
   - Status
3. **Export** - Download logs as JSON/CSV

---

## 🔌 API Documentation

GDOLS Panel provides a RESTful API for automation.

### Authentication

All API requests require authentication:

```bash
# Login
curl -X POST https://your-panel.com/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your_password"}'
```

Response:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "role": "admin"
    },
    "csrf_token": "abc123..."
  }
}
```

### Create Virtual Host

```bash
curl -X POST https://your-panel.com/api/vhost.php?action=create \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: abc123..." \
  -d '{
    "domain": "newsite.com",
    "email": "admin@newsite.com",
    "type": "wordpress"
  }'
```

### Get System Info

```bash
curl -X GET https://your-panel.com/api/system.php?action=info \
  -H "X-CSRF-Token: abc123..."
```

### Install PHP Extension

```bash
curl -X POST https://your-panel.com/api/php.php?action=install_extension \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: abc123..." \
  -d '{"extension": "imagick"}'
```

For complete API documentation, see [API.md](API.md).

---

## 🔒 Security

### Best Practices

1. **Change Default Password**
   - Immediately after installation, change the admin password

2. **Delete install.php**
   - Remove installer to prevent reinstallation

3. **Use HTTPS**
   - Install SSL certificate for panel access

4. **Restrict Access**
   - Use firewall to limit panel access to specific IPs

5. **Keep Updated**
   - Regularly update GDOLS Panel and dependencies

6. **Monitor Logs**
   - Review system logs regularly for suspicious activity

7. **Backup Regularly**
   - Set up automated backups

### Secure Panel with SSL

```bash
# Install Certbot
sudo apt install certbot

# Generate certificate
sudo certbot certonly --standalone -d panel.yourdomain.com

# Configure OLS to use certificate
# Via WebAdmin or edit configuration
```

### Enable Two-Factor Authentication (Future)

Planned feature for future versions.

---

## 🛠️ Troubleshooting

### Common Issues

#### 1. Installation Fails - Database Connection Error

**Problem:** Cannot connect to database during installation

**Solution:**
```bash
# Test MySQL connection
mysql -h localhost -u root -p

# Check if MariaDB is running
sudo systemctl status mariadb

# Restart if needed
sudo systemctl restart mariadb
```

#### 2. 403 Forbidden Error

**Problem:** Cannot access panel after installation

**Solution:**
```bash
# Check file permissions
ls -la /var/www/gdolspanel

# Fix permissions
sudo chown -R nobody:nogroup /var/www/gdolspanel
sudo chmod -R 755 /var/www/gdolspanel
```

#### 3. PHP Extensions Not Loading

**Problem:** Extensions not working after installation

**Solution:**
```bash
# Check PHP configuration
/usr/local/lsws/lsphp83/bin/php -m

# Restart OLS
sudo /usr/local/lsws/bin/lswsctrl restart

# Check extension files exist
ls -la /usr/local/lsws/lsphp83/lib/php/20230831/
```

#### 4. Redis Connection Failed

**Problem:** Panel cannot connect to Redis

**Solution:**
```bash
# Check Redis status
sudo systemctl status redis

# Test connection
redis-cli -a STRONG_REDIS_PASSWORD PING

# Check Redis config
sudo cat /etc/redis/redis.conf | grep bind
```

#### 5. Virtual Host Not Working

**Problem:** New virtual host shows default page

**Solution:**
```bash
# Check vhost configuration
cat /usr/local/lsws/conf/vhosts/yourdomain.com/vhconf.conf

# Restart OLS
sudo /usr/local/lsws/bin/lswsctrl restart

# Check DNS resolution
nslookup yourdomain.com
```

### Getting Help

If you encounter issues not covered here:

1. **Check Logs**
   ```bash
   tail -f /var/www/gdolspanel/logs/error.log
   tail -f /usr/local/lsws/logs/error.log
   ```

2. **Enable Debug Mode**
   ```php
   // In config/config.php
   define('DEBUG_MODE', true);
   ```

3. **Visit Forums**
   - Website: [godi.my.id](https://godi.my.id)
   - GitHub Issues: [Create issue](https://github.com/godimyid/gd-panel/issues)

4. **Contact Support**
   - Email: support@godi.my.id

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. **Fork** the repository
2. **Create** a feature branch
3. **Make** your changes
4. **Test** thoroughly
5. **Submit** a pull request

### Coding Standards

- Follow PSR-12 coding standards
- Add comments for complex code
- Update documentation
- Test on both Ubuntu and Debian

### Feature Requests

Submit feature requests via GitHub Issues with:
- Clear description
- Use cases
- Proposed implementation

---

## 📄 License

GDOLS Panel is open-source software licensed under the **MIT License**.

```
Copyright (c) 2024 GoDiMyID

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 🙏 Acknowledgments

- **LiteSpeed Technologies** - Excellent web server and installation script
- **PHP Community** - Amazing language and ecosystem
- **MariaDB Foundation** - Robust database solution
- **Redis Team** - Powerful caching solution
- **Open Source Community** - Inspiration and tools

---

## 📞 Support & Contact

- **Website:** [godi.my.id](https://godi.my.id)
- **Documentation:** [docs.godi.my.id](https://docs.godi.my.id)
- **GitHub:** [github.com/godimyid/gdols-panel](https://github.com/godimyid/gdols-panel)
- **Email:** support@godi.my.id

---

## 📝 Changelog

### Version 1.0.0 (2025-12-25)
- Initial release
- OpenLiteSpeed management
- PHP 8.3 support
- MariaDB integration
- Redis management
- Firewall (UFW) management
- Virtual host management (WordPress, Custom, Proxy)
- PHP Extensions management with checklist interface
- Database Management Interface
  - Full CRUD operations for databases and users
  - SQL import/export functionality
- SSL Management with Let's Encrypt integration
- Automated backup system with scheduling
- Advanced Rate Limiting for security
- System monitoring and resource tracking
- Smart Virtual Host deletion (with database cleanup)

---

## 🗺️ Roadmap

### 🎯 Short Term (Q1 2026)
- [ ] **Multi-language Support**
  - Indonesian and English interface
  - Easy language switching
  - Translation-ready architecture
- [ ] **phpMyAdmin Integration**
  - Database management UI
  - SQL query browser
  - Import/export wizards
- [ ] **File Manager**
  - Browse and edit files
  - Upload/download functionality
- [ ] **Enhanced Monitoring Dashboard**
  - Real-time graphs and charts
  - Customizable widgets
  - Performance alerts

### 🚀 Medium Term (Q2-Q3 2026)
- [ ] **Two-Factor Authentication (2FA)**
  - TOTP support (Google Authenticator)
  - QR code setup
  - Backup codes
- [ ] **Backup Automation UI**
  - Web-based backup scheduler
  - Backup browser and restore
  - Backup encryption options
- [ ] **Multi-user Support**
  - Role-based access control (RBAC)
  - User management interface
  - Activity audit logs
- [ ] **Container Management**
  - Docker integration
  - Container deployment
  - Container monitoring

### 🌟 Long Term (Q4 2026 - 2027)
- [ ] **Cluster Management**
  - Multi-server support
  - Load balancer configuration
  - Centralized dashboard
- [ ] **Application Marketplace**
  - One-click app installation (WordPress, Laravel, Node.js)
  - Custom application templates
  - Version management
- [ ] **DNS Management**
  - DNS record editor
  - Subdomain management
  - Integration with Cloudflare
- [ ] **Email Management**
  - Email account creation
  - Webmail integration
  - Spam filtering
- [ ] **SSL Certificate Automation**
  - Auto-discovery of domains
  - Bulk certificate management
  - Certificate expiration alerts
- [ ] **API Documentation**
  - Swagger/OpenAPI integration
  - Interactive API explorer
  - Code examples for developers

### 💡 Future Enhancements
- [ ] **Mobile App**
  - iOS and Android applications
  - Push notifications
  - On-the-go management
- [ ] **Analytics & Reporting**
  - Usage statistics
  - Performance reports
  - Security audit logs
- [ ] **Integration Hub**
  - Webhook support
  - Third-party integrations
  - Plugin system
- [ ] **AI-Powered Features**
  - Smart security recommendations
  - Performance optimization suggestions
  - Anomaly detection

### 🔄 Planned Improvements
- Enhanced UI/UX redesign
- Dark/Light theme toggle
- Customizable dashboard
- Keyboard shortcuts
- Advanced search functionality
- Bulk operations support
- Template system for configurations

---

## 🤝 Contributing

We welcome contributions from the community! Whether it's bug fixes, new features, or documentation improvements, your help is appreciated.

### Development Setup

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/AmazingFeature`
3. Commit your changes: `git commit -m 'Add some AmazingFeature'`
4. Push to the branch: `git push origin feature/AmazingFeature`
5. Open a Pull Request

### Coding Standards
- Follow PSR-12 coding standards
- Write clear, commented code
- Add tests for new features
- Update documentation

### Feature Requests
Want a feature that's not on the roadmap? Open an issue and let us know. We prioritize based on community needs and technical feasibility.

---

**Made with ❤️ by GoDiMyID**

*If you find GDOLS Panel useful, please consider:*
- ⭐ Starring the repository on GitHub
- 🐛 Reporting bugs
- 💡 Suggesting features
- 💬 Sharing with others

**Thank you for using GDOLS Panel! 🚀**
