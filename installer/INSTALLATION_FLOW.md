# GDOLS Panel - Installation Flowchart

## 📊 Complete Installation Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                         START INSTALLATION                          │
│              (sudo bash install.sh on VPS)                         │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                       PRE-INSTALLATION CHECKS                       │
├─────────────────────────────────────────────────────────────────────┤
│ 1. Check if running as root (sudo required)                         │
│ 2. Detect Operating System (Ubuntu 24.04 LTS)                      │
│ 3. Check for existing installation                                 │
│    └─ If exists: Ask user to reinstall or abort                    │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    INSTALL DEPENDENCIES                             │
├─────────────────────────────────────────────────────────────────────┤
│ Step 1: Install Base Packages                                       │
│   - curl, wget, git, unzip                                         │
│   - software-properties-common                                     │
│   - apt-transport-https, ca-certificates                           │
│                                                                     │
│ Step 2: Install OpenLiteSpeed Web Server                           │
│   - Add LiteSpeed repository                                       │
│   - Install openlitespeed package                                  │
│   - Enable and start lsws service                                  │
│                                                                     │
│ Step 3: Install PHP 8.3 + Extensions                               │
│   - Add ondrej/php PPA repository                                  │
│   - Install PHP 8.3                                                │
│   - Install extensions: mysql, redis, curl, gd, mbstring,          │
│     xml, zip, bcmath, intl, json                                   │
│                                                                     │
│ Step 4: Install MariaDB Database Server                            │
│   - Install mariadb-server and mariadb-client                      │
│   - Set root password (default: 'root')                            │
│   - Enable and start mysql service                                 │
│                                                                     │
│ Step 5: Install Redis Cache Server                                 │
│   - Install redis-server package                                   │
│   - Enable and start redis-server service                          │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    CREATE DIRECTORY STRUCTURE                       │
├─────────────────────────────────────────────────────────────────────┤
│ Application Directories:                                            │
│   /opt/gdols-panel/app/          (Source code)                     │
│   /opt/gdols-panel/bin/          (Executable scripts)              │
│   /opt/gdols-panel/config/       (Config templates)                │
│   /opt/gdols-panel/storage/      (Application data)                │
│   /opt/gdols-panel/public/       (Web UI files)                    │
│   /opt/gdols-panel/logs/         (Internal logs)                   │
│   /opt/gdols-panel/scripts/      (Automation scripts)              │
│                                                                     │
│ System Directories:                                                 │
│   /etc/gdols/                     (Configuration)                  │
│   /var/log/gdols/                 (System logs)                    │
│   /var/lib/gdols/runtime/         (Runtime data)                   │
│   /var/lib/gdols/backups/         (Backup storage)                 │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   COPY APPLICATION FILES                           │
├─────────────────────────────────────────────────────────────────────┤
│ 1. Copy from installer directory:                                  │
│    - API endpoints and classes                                     │
│    - Templates (HTML, PHP)                                         │
│    - Public web files (assets, HTML pages)                         │
│    - Automation scripts (backup, monitoring)                       │
│    - Configuration templates                                       │
│                                                                     │
│ 2. Create VERSION file (1.0.0)                                     │
│                                                                     │
│ 3. Verify all files are copied correctly                           │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   SETUP PERMISSIONS                                │
├─────────────────────────────────────────────────────────────────────┤
│ Application Permissions:                                            │
│   /opt/gdols-panel/         → 755  (rwxr-xr-x)                    │
│   /opt/gdols-panel/bin/     → 755  (executable)                   │
│   /opt/gdols-panel/storage/ → 750  (rwxr-x---)                    │
│   /opt/gdols-panel/public/  → 755  (rwxr-xr-x)                    │
│                                                                     │
│ System Permissions:                                                 │
│   /etc/gdols/                → 750  (rwxr-x---)                    │
│   /var/log/gdols/            → 750  (rwxr-x---)                    │
│   /var/lib/gdols/            → 750  (rwxr-x---)                    │
│                                                                     │
│ Configuration Security:                                             │
│   /etc/gdols/gdols.conf      → 600  (rw-------) root:root         │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   SETUP CONFIGURATION                              │
├─────────────────────────────────────────────────────────────────────┤
│ 1. Copy configuration template to /etc/gdols/gdols.conf            │
│                                                                     │
│ 2. Generate secure database password:                              │
│    - 16-character random password                                  │
│    - Store in /etc/gdols/gdols.conf                                │
│                                                                     │
│ 3. Configuration includes:                                         │
│    - Database settings (host, port, database, user, password)     │
│    - Redis settings (host, port, database)                         │
│    - Security settings (app_key, session, rate limiting)           │
│    - SSL settings (Let's Encrypt configuration)                    │
│    - Backup settings (schedules, storage)                          │
│    - API settings (authentication, rate limits)                    │
│    - Service settings (paths, commands)                            │
│                                                                     │
│ 4. Set secure permissions: chmod 600 /etc/gdols/gdols.conf         │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   SETUP DATABASE                                   │
├─────────────────────────────────────────────────────────────────────┤
│ 1. Create database:                                                 │
│    CREATE DATABASE gdols_panel                                     │
│                                                                     │
│ 2. Create database user:                                           │
│    CREATE USER 'gdols_user'@'localhost'                            │
│    IDENTIFIED BY 'auto_generated_password'                         │
│                                                                     │
│ 3. Grant privileges:                                               │
│    GRANT ALL PRIVILEGES ON gdols_panel.*                           │
│    TO 'gdols_user'@'localhost'                                     │
│    FLUSH PRIVILEGES                                                │
│                                                                     │
│ 4. Update configuration with generated password                    │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   SETUP SYSTEMD SERVICE                            │
├─────────────────────────────────────────────────────────────────────┤
│ 1. Copy service file:                                               │
│    installer/etc/systemd/system/gdols-panel.service                │
│    → /etc/systemd/system/gdols-panel.service                       │
│                                                                     │
│ 2. Service configuration:                                          │
│    - Description: GDOLS Panel Service                              │
│    - After: network.target mysql.service redis-server.service      │
│    - Type: simple                                                  │
│    - User: root                                                    │
│    - WorkingDirectory: /opt/gdols-panel                            │
│    - ExecStart: /opt/gdols-panel/bin/start                        │
│    - ExecStop: /opt/gdols-panel/bin/stop                          │
│    - Restart: always                                               │
│    - WantedBy: multi-user.target                                   │
│                                                                     │
│ 3. Reload systemd: systemctl daemon-reload                         │
│                                                                     │
│ 4. Enable service: systemctl enable gdols-panel                    │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   CONFIGURE OPENLITESPEED                          │
├─────────────────────────────────────────────────────────────────────┤
│ 1. Create virtual host directory:                                  │
│    /usr/local/lsws/vhosts/gdols-panel/                             │
│                                                                     │
│ 2. Create subdirectories:                                          │
│    - html/ (document root)                                         │
│    - logs/ (vhost logs)                                            │
│    - conf/ (vhost configuration)                                   │
│                                                                     │
│ 3. Create symlink:                                                 │
│    /usr/local/lsws/vhosts/gdols-panel/html                         │
│    → /opt/gdols-panel/public                                       │
│                                                                     │
│ 4. Note: OpenLiteSpeed restart required after installation         │
│    systemctl restart lsws                                          │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   SETUP CRON JOBS                                  │
├─────────────────────────────────────────────────────────────────────┤
│ 1. Install backup automation:                                      │
│    - Copy backup-cron.sh to /opt/gdols-panel/scripts/             │
│    - Make executable: chmod +x backup-cron.sh                     │
│                                                                     │
│ 2. Add daily backup cron job:                                      │
│    0 2 * * * /opt/gdols-panel/scripts/backup-cron.sh              │
│            >> /var/log/gdols/backup-cron.log 2>&1                 │
│                                                                     │
│ 3. Schedule: Daily at 2:00 AM                                     │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   POST-INSTALLATION TASKS                          │
├─────────────────────────────────────────────────────────────────────┤
│ 1. Create logrotate configuration:                                 │
│    /etc/logrotate.d/gdols-panel                                    │
│    - Daily rotation                                                │
│    - Keep 10 days                                                  │
│    - Compress old logs                                             │
│                                                                     │
│ 2. Create symlinks for easy access:                                │
│    /opt/gdols-panel/logs/system → /var/log/gdols                  │
│                                                                     │
│ 3. Set up log directories with proper permissions                  │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                   DISPLAY COMPLETION SUMMARY                       │
├─────────────────────────────────────────────────────────────────────┤
│ Installation Summary:                                               │
│   ✓ Installation Directory: /opt/gdols-panel                       │
│   ✓ Configuration File:    /etc/gdols/gdols.conf                  │
│   ✓ Log Directory:         /var/log/gdols                         │
│   ✓ Runtime Directory:     /var/lib/gdols                         │
│   ✓ Service Name:          gdols-panel                            │
│                                                                     │
│ Quick Start Commands:                                               │
│   1. Edit configuration: sudo nano /etc/gdols/gdols.conf          │
│   2. Start service:      sudo systemctl start gdols-panel         │
│   3. Check status:        sudo systemctl status gdols-panel       │
│   4. Restart OLS:         sudo systemctl restart lsws             │
│   5. Access panel:        http://SERVER_IP:8088                   │
│                                                                     │
│ Important Notes:                                                   │
│   ⚠ Update passwords in /etc/gdols/gdols.conf                     │
│   ⚠ Configure SSL certificate with Let's Encrypt                  │
│   ⚠ Restart OpenLiteSpeed to apply web server changes             │
└─────────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────────┐
│                      INSTALLATION COMPLETE                         │
│                                                                     │
│  Next Steps:                                                        │
│  1. Edit configuration and update secure passwords                 │
│  2. Start the GDOLS Panel service                                  │
│  3. Restart OpenLiteSpeed web server                               │
│  4. Access the panel via browser                                   │
│  5. Run initial setup wizard                                       │
│  6. Configure SSL with Let's Encrypt                               │
│  7. Set up automated backup schedules                              │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Manual Installation Flow (Alternative)

```
┌──────────────────────────────────────────────┐
│      Manual Installation (Step-by-Step)      │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 1. System Preparation                         │
│    - apt update && apt upgrade               │
│    - Install base dependencies               │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 2. Install OpenLiteSpeed                     │
│    - wget -qO - https://repo.litespeed.sh    │
│      | bash                                  │
│    - apt install openlitespeed               │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 3. Install PHP 8.3                           │
│    - add-apt-repository ppa:ondrej/php       │
│    - apt install php8.3 php8.3-* extensions  │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 4. Install MariaDB & Redis                   │
│    - apt install mariadb-server              │
│    - apt install redis-server                │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 5. Create Directory Structure                │
│    - mkdir -p /opt/gdols-panel/{app,bin,...} │
│    - mkdir -p /etc/gdols                     │
│    - mkdir -p /var/log/gdols                 │
│    - mkdir -p /var/lib/gdols/runtime         │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 6. Copy Application Files                    │
│    - cp -r app/* /opt/gdols-panel/app/       │
│    - cp -r public/* /opt/gdols-panel/public/ │
│    - cp -r scripts/* /opt/gdols-panel/scripts/│
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 7. Setup Configuration                       │
│    - cp config/gdols.conf /etc/gdols/        │
│    - chmod 600 /etc/gdols/gdols.conf         │
│    - Edit database passwords                 │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 8. Setup Database                            │
│    - mysql -e "CREATE DATABASE gdols_panel"  │
│    - mysql -e "CREATE USER 'gdols_user'..."  │
│    - mysql -e "GRANT ALL ON gdols_panel..."  │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 9. Setup Systemd Service                     │
│    - cp gdols-panel.service                  │
│      /etc/systemd/system/                    │
│    - systemctl daemon-reload                 │
│    - systemctl enable gdols-panel            │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 10. Configure OpenLiteSpeed                  │
│     - ln -s /opt/gdols-panel/public          │
│       /usr/local/lsws/vhosts/gdols-panel/html│
│     - systemctl restart lsws                 │
└──────────────────────────────────────────────┘
                   ↓
┌──────────────────────────────────────────────┐
│ 11. Start Service                            │
│     - systemctl start gdols-panel            │
│     - systemctl status gdols-panel           │
└──────────────────────────────────────────────┘
```

---

## 🎯 Key Decision Points

### During Installation

1. **Existing Installation Found?**
   - Yes → Ask to reinstall or abort
   - No → Continue installation

2. **PHP Already Installed?**
   - Version ≥ 8.3 → Skip PHP installation
   - Version < 8.3 → Warn user but continue
   - Not installed → Install PHP 8.3

3. **MariaDB Already Installed?**
   - Yes → Skip installation, use existing
   - No → Install and setup MariaDB

4. **Redis Already Installed?**
   - Yes → Skip installation, use existing
   - No → Install and start Redis

### After Installation

5. **Service Won't Start?**
   - Check logs: `tail -f /var/log/gdols/panel.log`
   - Verify configuration: `/opt/gdols-panel/bin/status --verbose`
   - Check permissions: `ls -la /opt/gdols-panel/bin/`

6. **Web Interface Not Accessible?**
   - Check OpenLiteSpeed: `systemctl status lsws`
   - Verify symlink: `ls -la /usr/local/lsws/vhosts/gdols-panel/html`
   - Restart OLS: `systemctl restart lsws`

---

## 📊 Installation Time Estimate

| Step | Time Required |
|------|---------------|
| Pre-installation checks | ~30 seconds |
| Install dependencies | 3-5 minutes |
| Install OpenLiteSpeed | 1-2 minutes |
| Install PHP 8.3 | 2-3 minutes |
| Install MariaDB | 1-2 minutes |
| Install Redis | ~30 seconds |
| Create directories | ~10 seconds |
| Copy files | ~30 seconds |
| Setup configuration | ~30 seconds |
| Setup database | ~20 seconds |
| Setup systemd service | ~20 seconds |
| Configure OpenLiteSpeed | ~30 seconds |
| Setup cron jobs | ~20 seconds |
| Post-installation tasks | ~30 seconds |
| **Total** | **10-15 minutes** |

---

## ✅ Installation Verification Checklist

After installation completes, verify:

- [ ] Service is running: `systemctl status gdols-panel`
- [ ] OpenLiteSpeed is running: `systemctl status lsws`
- [ ] Database is accessible: `mysql -u gdols_user -p gdols_panel`
- [ ] Redis is working: `redis-cli ping`
- [ ] Configuration file exists: `/etc/gdols/gdols.conf`
- [ ] Log directory exists: `/var/log/gdols/`
- [ ] Runtime directory exists: `/var/lib/gdols/`
- [ ] Web interface accessible: `curl -I http://localhost:8088`
- [ ] Symlink is correct: `ls -la /usr/local/lsws/vhosts/gdols-panel/html`
- [ ] Cron jobs installed: `crontab -l`

---

## 🔧 Troubleshooting Flow

```
Service Won't Start
    ↓
Check Logs: tail -f /var/log/gdols/panel.log
    ↓
    ├─→ Permission Errors?
    │   └─→ Fix: chmod +x /opt/gdols-panel/bin/*
    │       chmod 600 /etc/gdols/gdols.conf
    │
    ├─→ Database Connection Error?
    │   └─→ Fix: Verify /etc/gdols/gdols.conf
    │       Check MariaDB is running
    │       Test: mysql -u gdols_user -p
    │
    ├─→ Configuration File Missing?
    │   └─→ Fix: cp /opt/gdols-panel/config/gdols.conf.example
    │           /etc/gdols/gdols.conf
    │
    └─→ Port Already in Use?
        └─→ Fix: Check what's using port 8088
            netstat -tulpn | grep 8088
```

---

## 📝 Version Information

- **GDOLS Panel**: 1.1.0
- **Supported OS**: Ubuntu 24.04 LTS (and compatible Debian-based systems)
- **Installer Version**: 1.1.0
- **Last Updated**: December 25, 2025

---

**Document Author**: GDOLS Panel Team  
**License**: MIT License  
**Repository**: https://github.com/godimyid/gdols-panel