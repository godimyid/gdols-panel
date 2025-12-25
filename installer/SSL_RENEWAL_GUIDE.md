# GDOLS Panel - SSL Certificate Renewal Guide

## 📋 Overview

This guide explains how SSL certificate auto-renewal works in GDOLS Panel and how to configure it optimally for your needs.

---

## 🔍 Understanding SSL Renewal

### Important Concepts

Let's Encrypt SSL certificates have a **90-day validity period** (3 months). However, the **renewal check interval** is different from the certificate validity.

| Parameter | Value | Description |
|-----------|-------|-------------|
| **Certificate Validity** | 90 days | How long the SSL certificate is valid |
| **Renewal Check Interval** | Configurable | How often to check if renewal is needed |
| **Renewal Window** | 30 days before expiry | Certificate auto-renews when 30 days or less remaining |

### How Auto-Renewal Works

```
Day 1:     SSL Certificate Issued (Valid for 90 days)
Day 60:    Check #1 - 30 days remaining → Auto-renew triggered
Day 61:    New certificate issued (Reset to 90 days)
Day 90:    Old certificate would have expired (but already renewed)
```

**Key Points:**
- ✅ Certbot checks periodically based on your cron schedule
- ✅ Certificate ONLY renews when ≤30 days remaining
- ✅ Checking more frequently does NOT renew more often
- ✅ Certbot is smart - it won't renew unnecessarily

---

## ⚙️ Default Configuration

### Current Settings (GDOLS Panel v1.1.0)

**Config File:** `/etc/gdols/gdols.conf`

```php
'ssl' => [
    'lets_encrypt' => [
        'enabled' => false,
        'email' => 'admin@example.com',
        'domains' => ['panel.example.com'],
        'renew_before' => 12, // hours before expiration (for manual checks)
    ],
    'auto_renewal' => [
        'enabled' => true,
        'check_interval' => 86400, // 24 hours in seconds
    ],
],
```

**Cron Job:** Set via `SSLManager.php`

```bash
# Runs daily at 3:00 AM
0 3 * * * /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1
```

---

## 🎯 Recommended Configurations

### Option 1: Daily Check (Recommended) ✅

**Best for:** Most use cases, optimal balance of safety and efficiency

```bash
# Cron: Every day at 3:00 AM
0 3 * * * /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1

# Config:
'check_interval' => 86400,  // 24 hours
```

**Advantages:**
- ✅ Safe - 30-day renewal window gives plenty of time
- ✅ Efficient - Only checks once per day
- ✅ Low resource usage - Runs during low-traffic hours
- ✅ Reliable - 30 opportunities to renew before expiry

**Resource Impact:** Minimal (1 check per day, ~1 second)

### Option 2: Twice Daily (Let's Encrypt Default)

**Best for:** Maximum reliability, critical systems

```bash
# Cron: Every 12 hours (00:00 and 12:00)
0 */12 * * * /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1

# Config:
'check_interval' => 43200,  // 12 hours
```

**Advantages:**
- ✅ Very safe - 2 checks per day
- ✅ Let's Encrypt recommended
- ✅ 60 opportunities to renew before expiry

**Resource Impact:** Still minimal (2 checks per day)

### Option 3: Weekly Check (Minimalist)

**Best for:** Resource-constrained servers, non-critical sites

```bash
# Cron: Every Sunday at 3:00 AM
0 3 * * 0 /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1

# Config:
'check_interval' => 604800,  // 7 days
```

**Advantages:**
- ✅ Lowest resource usage
- ✅ Still safe (30-day window)

**Disadvantages:**
- ⚠️ Only 4 opportunities per month
- ⚠️ If server down that day, might miss renewal

---

## 🛠️ How to Change Configuration

### Method 1: Update Config File

```bash
# Edit configuration
sudo nano /etc/gdols/gdols.conf

# Find the ssl.auto_renewal section and update:
'auto_renewal' => [
    'enabled' => true,
    'check_interval' => 86400,  // Change this value
],

# Save with Ctrl+X, Y, Enter
```

**Check Interval Values:**
- `3600` = 1 hour
- `21600` = 6 hours
- `43200` = 12 hours
- `86400` = 24 hours (1 day) ✅ Recommended
- `604800` = 7 days
- `2592000` = 30 days

### Method 2: Update Cron Job Directly

