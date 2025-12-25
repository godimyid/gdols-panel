# GDOLS Panel - Login Credentials Guide

## 📋 Overview

This guide provides complete information about login credentials for GDOLS Panel and OpenLiteSpeed WebAdmin after installation.

---

## 🔐 Default Login Credentials

### GDOLS Panel Web Interface

| Parameter | Value | Description |
|-----------|-------|-------------|
| **URL** | `http://YOUR_SERVER_IP:8088` | GDOLS Panel web interface |
| **Default Username** | `admin` | Default administrator account |
| **Default Password** | `admin123` | Default password (CHANGE IMMEDIATELY!) |
| **Port** | `8088` | OpenLiteSpeed default HTTP port |

**⚠️ IMPORTANT:** Change the default password immediately after first login!

### OpenLiteSpeed WebAdmin Console

| Parameter | Value | Description |
|-----------|-------|-------------|
| **URL** | `http://YOUR_SERVER_IP:7080` | OpenLiteSpeed WebAdmin interface |
| **Default Username** | `admin` | Default WebAdmin account |
| **Default Password** | `CHANGE_THIS` | Default password (CHANGE IMMEDIATELY!) |
| **Port** | `7080` | WebAdmin default port |

**⚠️ IMPORTANT:** You MUST change the WebAdmin password before using in production!

---

## 🗄️ Database Credentials

Database credentials are auto-generated during installation and saved to:

```
/etc/gdols/.credentials.txt
```

### Database Connection Details

| Parameter | Value |
|-----------|-------|
| **Database Name** | `gdols_panel` |
| **Username** | `gdols_user` |
| **Password** | Auto-generated (16 characters) |
| **Host** | `localhost` |
| **Port** | `3306` |

### View Database Password

```bash
# Method 1: View credentials file
sudo cat /etc/gdols/.credentials.txt

# Method 2: Extract from config
sudo grep "'password'" /etc/gdols/gdols.conf | grep -oP "' => '\K[^']+"

# Method 3: View full config
sudo nano /etc/gdols/gdols.conf
```

---

## 🚀 First Login Steps

### Step 1: Access GDOLS Panel

1. Open your browser and navigate to:
   ```
   http://YOUR_SERVER_IP:8088
   ```

2. You should see the GDOLS Panel login page

3. Enter default credentials:
   - **Username:** `admin`
   - **Password:** `admin123`

4. Click **Login**

### Step 2: Change Admin Password

⚠️ **DO THIS IMMEDIATELY!**

1. After logging in, go to **Settings** → **Account**

2. Click **Change Password**

3. Enter your new password:
   - **Current Password:** `admin123`
   - **New Password:** Your secure password
   - **Confirm Password:** Your secure password

4. Click **Update Password**

5. You'll be logged out and need to login with your new password

### Step 3: Change OpenLiteSpeed WebAdmin Password

1. Access WebAdmin console:
   ```
   http://YOUR_SERVER_IP:7080
   ```

2. Login with:
   - **Username:** `admin`
   - **Password:** `CHANGE_THIS`

3. Go to **Actions** → **Password**

4. Enter new password:
   - **Current Password:** `CHANGE_THIS`
   - **New Password:** Your secure password
   - **Confirm Password:** Your secure password

5. Click **Save**

6. Update `/etc/gdols/gdols.conf`:
   ```bash
   sudo nano /etc/gdols/gdols.conf
   ```

7. Find and update:
   ```php
   'openlitespeed' => [
       'admin_username' => 'admin',
       'admin_password' => 'YOUR_NEW_PASSWORD',  // Update this
       'admin_port' => 7080,
   ],
   ```

---

## 🔧 How to Reset Forgotten Passwords

### Reset GDOLS Panel Admin Password

#### Method 1: Via MySQL Command Line

```bash
# Connect to MySQL
sudo mysql -u root

# Use GDOLS database
USE gdols_panel;

# Generate new password hash (example: newpassword123)
SELECT PASSWORD('newpassword123');

# Or use PHP to generate proper hash:
php -r "echo password_hash('newpassword123', PASSWORD_DEFAULT);"

# Copy the hash and update user password
UPDATE users 
SET password = '$2y$10$YOUR_HASH_HERE' 
WHERE username = 'admin';

# Exit MySQL
EXIT;

# Login with new password
# Username: admin
# Password: newpassword123
```

#### Method 2: Via PHP Script

```bash
# Create password reset script
sudo nano /tmp/reset_admin_password.php
```

Add this content:

