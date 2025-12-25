# GDOLS Panel - Quick Installation Guide

## 🚀 One-Line Installation (Ubuntu 24.04 LTS)

The easiest way to install GDOLS Panel:

```bash
wget https://github.com/godimyid/gdols-panel/raw/main/installer/install.sh && sudo bash install.sh
```

That's it! The installer will handle everything automatically.

---

## 📋 Requirements

- Ubuntu 24.04 LTS (or compatible Debian-based system)
- 2GB RAM minimum (4GB recommended)
- 20GB free disk space
- Root or sudo access

---

## 🔧 Manual Installation

If you prefer manual installation or the automated installer fails:

### Step 1: Install Dependencies

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip software-properties-common
```

### Step 2: Install OpenLiteSpeed

```bash
wget -qO - https://repo.litespeed.sh | sudo bash
sudo apt install -y openlitespeed
sudo systemctl start lsws
sudo systemctl enable lsws
```

### Step 3: Install PHP 8.3

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-redis php8.3-curl php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip php8.3-bcmath php8.3-intl php8.3-json
```

### Step 4: Install MariaDB

```bash
sudo apt install -y mariadb-server mariadb-client
sudo systemctl start mysql
sudo systemctl enable mysql
sudo mysql_secure_installation
```

### Step 5: Install Redis

```bash
sudo apt install -y redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

### Step 6: Download and Install GDOLS Panel

```bash
cd /tmp
wget https://github.com/godimyid/gdols-panel/archive/refs/heads/main.zip
unzip main.zip
cd gdols-panel-main

# Run installer
sudo bash installer/install.sh
```

### Step 7: Start GDOLS Panel

```bash
sudo systemctl start gdols-panel
sudo systemctl enable gdols-panel
```

### Step 8: Access the Panel

Open your browser:
```
http://your-server-ip:8088
```

---

## ✅ Verification

Check if everything is running:

```bash
# Check services
sudo systemctl status gdols-panel
sudo systemctl status lsws
sudo systemctl status mysql
sudo systemctl status redis-server

# Check versions
php -v
mysql --version
redis-cli --version
cat /opt/gdols-panel/VERSION
```

---

## 🔧 Troubleshooting

### Issue: Permission Denied

```bash
chmod +x install.sh
sudo bash install.sh
```

### Issue: PHP Installation Failed

```bash
# Set non-interactive mode
export DEBIAN_FRONTEND=noninteractive
sudo apt update
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-*
```

### Issue: Service Won't Start

```bash
# Check logs
sudo journalctl -u gdols-panel -n 50
sudo tail -f /var/log/gdols/panel.log

# Check configuration
sudo php -l /etc/gdols/gdols.conf
```

---

## 📚 Complete Documentation

For detailed information, see:

- [INSTALL.md](INSTALL.md) - Complete installation guide
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Troubleshooting guide
- [README.md](README.md) - Project overview
- [SUPPORT.md](SUPPORT.md) - Support information

---

## 🆘 Getting Help

If you encounter issues:

1. Check [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
2. Search [GitHub Issues](https://github.com/godimyid/gdols-panel/issues)
3. Ask in [GitHub Discussions](https://github.com/godimyid/gdols-panel/discussions)
4. Support the project:
   - [Saweria (Indonesia)](https://saweria.co/godi)
   - [Ko-Fi (International)](https://ko-fi.com/godimyid/goal?g=0)

---

**Version:** 1.1.0  
**Last Updated:** December 25, 2025  
**OS:** Ubuntu 24.04 LTS