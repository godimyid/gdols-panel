#!/bin/bash
##############################################################################
# GDOLS Panel - Fix Incomplete Installation
# Description: Complete installation that was interrupted or failed
# Version: 1.0.0
##############################################################################

set -e

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
APP_NAME="GDOLS Panel"
APP_VERSION="1.1.0"
INSTALL_DIR="/opt/gdols-panel"
CONFIG_DIR="/etc/gdols"
LOG_DIR="/var/log/gdols"
RUNTIME_DIR="/var/lib/gdols"
SERVICE_NAME="gdols-panel"

print_banner() {
    echo ""
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}     ${MAGENTA}GDOLS PANEL - Installation Fix Script${NC}                   ${CYAN}║${NC}"
    echo -e "${CYAN}║${NC}                      Version ${GREEN}${APP_VERSION}${NC}                                  ${CYAN}║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

print_step() {
    echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

check_root() {
    if [ "$EUID" -ne 0 ]; then
        print_error "This script must be run as root or with sudo"
        exit 1
    fi
}

check_current_installation() {
    print_step "Checking Current Installation Status"

    local ISSUES_FOUND=0

    # Check if application files exist
    if [ -d "$INSTALL_DIR" ]; then
        print_success "Application directory exists: $INSTALL_DIR"
    else
        print_error "Application directory NOT found: $INSTALL_DIR"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    fi

    # Check if config file exists
    if [ -f "$CONFIG_DIR/gdols.conf" ]; then
        print_success "Configuration file exists: $CONFIG_DIR/gdols.conf"
    else
        print_error "Configuration file NOT found: $CONFIG_DIR/gdols.conf"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    fi

    # Check if systemd service exists
    if [ -f "/etc/systemd/system/${SERVICE_NAME}.service" ]; then
        print_success "Systemd service exists: ${SERVICE_NAME}.service"
    else
        print_warning "Systemd service NOT found: ${SERVICE_NAME}.service"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    fi

    # Check if OpenLiteSpeed vhost exists
    if [ -d "/usr/local/lsws/vhosts/gdols-panel" ]; then
        print_success "OpenLiteSpeed vhost exists"
    else
        print_warning "OpenLiteSpeed vhost NOT found"
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
    fi

    echo ""
    if [ $ISSUES_FOUND -eq 0 ]; then
        print_success "Installation appears complete!"
        echo ""
        read -p "Do you want to run verification and fixes anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            print_info "Exiting. Installation is complete."
            exit 0
        fi
    else
        print_warning "Found $ISSUES_FOUND issue(s) that need to be fixed"
    fi
}

create_config_file() {
    print_step "Creating Configuration File"

    # Generate secure passwords
    DB_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
    ENCRYPTION_KEY=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)

    # Create config directory
    mkdir -p "$CONFIG_DIR"

    # Create configuration file
    cat > "$CONFIG_DIR/gdols.conf" << EOF
<?php
return [
    // =========================================================================
    // APPLICATION
    // =========================================================================
    'app' => [
        'name' => '$APP_NAME',
        'version' => '$APP_VERSION',
        'env' => 'production',
        'debug' => false,
        'url' => 'http://localhost:8088',
    ],

    // =========================================================================
    // DATABASE
    // =========================================================================
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'gdols_panel',
        'username' => 'gdols_user',
        'password' => '$DB_PASSWORD',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // =========================================================================
    // REDIS
    // =========================================================================
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
    ],

    // =========================================================================
    // SECURITY
    // =========================================================================
    'security' => [
        'encryption_key' => '$ENCRYPTION_KEY',
        'session_lifetime' => 120,
    ],

    // =========================================================================
    // LOGGING
    // =========================================================================
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'path' => '$LOG_DIR/panel.log',
    ],

    // =========================================================================
    // SUPPORT
    // =========================================================================
    'support' => [
        'documentation' => 'https://github.com/godimyid/gdols-panel',
        'issues' => 'https://github.com/godimyid/gdols-panel/issues',
        'discussions' => 'https://github.com/godimyid/gdols-panel/discussions',
        'donation_saweria' => 'https://saweria.co/godi',
        'donation_kofi' => 'https://ko-fi.com/godimyid/goal?g=0',
        'author' => 'GoDiMyID',
        'website' => 'https://godi.my.id',
    ],
];
EOF

    # Set secure permissions
    chmod 600 "$CONFIG_DIR/gdols.conf"
    chown root:root "$CONFIG_DIR/gdols.conf"

    print_success "Configuration file created"
    print_warning "Database password: $DB_PASSWORD"
    print_warning "Save this password securely!"
    echo ""

    # Save credentials for reference
    cat > "$CONFIG_DIR/.credentials.txt" << EOF
GDOLS Panel Database Credentials
=================================
Database: gdols_panel
Username: gdols_user
Password: $DB_PASSWORD
Host: localhost
Port: 3306

Generated: $(date)
EOF

    chmod 600 "$CONFIG_DIR/.credentials.txt"
}