```php
<?php
// Database configuration
$host = 'localhost';
$db   = 'gdols_panel';
$user = 'gdols_user';
$pass = 'YOUR_DB_PASSWORD'; // Get from /etc/gdols/.credentials.txt

// New password
$newPassword = 'newpassword123';

// Connect to database
$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Hash new password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update admin password
$sql = "UPDATE users SET password = ? WHERE username = 'admin'";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $hashedPassword);

if ($stmt->execute()) {
    echo "Password reset successful!\n";
    echo "Username: admin\n";
    echo "New Password: $newPassword\n";
} else {
    echo "Error: " . $stmt->error . "\n";
}

$stmt->close();
$mysqli->close();
?>
```

Run the script:

```bash
php /tmp/reset_admin_password.php

# Delete script after use
sudo rm /tmp/reset_admin_password.php
```

### Reset OpenLiteSpeed WebAdmin Password

#### Method 1: Via Command Line

```bash
# Generate encrypted password
sudo /usr/local/lsws/admin/fcgi-bin/admin_php* 
# Or use: /usr/local/lsws/bin/admin_php

# Edit WebAdmin password file
sudo nano /usr/local/lsws/admin/conf/htpasswd
```

Replace the encrypted password hash with your new one.

#### Method 2: Restart with Default Password

```bash
# Stop WebAdmin
sudo /usr/local/lsws/bin/lswsctrl stop

# Reset to default
sudo rm -f /usr/local/lsws/admin/conf/htpasswd

# Start WebAdmin
sudo /usr/local/lsws/bin/lswsctrl start

# Login with: admin / (no password or check logs)
```

---

## 🔒 Security Best Practices

### 1. Change All Default Passwords Immediately

```bash
# Checklist after installation:
□ GDOLS Panel admin password (admin123)
□ OpenLiteSpeed WebAdmin password (CHANGE_THIS)
□ Database password (auto-generated, save securely)
□ System root password (if weak)
```

### 2. Use Strong Passwords

**Requirements:**
- Minimum 12 characters
- Mix of uppercase, lowercase, numbers
- Special characters: !@#$%^&*
- Not based on dictionary words

**Example Strong Password:**
```
P@ssw0rd!2024#GDOLS
```

**Use password generator:**
```bash
# Generate 16-character secure password
openssl rand -base64 16 | tr -d "=+/" | cut -c1-16

# Or use pwgen
sudo apt install pwgen
pwgen -s 16 1
```

### 3. Enable Two-Factor Authentication (Future)

GDOLS Panel will support 2FA in future versions. Enable when available.

### 4. Limit Login Attempts

Default configuration:
- Maximum attempts: 5
- Lockout duration: 15 minutes
- IP-based rate limiting

### 5. Use HTTPS in Production

```bash
# Install Let's Encrypt SSL certificate
sudo apt install certbot

# Generate certificate
sudo certbot certonly --standalone -d panel.yourdomain.com

# Configure OpenLiteSpeed to use SSL
# Update /etc/gdols/gdols.conf with certificate paths
```

---

## 📊 Credentials Location Reference

### Where Credentials Are Stored

| Credential Type | Location | Permissions |
|----------------|----------|-------------|
| **GDOLS Config** | `/etc/gdols/gdols.conf` | `600` (root:root) |
| **Database Password** | `/etc/gdols/.credentials.txt` | `600` (root:root) |
| **Admin Password** | Database: `users` table | Hashed (bcrypt) |
| **WebAdmin Password** | `/usr/local/lsws/admin/conf/htpasswd` | `600` |
| **Session Keys** | `/etc/gdols/gdols.conf` | `600` |

### View All Credentials

```bash
# Database credentials
sudo cat /etc/gdols/.credentials.txt

# GDOLS Panel config (contains all settings)
sudo nano /etc/gdols/gdols.conf

# OpenLiteSpeed WebAdmin
sudo cat /usr/local/lsws/admin/conf/htpasswd
```

---

## 🛡️ Securing Your Installation

### After Installation Checklist

```bash
# 1. Change all default passwords
□ GDOLS Panel admin password
□ OpenLiteSpeed WebAdmin password
□ Database user password

# 2. Remove default users if not needed
□ Remove any test users

# 3. Configure firewall
□ Allow only necessary ports (80, 443, 8088, 7080)
□ Block unauthorized access

# 4. Enable SSL/TLS
□ Install Let's Encrypt certificate
□ Force HTTPS redirect
□ Update all URLs to https://

# 5. Set up regular backups
□ Database backups
□ Configuration backups
□ SSL certificate backups

# 6. Monitor logs
□ Authentication logs
□ Access logs
□ Error logs
```

### Secure File Permissions

```bash
# Fix permissions if needed
sudo chmod 600 /etc/gdols/gdols.conf
sudo chmod 600 /etc/gdols/.credentials.txt
sudo chmod 640 /var/log/gdols/*
sudo chmod 750 /etc/gdols

# Ensure correct ownership
sudo chown root:root /etc/gdols/gdols.conf
sudo chown root:root /etc/gdols/.credentials.txt
```

