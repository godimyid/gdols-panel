# Changelog

All notable changes to GDOLS Panel will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-12-25

### Added
- **FHS-Compliant Directory Structure**: Complete restructuring to follow Linux Filesystem Hierarchy Standard
  - Application code: `/opt/gdols-panel/`
  - Configuration: `/etc/gdols/gdols.conf`
  - System logs: `/var/log/gdols/`
  - Runtime data: `/var/lib/gdols/`
- **Automated Installer Script**: Comprehensive installation automation
  - Auto-install OpenLiteSpeed, PHP 8.3, MariaDB, Redis
  - Automatic dependency resolution
  - FHS directory creation
  - Database and user setup with secure password generation
  - Systemd service installation
  - OpenLiteSpeed virtual host configuration
- **Systemd Service Integration**: Native Linux service management
  - `gdols-panel.service` for automatic startup
  - Auto-restart on failure
  - Boot-time enablement
- **Service Management Scripts**: CLI tools for service control
  - `/opt/gdols-panel/bin/start` - Start panel service
  - `/opt/gdols-panel/bin/stop` - Stop panel service
  - `/opt/gdols-panel/bin/restart` - Restart panel service
  - `/opt/gdols-panel/bin/status` - Detailed status checking with verbose mode
- **Enhanced Documentation**: Comprehensive installation and migration guides
  - `INSTALL.md` - Complete installation guide
  - `INSTALLATION_FLOW.md` - Visual flowchart of installation process
  - `FHS_MIGRATION.md` - Migration guide from old structure
  - `README.md` - FHS structure overview
- **Log Rotation**: Automatic log rotation configuration via logrotate
- **Cron Job Integration**: Automated backup scheduling
- **Security Hardening**: 
  - Configuration file isolation (600 permissions)
  - Separation of code and configuration
  - Secure runtime directories (750 permissions)
- **Ubuntu 24.04 LTS Optimization**: Specific support and optimizations for Ubuntu 24.04

### Changed
- **BREAKING**: Directory structure from custom paths to FHS-compliant paths
- **BREAKING**: Configuration location from application directory to `/etc/gdols/gdols.conf`
- **BREAKING**: Log location from application directory to `/var/log/gdols/`
- **BREAKING**: Runtime data location to `/var/lib/gdols/runtime/`
- **Configuration Format**: Improved to comprehensive PHP array-based config with all settings in one file
- **Web Server Configuration**: OpenLiteSpeed virtual host now uses symlink to `/opt/gdols-panel/public`
- **Application Paths**: All internal paths updated to use FHS-compliant structure

### Removed
- Old installation directory structure support
- Legacy configuration files format

### Fixed
- Ubuntu 24.04 LTS compatibility issues with `/var/www` directory
- Service management inconsistencies
- Permission issues in multi-user environments

### Security
- Configuration file now properly isolated with 600 permissions
- Database passwords auto-generated and stored securely
- Runtime directories properly isolated with 750 permissions
- Log directories secured with appropriate permissions

### Migration
- Migration guide provided for users upgrading from previous structure
- Automated migration scripts included in installer
- Backup and rollback procedures documented

### Performance
- Optimized file structure for better I/O performance
- Separated cache and session storage for improved performance
- Log rotation to prevent disk space issues

---

## [1.0.0] - 2025-12-25

### Added
- **Initial Public Release** of GDOLS Panel
- OpenLiteSpeed management interface
- Database management (MariaDB/MySQL)
  - Create/delete databases
  - Create/delete database users
  - Import/export databases
  - Database backups
- PHP extension management
  - Enable/disable PHP extensions
  - View installed extensions
- Virtual host management
  - Create/delete virtual hosts
  - SSL certificate management
  - Let's Encrypt integration
- Redis management
  - Start/stop Redis service
  - Clear cache
  - View Redis stats
- Security features
  - IP whitelist/blacklist
  - Rate limiting
  - Brute force protection
- Backup automation
  - Scheduled backups
  - Multiple storage backends (S3, FTP, SSH)
  - Configurable retention policies
- Web UI with responsive design
- REST API with authentication
- System monitoring dashboard

---

## Version History Summary

| Version | Date | Type | Notes |
|---------|------|------|-------|
| 1.1.0 | 2025-12-25 | Minor | FHS-compliant restructuring, automated installer, systemd integration |
| 1.0.0 | 2025-12-25 | Major | Initial public release |

---

## Migration Notes

### From 1.0.0 to 1.1.0

**Breaking Changes:**
- Directory structure completely changed
- Configuration file location moved
- Log files location moved

**Migration Required:**
- Use provided migration guide: `FHS_MIGRATION.md`
- Backup your installation before upgrading
- Update all custom scripts to use new paths
- Update monitoring tools to use new log location
- Reconfigure web server virtual hosts

**Upgrade Path:**
1. Backup existing installation
2. Run automated installer: `sudo bash install.sh`
3. Follow migration guide for custom configurations
4. Update configuration in `/etc/gdols/gdols.conf`
5. Restart services: `sudo systemctl restart gdols-panel`

---

## Future Releases

### Planned for 1.2.0
- Multi-language support
- phpMyAdmin integration
- Enhanced backup encryption
- Cloud storage integration improvements

### Planned for 1.3.0
- Two-factor authentication
- Advanced monitoring and alerting
- Performance optimization tools
- Plugin system

### Planned for 2.0.0
- Multi-server management
- Cluster support
- Advanced load balancing
- API v2 with breaking changes

---

## Support

For detailed upgrade instructions, see:
- [INSTALL.md](INSTALL.md) - Installation guide
- [FHS_MIGRATION.md](FHS_MIGRATION.md) - Migration from previous versions
- [README.md](README.md) - General information

For issues and questions:
- GitHub Issues: [https://github.com/godimyid/gdols-panel/issues](https://github.com/godimyid/gdols-panel/issues)
- GitHub Discussions: [https://github.com/godimyid/gdols-panel/discussions](https://github.com/godimyid/gdols-panel/discussions)

---

**Note:** This project follows Semantic Versioning. For more information, see [https://semver.org/](https://semver.org/)

---

[1.1.0]: https://github.com/godimyid/gdols-panel/releases/tag/v1.1.0
[1.0.0]: https://github.com/godimyid/gdols-panel/releases/tag/v1.0.0