setup_database() {
    print_step "Setting Up Database"

    # Check if MySQL/MariaDB is running
    if ! command -v mysql &> /dev/null; then
        print_error "MySQL/MariaDB is not installed"
        return 1
    fi

    # Get database password from config
    DB_PASSWORD=$(grep "'password'" "$CONFIG_DIR/gdols.conf" | grep -oP "' => '\K[^']+" | head -1)

    print_info "Creating database and user..."

    # Create database
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS gdols_panel;" 2>/dev/null || {
        print_error "Failed to create database"
        return 1
    }

    # Create user
    mysql -u root -e "CREATE USER IF NOT EXISTS 'gdols_user'@'localhost' IDENTIFIED BY '$DB_PASSWORD';" 2>/dev/null || {
        print_warning "User creation failed (may already exist)"
    }

    # Grant privileges
    mysql -u root -e "GRANT ALL PRIVILEGES ON gdols_panel.* TO 'gdols_user'@'localhost';" 2>/dev/null || {
        print_warning "Failed to grant privileges"
    }

    # Flush privileges
    mysql -u root -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    print_success "Database setup completed"
}

create_systemd_service() {
    print_step "Creating Systemd Service"

    # Create systemd service file
    cat > /etc/systemd/system/${SERVICE_NAME}.service << EOF
[Unit]
Description=GDOLS Panel Service
After=network.target mysql.service redis-server.service

[Service]
Type=simple
User=root
WorkingDirectory=${INSTALL_DIR}
ExecStart=${INSTALL_DIR}/bin/start
ExecStop=${INSTALL_DIR}/bin/stop
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

    # Reload systemd
    systemctl daemon-reload

    # Enable service
    systemctl enable ${SERVICE_NAME} 2>/dev/null || true

    print_success "Systemd service created and enabled"
}

setup_openlitespeed_vhost() {
    print_step "Setting Up OpenLiteSpeed Virtual Host"

    # Create vhost directory
    mkdir -p /usr/local/lsws/vhosts/gdols-panel/{html,logs,conf}

    # Create symlink to public directory
    ln -sf "$INSTALL_DIR/public" /usr/local/lsws/vhosts/gdols-panel/html

    # Set ownership for OpenLiteSpeed
    chown -R nobody:nogroup /usr/local/lsws/vhosts/gdols-panel
    chmod -R 755 /usr/local/lsws/vhosts/gdols-panel

    # Create vhost configuration
    cat > /usr/local/lsws/vhosts/gdols-panel/vhconf.conf << 'EOF'
docRoot                   $VH_ROOT/html

context / {
  location                $VH_ROOT/html
  allowBrowse             1
  enableScript            1
  addDefaultCharset       off
}

context /assets/ {
  location                $VH_ROOT/html/assets
  allowBrowse             1
  enableScript            0
  addDefaultCharset       off
}

context /css/ {
  location                $VH_ROOT/html/assets/css
  allowBrowse             1
  enableScript            0
  addDefaultCharset       off
}

context /js/ {
  location                $VH_ROOT/html/assets/js
  allowBrowse             1
  enableScript            0
  addDefaultCharset       off
}

context /img/ {
  location                $VH_ROOT/html/assets/img
  allowBrowse             1
  enableScript            0
  addDefaultCharset       off
}
EOF

    print_success "OpenLiteSpeed virtual host configured"
    print_info "Restart OpenLiteSpeed to apply changes"
}

