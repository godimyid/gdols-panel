# GDOLS Panel - Static Files Troubleshooting Guide

## 📋 Overview

This guide specifically addresses **static file serving issues** (CSS, JS, images) that may occur after installing GDOLS Panel with OpenLiteSpeed web server.

---

## 🔍 Problem Identification

### Common Symptoms

1. **Panel loads but looks broken** - No styles, layout issues
2. **Console shows 404 errors** - CSS/JS files not found
3. **Browser returns 404 for /assets/ paths**
4. **Files exist but web server can't serve them**

### Quick Check

```bash
# Check if files exist
ls -lh /opt/gdols-panel/public/assets/css/style.css
ls -lh /opt/gdols-panel/public/assets/js/app.js

# Check ownership
ls -la /opt/gdols-panel/public/assets/

# Test access
curl -I http://YOUR_IP:8088/assets/css/style.css
```

---

## 🎯 Root Cause Analysis

### Why This Happens

1. **Wrong File Ownership** - Files owned by `root:root` instead of `nobody:nogroup`
2. **Broken Symlink** - Virtual host symlink doesn't point to correct location
3. **Missing Context Rules** - OpenLiteSpeed doesn't have rules for `/assets/` directory
4. **Permission Issues** - Files or directories have incorrect permissions

### Web Server Ownership Requirements

| Web Server | Required Ownership | Required Permissions |
|------------|-------------------|---------------------|
| **OpenLiteSpeed** | `nobody:nogroup` | `755` (dirs), `644` (files) |
| **Apache** | `www-data:www-data` | `755` (dirs), `644` (files) |
| **Nginx** | `www-data:www-data` | `755` (dirs), `644` (files) |

---

## 🚀 Solution

### Method 1: Automated Fix (Recommended)

The easiest way to fix static file issues is using the built-in fix script:

```bash
# Quick fix (recommended first step)
sudo /opt/gdols-panel/bin/fix-static-files
```

This script will:
- ✅ Detect your web server automatically
- ✅ Fix file ownership and permissions
- ✅ Repair broken symlinks
- ✅ Restart web server
- ✅ Verify the fix

### Method 2: Detailed Troubleshooting

For comprehensive diagnostics and fixes:

```bash
# Run detailed troubleshooting script
sudo /opt/gdols-panel/scripts/fix-static-files.sh
```

This provides:
- 🔍 Full diagnostics of static files
- 🔍 Web server detection and configuration check
- 🔍 Permission analysis
- 🔍 Symlink verification
- 🔍 HTTP access testing
- 📊 Detailed summary report

### Method 3: Manual Fix

If you prefer to fix it manually:

#### Step 1: Fix Ownership (OpenLiteSpeed)

```bash
# Fix ownership for OpenLiteSpeed
sudo chown -R nobody:nogroup /opt/gdols-panel/public
sudo chown -R nobody:nogroup /usr/local/lsws/vhosts/gdols-panel

# Fix permissions
sudo chmod -R 755 /opt/gdols-panel/public
sudo chmod -R 755 /usr/local/lsws/vhosts/gdols-panel
```

#### Step 2: Fix Virtual Host Symlink

```bash
# Remove broken symlink if exists
sudo rm -f /usr/local/lsws/vhosts/gdols-panel/html

# Create new symlink
sudo mkdir -p /usr/local/lsws/vhosts/gdols-panel
sudo ln -sf /opt/gdols-panel/public /usr/local/lsws/vhosts/gdols-panel/html

# Fix ownership
sudo chown -R nobody:nogroup /usr/local/lsws/vhosts/gdols-panel
```

#### Step 3: Verify and Restart

```bash
# Verify symlink
ls -la /usr/local/lsws/vhosts/gdols-panel/html

# Should show: html -> /opt/gdols-panel/public

# Restart OpenLiteSpeed
sudo systemctl restart lsws

# Check status
sudo systemctl status lsws
```

---

## ✅ Verification

### Check 1: Verify Files Exist

```bash
# Check CSS file
ls -lh /opt/gdols-panel/public/assets/css/style.css

# Should show file size (e.g., 23K)

# Check JS file
ls -lh /opt/gdols-panel/public/assets/js/app.js

# Should show file size (e.g., 51K)
```

### Check 2: Verify Ownership

```bash
# Check ownership
ls -la /opt/gdols-panel/public/assets/

# OpenLiteSpeed should show: nobody nogroup
# Apache/Nginx should show: www-data www-data
```

### Check 3: Verify Symlink

```bash
# Check symlink
readlink /usr/local/lsws/vhosts/gdols-panel/html

# Should output: /opt/gdols-panel/public

# Check if target is accessible
ls -la /usr/local/lsws/vhosts/gdols-panel/html/assets/

# Should list files without errors
```

### Check 4: Test HTTP Access

```bash
# Get server IP
IP=$(hostname -I | awk '{print $1}')

# Test CSS file
curl -I http://$IP:8088/assets/css/style.css

# Should return: HTTP/1.1 200 OK

# Test JS file
curl -I http://$IP:8088/assets/js/app.js

# Should return: HTTP/1.1 200 OK
```

### Check 5: Browser Test

Open your browser and navigate to:

```
http://YOUR_SERVER_IP:8088
```

- Panel should load with proper styling
- No console errors for CSS/JS files
- All visual elements should display correctly

---

## 🔧 Advanced Troubleshooting

### Issue: Files Still Return 404 After Fix