---

## 🔍 Troubleshooting Login Issues

### Issue 1: Cannot Login to GDOLS Panel

**Symptoms:** "Invalid credentials" error

**Possible Causes:**
1. Wrong username/password
2. Account locked (too many failed attempts)
3. Database connection issue
4. Service not running

**Solutions:**

```bash
# 1. Check service status
sudo systemctl status gdols-panel

# 2. Check database connection
sudo mysql -u gdols_user -p gdols_panel

# 3. Verify user exists in database
sudo mysql -u gdols_user -p gdols_panel -e "SELECT * FROM users WHERE username='admin';"

# 4. Check for locked account
sudo mysql -u gdols_user -p gdols_panel -e "SELECT username, status, login_attempts FROM users WHERE username='admin';"

# 5. Reset locked account
sudo mysql -u gdols_user -p gdols_panel -e "UPDATE users SET status='active', login_attempts=0 WHERE username='admin';"
```

### Issue 2: Cannot Login to OpenLiteSpeed WebAdmin

**Symptoms:** "Login failed" error

**Possible Causes:**
1. Wrong password
2. WebAdmin service not running
3. Port 7080 blocked by firewall

**Solutions:**

```bash
# 1. Check WebAdmin status
sudo systemctl status lsws

# 2. Check if port is listening
sudo netstat -tlnp | grep 7080

# 3. Check firewall
sudo ufw status
sudo ufw allow 7080/tcp

# 4. Reset WebAdmin password
sudo /usr/local/lsws/admin/fcgi-bin/admin_php*

# 5. Restart WebAdmin
sudo systemctl restart lsws
```

### Issue 3: Database Connection Failed

**Symptoms:** "Database connection error" in panel

**Solutions:**

```bash
# 1. Check MySQL/MariaDB status
sudo systemctl status mysql

# 2. Test database connection
mysql -u gdols_user -p gdols_panel

# 3. Verify credentials
sudo cat /etc/gdols/.credentials.txt

# 4. Update config if needed
sudo nano /etc/gdols/gdols.conf

# 5. Restart panel service
sudo systemctl restart gdols-panel
```

---

## 📝 Quick Reference Card

### Default Credentials (After Installation)

```
┌─────────────────────────────────────────────────────────┐
│  GDOLS Panel                                            │
├─────────────────────────────────────────────────────────┤
│  URL:      http://YOUR_IP:8088                          │
│  Username: admin                                         │
│  Password: admin123  ⚠ CHANGE IMMEDIATELY!             │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  OpenLiteSpeed WebAdmin                                  │
├─────────────────────────────────────────────────────────┤
│  URL:      http://YOUR_IP:7080                          │
│  Username: admin                                         │
│  Password: CHANGE_THIS  ⚠ CHANGE IMMEDIATELY!         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  Database                                               │
├─────────────────────────────────────────────────────────┤
│  Host:      localhost                                   │
│  Database:  gdols_panel                                 │
│  Username:  gdols_user                                   │
│  Password:  [Auto-generated]                             │
│  Saved in:  /etc/gdols/.credentials.txt                  │
└─────────────────────────────────────────────────────────┘
```

### Essential Commands

```bash
# View all credentials
sudo cat /etc/gdols/.credentials.txt

# Edit GDOLS config
sudo nano /etc/gdols/gdols.conf

# Check service status
sudo systemctl status gdols-panel
sudo systemctl status lsws

# Restart services
sudo systemctl restart gdols-panel
sudo systemctl restart lsws

# View logs
sudo journalctl -u gdols-panel -f
sudo tail -f /var/log/gdols/panel.log
```

---

## 🎯 Summary

### Immediate Actions After Installation

1. ✅ **Login to GDOLS Panel** (http://YOUR_IP:8088)
2. ✅ **Change admin password** immediately
3. ✅ **Change OpenLiteSpeed WebAdmin password** immediately
4. ✅ **Save database credentials** from `/etc/gdols/.credentials.txt`
5. ✅ **Configure firewall** to protect ports
6. ✅ **Enable SSL/TLS** for production use

### Security Reminders

- ⚠️ **NEVER** use default passwords in production
- ⚠️ **ALWAYS** use strong, unique passwords
- ⚠️ **ENABLE** SSL/TLS as soon as possible
- ⚠️ **BACKUP** credentials securely
- ⚠️ **MONITOR** access logs regularly
- ⚠️ **UPDATE** GDOLS Panel regularly

---

**Last Updated:** December 25, 2025  
**Version:** 1.1.0  
**Maintained By:** GDOLS Panel Team