```bash
# Edit crontab
sudo crontab -e

# Find the certbot renew line and update:
# For daily (recommended):
0 3 * * * /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1

# For twice daily:
0 */12 * * * /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1

# For weekly:
0 3 * * 0 /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1

# Save with Ctrl+X, Y, Enter (if using nano)
```

### Method 3: Remove and Re-add Cron Job

```bash
# Remove existing cron job
sudo crontab -l | grep -v certbot | sudo crontab -

# Add new cron job (example: daily at 3 AM)
(crontab -l 2>/dev/null; echo "0 3 * * * /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1") | sudo crontab -
```

---

## 🧪 Testing Renewal Configuration

### Check Current Cron Jobs

```bash
# List all cron jobs
sudo crontab -l

# Filter for certbot jobs
sudo crontab -l | grep certbot
```

### Check SSL Renewal Log

```bash
# View renewal log
sudo tail -f /var/log/ssl-renewal.log

# View last 50 lines
sudo tail -50 /var/log/ssl-renewal.log
```

### Test Certificate Renewal Manually

```bash
# Check certificate status
sudo certbot certificates

# Simulate renewal (dry-run)
sudo certbot renew --dry-run

# Force renewal (for testing)
sudo certbot renew --force-renewal
```

### Check Certificate Expiry

```bash
# Check specific certificate
sudo certbot certificates

# Or with OpenSSL
echo | openssl s_client -connect your-domain.com:443 2>/dev/null | openssl x509 -noout -dates

# Calculate days remaining
whois your-domain.com | grep "Expiry Date"
```

---

## 📊 Monitoring Renewal

### View Certificate Information

```bash
# List all certificates
sudo certbot certificates

# Output example:
# Certificate Name: example.com
#   Domains: example.com www.example.com
#   Expiry Date: 2025-03-25 (VALID: 89 days)
#   Certificate Path: /etc/letsencrypt/live/example.com/fullchain.pem
```

### Check Auto-Renewal Status

```bash
# Check if cron job exists
sudo crontab -l | grep certbot

# Check last renewal log
sudo tail -20 /var/log/ssl-renewal.log

# Expected output if recently checked:
# - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
# Processing certificate for example.com
# - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
# Certificate not yet due for renewal; no action taken.
# - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
```

### Set Up Renewal Notifications (Optional)

```bash
# Create a script to check and notify
sudo nano /usr/local/bin/check-ssl-expiry.sh
```

Add this content:

```bash
#!/bin/bash
# Check SSL certificates expiring soon

EXPIRY_THRESHOLD=30  # days

for cert in /etc/letsencrypt/live/*/; do
    domain=$(basename "$cert")
    expiry=$(openssl x509 -enddate -noout -in "$cert/cert.pem" -date | cut -d= -f2)
    expiry_days=$((($(date -d "$expiry" +%s) - $(date +%s)) / 86400))
    
    if [ $expiry_days -le $EXPIRY_THRESHOLD ]; then
        echo "WARNING: SSL certificate for $domain expires in $expiry_days days"
        # Add email notification here if desired
    fi
done
```

Make it executable and add to cron:

```bash
sudo chmod +x /usr/local/bin/check-ssl-expiry.sh

# Add to cron (weekly check)
0 3 * * 0 /usr/local/bin/check-ssl-expiry.sh >> /var/log/ssl-check.log 2>&1
```

---

## ❌ Common Issues and Troubleshooting

### Issue 1: Certificate Not Renewing

**Symptoms:** Certificate expires even though auto-renewal is configured

**Possible Causes:**
1. Cron job not set up correctly
2. Certbot not installed
3. Web server configuration issue
4. Certificate permissions problem

**Solutions:**

```bash
# Check if cron job exists
sudo crontab -l | grep certbot

# Check if certbot is installed
which certbot

# Test renewal manually
sudo certbot renew --dry-run

# Check web server is running
sudo systemctl status lsws  # or apache2/nginx

# Check certificate permissions
ls -la /etc/letsencrypt/live/
```

### Issue 2: "Too Many Certificates" Error

**Symptoms:** Let's Encrypt rate limit error

**Cause:** Let's Encrypt limits certificates per domain (50 per week)

**Solution:**

```bash
# Wait for rate limit to expire (1 week)
# Or use staging environment for testing:
sudo certbot certonly --staging

# Check rate limits:
curl https://letsencrypt.org/docs/rate-limits/
```

