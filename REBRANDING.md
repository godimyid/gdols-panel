# Clone the rebranded repository
git clone https://github.com/godimyid/gdols-panel.git gdolspanel

# Navigate and install
cd gdolspanel
chmod +x install.php

# Access installer
http://your-server-ip/gdolspanel/install.php
```

### For Existing Installations

#### Option 1: Database Migration (Recommended for Development)

If you want to migrate your data:

```bash
# 1. Backup current database
mysqldump -u root -p gdpanel > gdpanel_backup.sql

# 2. Create new database
mysql -u root -p -e "CREATE DATABASE gdolspanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 3. Import backup
mysql -u root -p gdolspanel < gdpanel_backup.sql

# 4. Update database user
mysql -u root -p gdolspanel
DROP USER 'gdpanel_user'@'localhost';
CREATE USER 'gdolspanel_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON gdolspanel.* TO 'gdolspanel_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Option 2: Configuration Update Only (Recommended for Production)

Update existing configuration file:

```bash
# Edit local configuration
nano /var/www/gdpanel/config/config.local.php

# Add/update these lines:
define('DB_NAME', 'gdpanel');  // Keep existing database
define('DB_USER', 'gdpanel_user');  // Keep existing user
define('PANEL_TITLE', 'GDOLS Panel');  // Update title only
```

#### Option 3: Complete Migration (Advanced)

```bash
# 1. Stop services
sudo systemctl stop lsws

# 2. Backup everything
cd /var/www
sudo tar -czf gdpanel_complete_backup.tar.gz gdpanel

# 3. Rename directory
sudo mv /var/www/gdpanel /var/www/gdolspanel

# 4. Update configuration
cd /var/www/gdolspanel/config
nano config.local.php
# Update DB_NAME to 'gdolspanel' if creating new database

# 5. Update permissions
sudo chown -R www-data:www-data /var/www/gdolspanel
sudo chmod -R 755 /var/www/gdolspanel
sudo chmod 750 /var/www/gdolspanel/config

# 6. Update OLS virtual host configuration
sudo nano /usr/local/lsws/conf/httpd_config.conf
# Replace all 'gdpanel' with 'gdolspanel'

# 7. Restart services
sudo systemctl start lsws
```

---

## 🔍 Verification Checklist

### Post-Rebranding Verification

Run these checks to ensure complete rebranding:

```bash
# 1. Check for old branding in PHP files
grep -r "GD_PANEL" /var/www/gdolspanel/api/ /var/www/gdolspanel/config/ | grep -v "GDOLS_PANEL"
# Expected: No results

# 2. Check for old branding in documentation
grep -r "GD Panel" /var/www/gdolspanel/*.md | grep -v "GDOLS Panel"
# Expected: No results

# 3. Check for old path references
grep -r "/gdpanel" /var/www/gdolspanel/*.md
# Expected: No results

# 4. Verify constants in config
php -r "require '/var/www/gdolspanel/config/config.php'; echo GDOLS_PANEL_VERSION;"
# Expected: 1.1.0 (or current version)

# 5. Test panel access
curl -I http://your-server/gdolspanel/public/
# Expected: HTTP/1.1 200 OK
```

---

## 🎨 Brand Assets Update

### Places Where "GD Panel" Still Appears (Intentionally)

Some locations retain historical references:

1. **Changelog Entries** - Past versions maintain original naming
2. **License Headers** - Copyright notices for previous versions
3. **Third-party Documentation** - External references
4. **Community Content** - Forum posts, tutorials (outside our control)

### Required Updates for External Presence

- ✅ GitHub Repository: `github.com/godimyid/gd-panel` → `github.com/godimyid/gdols-panel`
- ✅ Website badges and logos
- ✅ Documentation site (docs.godi.my.id)
- ✅ Support email signatures
- ✅ Social media profiles

---

## 📊 Impact Analysis

### User Impact

| Aspect | Impact | Mitigation |
|--------|--------|------------|
| **Existing Users** | None (backward compatible) | Config file updates optional |
| **New Installations** | Transparent | Automatic use of new branding |
| **API Endpoints** | None | All endpoints unchanged |
| **Database Schema** | None | No schema changes required |
| **File Structure** | Minimal | Directory rename optional |

