# 🚀 GDOLS Panel

<div align="center">

![GDOLS Panel Logo](https://img.shields.io/badge/GDOLS_Panel-v1.1.0-purple?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.3-blue?style=for-the-badge)
![OpenLiteSpeed](https://img.shields.io/badge/OpenLiteSpeed-Latest-green?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge)
![Ubuntu](https://img.shields.io/badge/Ubuntu-24.04-orange?style=for-the-badge)

**Professional OpenLiteSpeed Management Panel**

[Features](#-features) • [Installation](#-quick-install) • [Documentation](#-documentation) • [Support](#-support) • [Contributing](#-contributing)

[⭐ Star us on GitHub](https://github.com/godimyid/gdols-panel) • 
[🐛 Report Issues](https://github.com/godimyid/gdols-panel/issues) • 
[💬 Discussions](https://github.com/godimyid/gdols-panel/discussions)

</div>

---

## 📖 About

**GDOLS Panel** (GoDiMyID OpenLiteSpeed Panel) is a professional, web-based management panel designed specifically for **OpenLiteSpeed** web servers. It provides a user-friendly interface to manage your server, databases, PHP, virtual hosts, SSL certificates, and more - all from your browser!

### 🎯 Why GDOLS Panel?

- ✅ **FHS-Compliant** - Follows Linux Filesystem Hierarchy Standard
- ✅ **Production Ready** - Built for real-world deployments
- ✅ **Free & Open Source** - MIT License, use it forever
- ✅ **Automated Installer** - One-command installation
- ✅ **Systemd Integrated** - Native Linux service management
- ✅ **Modern UI** - Clean, responsive, intuitive interface
- ✅ **Secure by Default** - Built-in security features

---

## ✨ Features

### 🌐 Virtual Host Management
- Create and manage OpenLiteSpeed virtual hosts
- WordPress auto-installation
- Custom and proxy vhosts
- SSL certificate management with Let's Encrypt integration
- Auto-renewal every 12 hours

### 🗄️ Database Management
- MariaDB/MySQL database CRUD operations
- User management with permissions
- SQL import/export functionality
- Automated database backups
- phpMyAdmin integration (planned)

### 🐘 PHP Extensions
- Enable/disable PHP 8.3 extensions
- Comprehensive extension checklist
- Version management
- Configuration management

### ⚡ Redis Management
- Start/stop Redis service
- Clear cache operations
- View Redis statistics
- Monitor performance metrics

### 🔒 Security Features
- IP whitelist/blacklist management
- Rate limiting (Redis/File-based)
- Brute force protection
- CSRF protection
- Session security

### 🔥 Firewall (UFW)
- Easy UFW rule management
- Port management
- Allow/deny rules
- Firewall status monitoring

### 📊 System Monitoring
- Real-time server resource monitoring
- CPU, Memory, Disk usage tracking
- Process management
- Service status dashboard

### 💾 Backup Automation
- Scheduled backups (daily, weekly, monthly)
- Multiple storage backends (S3, FTP, SSH)
- Configurable retention policies
- Compression and encryption support
- One-click restore functionality

### ⚙️ Configuration Management
- Systemd service integration
- Centralized configuration (`/etc/gdols/gdols.conf`)
- Log rotation setup
- Environment management

---

## 🚀 Quick Install

### Requirements

- **OS**: Ubuntu 24.04 LTS (or compatible Debian-based system)
- **RAM**: 2GB minimum (4GB recommended)
- **Disk**: 20GB free space
- **User**: Root or sudo access

### One-Line Installation

```bash
wget https://github.com/godimyid/gdols-panel/raw/main/installer/install.sh
sudo bash install.sh
```

### Manual Installation

See [INSTALL.md](installer/INSTALL.md) for detailed installation guide.

---

## 📁 FHS-Compliant Structure

GDOLS Panel follows the Linux Filesystem Hierarchy Standard:

```
/opt/gdols-panel/          # Application files
├── app/                   # Source code
├── bin/                   # Service scripts
├── config/                # Default configs
├── storage/               # Application data
├── public/                # Web UI
├── scripts/               # Automation scripts
└── VERSION

/etc/gdols/               # Configuration (not in repo)
└── gdols.conf

/var/log/gdols/           # System logs
/var/lib/gdols/           # Runtime data & backups
```

---

## 🎮 Usage

### Start Service

```bash
sudo systemctl start gdols-panel
```

### Check Status

```bash
sudo systemctl status gdols-panel
```

### Access Panel

Open your browser:
```
http://your-server-ip:8088
```

### Service Management

```bash
# Start
sudo /opt/gdols-panel/bin/start

# Stop
sudo /opt/gdols-panel/bin/stop

# Restart
sudo /opt/gdols-panel/bin/restart

# Status
sudo /opt/gdols-panel/bin/status --verbose
```

---

## 📚 Documentation

- **[Installation Guide](installer/INSTALL.md)** - Complete installation instructions
- **[FHS Migration Guide](installer/FHS_MIGRATION.md)** - Migrate from old structure
- **[Installation Flow](installer/INSTALLATION_FLOW.md)** - Visual installation process
- **[Changelog](installer/CHANGELOG.md)** - Version history and changes
- **[Roadmap](installer/ROADMAP.md)** - Development roadmap
- **[Support](installer/SUPPORT.md)** - Support and contribution guide

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.3
- **Web Server**: OpenLiteSpeed
- **Database**: MariaDB 10.x
- **Cache**: Redis 7.x
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **Service Management**: Systemd
- **OS**: Ubuntu 24.04 LTS

---

## 🔐 Security

- Configuration isolated from application code
- Secure file permissions (600 for configs)
- Brute force protection
- Rate limiting
- CSRF protection
- Session security
- SSL/TLS encryption

---

## 📸 Screenshots

### Dashboard
![Dashboard](https://via.placeholder.com/800x450?text=Dashboard+Screenshot)

### Virtual Hosts
![Virtual Hosts](https://via.placeholder.com/800x450?text=Virtual+Hosts+Screenshot)

### Database Management
![Database](https://via.placeholder.com/800x450?text=Database+Screenshot)

---

## 🤝 Contributing

We welcome contributions! Please see our [Support Guide](installer/SUPPORT.md) for details.

### Ways to Contribute

- ⭐ **Star** the repository
- 🐛 **Report bugs**
- 💡 **Suggest features**
- 🔧 **Submit pull requests**
- 📖 **Improve documentation**
- 🌍 **Translate to other languages**

---

## ❤️ Support This Project

GDOLS Panel is free and open-source. Your support helps keep this project alive!

### ☕ Buy Me a Coffee

[![Ko-Fi](https://img.shields.io/badge/Ko--Fi-Buy%20Me%20a%20Coffee-ff5f5f?style=for-the-badge&logo=ko-fi)](https://ko-fi.com/godimyid/goal?g=0)

### Other Ways to Support

- ⭐ [Star on GitHub](https://github.com/godimyid/gdols-panel)
- 🐛 [Report Issues](https://github.com/godimyid/gdols-panel/issues)
- 💡 [Feature Requests](https://github.com/godimyid/gdols-panel/discussions)
- 📢 [Spread the Word](#)

**Every contribution helps!** 🙏

---

## 📜 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- **OpenLiteSpeed Team** - Excellent web server
- **PHP Community** - Powerful language
- **Ubuntu Community** - Great OS platform
- **All Contributors** - Thank you for your support!

---

## 📞 Support & Community

- **Documentation**: [https://github.com/godimyid/gdols-panel](https://github.com/godimyid/gdols-panel)
- **Issues**: [https://github.com/godimyid/gdols-panel/issues](https://github.com/godimyid/gdols-panel/issues)
- **Discussions**: [https://github.com/godimyid/gdols-panel/discussions](https://github.com/godimyid/gdols-panel/discussions)
- **Website**: [https://godi.my.id](https://godi.my.id)
- **Ko-Fi**: [https://ko-fi.com/godimyid/goal?g=0](https://ko-fi.com/godimyid/goal?g=0)

---

## 📊 Version

**Current Version**: 1.1.0  
**Release Date**: December 25, 2025  
**Status**: FHS-Compliant Release

See [CHANGELOG.md](installer/CHANGELOG.md) for version history.

---

## 🔮 Roadmap

### v1.2.0 (Planned)
- Multi-language support
- phpMyAdmin integration
- Enhanced backup encryption
- Performance optimization tools

### v1.3.0 (Planned)
- Two-factor authentication
- Advanced monitoring and alerting
- Plugin system
- Cloud storage improvements

### v2.0.0 (Future)
- Multi-server management
- Cluster support
- Advanced load balancing
- API v2

---

<div align="center">

**Made with ❤️ by [GoDiMyID](https://godi.my.id)**

[⬆ Back to Top](#-gdols-panel)

</div>