### Issue 3: Cron Job Not Running

**Symptoms:** Renewals not happening, log file empty

**Diagnosis:**

```bash
# Check if cron service is running
sudo systemctl status cron

# Check cron logs
sudo grep CRON /var/log/syslog | tail -20

# Verify crontab
sudo crontab -l
```

**Solution:**

```bash
# Restart cron service
sudo systemctl restart cron

# Re-add cron job
(crontab -l 2>/dev/null; echo "0 3 * * * /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1") | sudo crontab -
```

---

## 🔒 Security Best Practices

### 1. Use Strong Email for Notifications

```bash
# In /etc/gdols/gdols.conf
'lets_encrypt' => [
    'email' => 'admin@your-domain.com',  // Use real email
],
```

### 2. Keep Certbot Updated

```bash
# Update certbot regularly
sudo apt update && sudo apt install --only-upgrade certbot
```

### 3. Monitor Certificate Status

```bash
# Add to monitoring system
# Check certificate expiry daily
# Alert if < 30 days remaining
```

### 4. Backup Certificates

```bash
# Create backup script
sudo nano /usr/local/bin/backup-ssl-certs.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/ssl-certs"
DATE=$(date +%Y%m%d)

mkdir -p "$BACKUP_DIR"
tar -czf "$BACKUP_DIR/ssl-certs-$DATE.tar.gz" /etc/letsencrypt/

# Keep last 30 days
find "$BACKUP_DIR" -name "ssl-certs-*.tar.gz" -mtime +30 -delete
```

```bash
# Make executable and schedule
sudo chmod +x /usr/local/bin/backup-ssl-certs.sh
(crontab -l 2>/dev/null; echo "0 4 * * * /usr/local/bin/backup-ssl-certs.sh") | sudo crontab -
```

---

## 📚 Additional Resources

### Useful Commands

```bash
# Certificate management
sudo certbot certificates                    # List all certificates
sudo certbot renew                           # Renew due certificates
sudo certbot renew --dry-run                 # Test renewal
sudo certbot renew --force-renewal           # Force renewal

# Certificate info
openssl x509 -in /path/to/cert.pem -text -noout  # View certificate details
openssl s_client -connect domain.com:443          # Check live certificate

# Cron management
sudo crontab -l                              # List cron jobs
sudo crontab -e                              # Edit cron jobs
sudo systemctl status cron                   # Check cron service
```

### Configuration Files

- **SSL Configuration:** `/etc/gdols/gdols.conf`
- **Certificates:** `/etc/letsencrypt/live/`
- **Renewal Log:** `/var/log/ssl-renewal.log`
- **Crontab:** `sudo crontab -e`

### External Documentation

- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [Certbot Documentation](https://certbot.eff.org/docs/)
- [Let's Encrypt Rate Limits](https://letsencrypt.org/docs/rate-limits/)
- [OpenLiteSpeed SSL Setup](https://docs.litespeedtech.com/litespeed-web-server/admin/ssl-configuration/)

---

## 📝 Summary

### Recommended Configuration ✅

| Setting | Value | Why |
|---------|-------|-----|
| **Check Interval** | 24 hours (86400s) | Balance of safety & efficiency |
| **Cron Schedule** | `0 3 * * *` | Runs at 3 AM daily (low traffic) |
| **Renewal Window** | 30 days | Let's Encrypt standard |
| **Log Location** | `/var/log/ssl-renewal.log` | Easy troubleshooting |

### Key Takeaways

1. ✅ **Certificate validity = 90 days** (Let's Encrypt standard)
2. ✅ **Check interval = configurable** (recommend 24 hours)
3. ✅ **Auto-renewal happens automatically** when ≤30 days remaining
4. ✅ **Checking more frequently does NOT renew more often**
5. ✅ **Daily check is optimal** - safe, efficient, and reliable

### Quick Setup Command

```bash
# Set optimal renewal schedule (daily at 3 AM)
echo "0 3 * * * /usr/bin/certbot renew --quiet --no-self-upgrade >> /var/log/ssl-renewal.log 2>&1" | sudo crontab -

# Verify
sudo crontab -l | grep certbot

# Test
sudo certbot renew --dry-run
```

---

**Last Updated:** December 25, 2025  
**Version:** 1.1.0  
**Maintained By:** GDOLS Panel Team