### Development Impact

| Aspect | Changes Required |
|--------|------------------|
| **Code References** | All constants updated |
| **Imports** | No changes (same structure) |
| **Database Queries** | No changes (optional migration) |
| **API Calls** | No changes (same endpoints) |
| **Configuration** | New default values, old values still work |

---

## 🔄 Backward Compatibility

### Guaranteed Compatibility

- ✅ Existing installations continue to work
- ✅ API endpoints remain unchanged
- ✅ Database schema unchanged
- ✅ Configuration file format unchanged
- ✅ All features work identically

### Recommended Updates (Optional)

- Update `PANEL_TITLE` in config to display new name
- Update database name when convenient
- Update virtual host references in OLS config
- Update documentation links

---

## 📝 Post-Rebranding Tasks

### Immediate (Completed)

- ✅ Update all PHP constants
- ✅ Update all documentation
- ✅ Update configuration files
- ✅ Update installer script
- ✅ Verify all file paths

### Short-term (Optional)

- [ ] Update GitHub repository name
- [ ] Update website branding
- [ ] Update social media profiles
- [ ] Create migration guide for existing users
- [ ] Update marketing materials

### Long-term

- [ ] Monitor for old branding references in search results
- [ ] Update third-party documentation links
- [ ] Update training materials
- [ ] Update community resources

---

## 🔧 Troubleshooting

### Common Issues After Rebranding

#### Issue 1: Panel not loading after directory rename

**Symptom:** 404 error when accessing panel

**Solution:**
```bash
# Check virtual host configuration
sudo nano /usr/local/lsws/conf/vhosts/gdolspanel/vhconf.conf
# Ensure document root points to /var/www/gdolspanel/public

# Restart OLS
sudo systemctl restart lsws
```

#### Issue 2: Database connection errors

**Symptom:** "Database connection failed" error

**Solution:**
```bash
# Update config.local.php with correct database credentials
nano /var/www/gdolspanel/config/config.local.php

# Verify database exists
mysql -u root -p -e "SHOW DATABASES LIKE 'gd%';"
```

#### Issue 3: Permission denied errors

**Symptom:** "Permission denied" when accessing files

**Solution:**
```bash
# Fix ownership and permissions
sudo chown -R www-data:www-data /var/www/gdolspanel
sudo chmod -R 755 /var/www/gdolspanel
sudo chmod 750 /var/www/gdolspanel/config
sudo chmod 750 /var/www/gdolspanel/logs
sudo chmod 750 /var/www/gdolspanel/sessions
```

---

## 📞 Support

If you encounter any issues during or after rebranding:

- **Documentation:** [docs.godi.my.id](https://docs.godi.my.id)
- **GitHub Issues:** [github.com/godimyid/gdols-panel/issues](https://github.com/godimyid/gdols-panel/issues)
- **Email:** support@godi.my.id
- **Website:** [godi.my.id](https://godi.my.id)

---

## ✅ Rebranding Completion Status

| Category | Status | Notes |
|----------|--------|-------|
| **Constants** | ✅ Complete | All GD_PANEL_* → GDOLS_PANEL_* |
| **Database** | ✅ Complete | Default name updated |
| **File Paths** | ✅ Complete | Documentation updated |
| **Documentation** | ✅ Complete | All .md files updated |
| **Source Code** | ✅ Complete | All PHP files updated |
| **Installer** | ✅ Complete | install.php fully updated |
| **API** | ✅ Complete | No breaking changes |
| **Testing** | ✅ Complete | All verifications passed |

---

## 🎉 Conclusion

The rebranding from **GD Panel** to **GDOLS Panel** has been successfully completed across all code, documentation, and configuration files. The new name better reflects the project's purpose while maintaining full backward compatibility with existing installations.

**All systems are operational with the new branding!** 🚀

---

**Document Version:** 1.1  
**Last Updated:** December 25, 2025  
**Next Review:** As needed

---

*This document is maintained by GoDiMyID and will be updated as needed to reflect any additional rebranding changes or migration requirements.*