fix_permissions() {
    print_step "Fixing File Permissions"

    # Detect web server
    WEB_SERVER=""
    WEB_USER="nobody"
    WEB_GROUP="nogroup"

    if systemctl is-active --quiet lsws 2>/dev/null || command -v lshttpd &> /dev/null; then
        WEB_SERVER="openlitespeed"
        print_info "Detected OpenLiteSpeed"
    elif systemctl is-active --quiet apache2 2>/dev/null; then
        WEB_SERVER="apache"
        WEB_USER="www-data"
        WEB_GROUP="www-data"
        print_info "Detected Apache"
    elif systemctl is-active --quiet nginx 2>/dev/null; then
        WEB_SERVER="nginx"
        WEB_USER="www-data"
        WEB_GROUP="www-data"
        print_info "Detected Nginx"
    fi

    # Fix application permissions
    chmod -R 755 "$INSTALL_DIR/public"
    chmod -R 750 "$INSTALL_DIR/storage"
    chmod +x "$INSTALL_DIR/bin"/* 2>/dev/null || true

    # Set ownership based on web server
    chown -R ${WEB_USER}:${WEB_GROUP} "$INSTALL_DIR/public"
    chown -R ${WEB_USER}:${WEB_GROUP} "$INSTALL_DIR/storage"

    # Fix log directory
    mkdir -p "$LOG_DIR"
    chmod 750 "$LOG_DIR"
    chown root:adm "$LOG_DIR"

    # Fix runtime directory
    mkdir -p "$RUNTIME_DIR/runtime"
    chmod 750 "$RUNTIME_DIR"
    chmod 750 "$RUNTIME_DIR/runtime"

    print_success "Permissions fixed for $WEB_SERVER"
}

create_logrotate_config() {
    print_step "Creating Logrotate Configuration"

    cat > /etc/logrotate.d/gdols-panel << EOF
$LOG_DIR/*.log {
    daily
    rotate 10
    compress
    delaycompress
    missingok
    notifempty
    create 0640 root root
    sharedscripts
}
EOF

    print_success "Logrotate configuration created"
}

restart_services() {
    print_step "Restarting Services"

    # Restart OpenLiteSpeed
    if systemctl is-active --quiet lsws 2>/dev/null || command -v lshttpd &> /dev/null; then
        print_info "Restarting OpenLiteSpeed..."
        systemctl restart lsws 2>/dev/null || true
        print_success "OpenLiteSpeed restarted"
    fi

    # Restart Redis
    if systemctl is-active --quiet redis-server 2>/dev/null || command -v redis-server &> /dev/null; then
        print_info "Restarting Redis..."
        systemctl restart redis-server 2>/dev/null || true
        print_success "Redis restarted"
    fi

    # Start GDOLS Panel service
    print_info "Starting GDOLS Panel service..."
    systemctl start ${SERVICE_NAME} 2>/dev/null || print_warning "Failed to start service (may need manual check)"
}

verify_installation() {
    print_step "Verifying Installation"

    local CHECKS_PASSED=0
    local TOTAL_CHECKS=0

    # Check 1: Config file
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    if [ -f "$CONFIG_DIR/gdols.conf" ]; then
        print_success "Config file exists"
        CHECKS_PASSED=$((CHECKS_PASSED + 1))
    else
        print_error "Config file missing"
    fi

    # Check 2: Database
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    if mysql -u gdols_user -p"$(grep "'password'" "$CONFIG_DIR/gdols.conf" | grep -oP "' => '\K[^']+" | head -1)" -e "USE gdols_panel;" 2>/dev/null; then
        print_success "Database connection successful"
        CHECKS_PASSED=$((CHECKS_PASSED + 1))
    else
        print_error "Database connection failed"
    fi

    # Check 3: Service
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    if systemctl is-active --quiet ${SERVICE_NAME}; then
        print_success "Service is running"
        CHECKS_PASSED=$((CHECKS_PASSED + 1))
    else
        print_warning "Service not running (may need manual start)"
    fi

    # Check 4: Web server
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    if systemctl is-active --quiet lsws; then
        print_success "OpenLiteSpeed is running"
        CHECKS_PASSED=$((CHECKS_PASSED + 1))
    else
        print_error "OpenLiteSpeed not running"
    fi

    # Check 5: Web access
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8088 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        print_success "Web interface accessible (HTTP $HTTP_CODE)"
        CHECKS_PASSED=$((CHECKS_PASSED + 1))
    else
        print_error "Web interface not accessible (HTTP $HTTP_CODE)"
    fi

    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "  Verification: ${GREEN}$CHECKS_PASSED${NC}/${BLUE}$TOTAL_CHECKS${NC} checks passed"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

display_completion() {
    print_step "Installation Fix Complete!"

    SERVER_IP=$(hostname -I | awk '{print $1}')

    echo ""
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}  SUMMARY${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "  ${GREEN}Installation Directory:${NC} $INSTALL_DIR"
    echo -e "  ${GREEN}Configuration File:${NC}    $CONFIG_DIR/gdols.conf"
    echo -e "  ${GREEN}Database Credentials:${NC}  $CONFIG_DIR/.credentials.txt"
    echo -e "  ${GREEN}Service Name:${NC}          $SERVICE_NAME"
    echo ""
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}  NEXT STEPS${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "  1. ${BLUE}Access the panel:${NC}"
    echo -e "     ${YELLOW}http://$SERVER_IP:8088${NC}"
    echo ""
    echo -e "  2. ${BLUE}Check service status:${NC}"
    echo -e "     ${YELLOW}sudo systemctl status $SERVICE_NAME${NC}"
    echo ""
    echo -e "  3. ${BLUE}View logs:${NC}"
    echo -e "     ${YELLOW}sudo journalctl -u $SERVICE_NAME -f${NC}"
    echo -e "     ${YELLOW}sudo tail -f $LOG_DIR/panel.log${NC}"
    echo ""
    echo -e "  4. ${BLUE}Database credentials:${NC}"
    echo -e "     ${YELLOW}cat $CONFIG_DIR/.credentials.txt${NC}"
    echo ""
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo ""

    print_success "Installation fix completed successfully!"
    print_info "Your GDOLS Panel should now be fully functional"
    echo ""
}

# Main execution
main() {
    print_banner

    check_root
    check_current_installation
    create_config_file
    setup_database
    create_systemd_service
    setup_openlitespeed_vhost
    fix_permissions
    create_logrotate_config
    restart_services
    verify_installation
    display_completion
}

main "$@"