**Check OpenLiteSpeed Configuration:**

```bash
# View virtual host configuration
sudo cat /usr/local/lsws/vhosts/gdols-panel/vhconf.conf

# Should contain context rules for /assets/
```

**Expected Configuration:**

```apache
context /assets/ {
  location                $VH_ROOT/html/assets
  allowBrowse             1
  enableScript            0
  addDefaultCharset       off
}
```

**If missing, add it manually:**

```bash
sudo nano /usr/local/lsws/vhosts/gdols-panel/vhconf.conf
```

Add the context rules and restart OpenLiteSpeed.

### Issue: Permission Denied Errors

```bash
# Check directory permissions
namei -l /opt/gdols-panel/public/assets/css/style.css

# All directories should have at least r-xr-xr-x (755)

# Fix if needed
sudo chmod -R 755 /opt/gdols-panel/public
```

### Issue: Broken Symlink After Reinstall

```bash
# Force recreate symlink
sudo rm -rf /usr/local/lsws/vhosts/gdols-panel
sudo mkdir -p /usr/local/lsws/vhosts/gdols-panel
sudo ln -sf /opt/gdols-panel/public /usr/local/lsws/vhosts/gdols-panel/html
sudo chown -R nobody:nogroup /usr/local/lsws/vhosts/gdols-panel
sudo systemctl restart lsws
```

---

## 📊 Diagnostic Commands

### Full System Check

```bash
# Run comprehensive diagnostics
sudo /opt/gdols-panel/bin/status --verbose
```

### Check Web Server Error Logs

```bash
# OpenLiteSpeed
sudo tail -f /usr/local/lsws/logs/error.log

# Apache
sudo tail -f /var/log/apache2/error.log

# Nginx
sudo tail -f /var/log/nginx/error.log
```

### Check Application Logs

```bash
# Panel logs
sudo tail -f /var/log/gdols/panel.log

# System logs
sudo journalctl -u gdols-panel -f
```

### Test All Static Assets

```bash
# Test all assets
IP=$(hostname -I | awk '{print $1}')

echo "Testing static assets..."
curl -I http://$IP:8088/assets/css/style.css
curl -I http://$IP:8088/assets/js/app.js
curl -I http://$IP:8088/index.html
```

---

## 🛡️ Prevention

### Best Practices

1. **Always use the installer** - Manual setups may miss permission fixes
2. **Run fix script after updates** - If you modify files, run the fix script
3. **Monitor web server logs** - Check for permission issues regularly
4. **Keep ownership correct** - Never change ownership from `nobody:nogroup` for OpenLiteSpeed

### Automated Monitoring

Create a cron job to check file permissions:

```bash
# Edit crontab
sudo crontab -e

# Add this line to check daily at 3 AM
0 3 * * * /opt/gdols-panel/scripts/fix-static-files.sh > /var/log/gdols/fix-static-files.log 2>&1
```

---

## 📞 Getting Help

### Before Asking for Help

1. Run the fix script: `sudo /opt/gdols-panel/bin/fix-static-files`
2. Run diagnostics: `sudo /opt/gdols-panel/bin/status --verbose`
3. Collect logs from above commands
4. Note your web server (OpenLiteSpeed/Apache/Nginx)
5. Check [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for general issues

### Report Issues

If the issue persists after trying all solutions:

1. **GitHub Issues**: [https://github.com/godimyid/gdols-panel/issues](https://github.com/godimyid/gdols-panel/issues)
2. Include diagnostic output
3. Specify your Ubuntu version
4. Mention web server type
5. Include error logs

---

## 📝 Quick Reference

### Essential Commands

```bash
# Quick fix
sudo /opt/gdols-panel/bin/fix-static-files

# Detailed fix
sudo /opt/gdols-panel/scripts/fix-static-files.sh

# Check status
sudo /opt/gdols-panel/bin/status --verbose

# Fix ownership (OpenLiteSpeed)
sudo chown -R nobody:nogroup /opt/gdols-panel/public

# Fix ownership (Apache/Nginx)
sudo chown -R www-data:www-data /opt/gdols-panel/public

# Fix permissions
sudo chmod -R 755 /opt/gdols-panel/public

# Fix symlink
sudo ln -sf /opt/gdols-panel/public /usr/local/lsws/vhosts/gdols-panel/html

# Restart web server
sudo systemctl restart lsws    # OpenLiteSpeed
sudo systemctl restart apache2 # Apache
sudo systemctl restart nginx   # Nginx
```

### File Locations

| Component | Path |
|-----------|------|
| Public Directory | `/opt/gdols-panel/public` |
| CSS Files | `/opt/gdols-panel/public/assets/css/` |
| JS Files | `/opt/gdols-panel/public/assets/js/` |
| VHost Symlink | `/usr/local/lsws/vhosts/gdols-panel/html` |
| Fix Script | `/opt/gdols-panel/bin/fix-static-files` |
| Detailed Script | `/opt/gdols-panel/scripts/fix-static-files.sh` |

---

## 🎯 Summary

**Most Common Cause**: Files owned by `root:root` instead of `nobody:nogroup`

**Quickest Fix**: Run `sudo /opt/gdols-panel/bin/fix-static-files`

**Verification**: Check browser for proper styling, no console errors

**If Still Broken**: Check web server logs and vhost configuration

---

**Last Updated**: December 25, 2025  
**Version**: 1.1.0  
**Maintained By**: GDOLS Panel Team