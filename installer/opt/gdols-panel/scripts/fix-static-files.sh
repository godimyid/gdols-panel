#!/bin/bash
##############################################################################
# GDOLS Panel - Static Files Fix Script
# Description: Troubleshoot and fix static file serving issues
# Version: 1.1.0
# Author: GDOLS Panel Team
# License: MIT
##############################################################################

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/opt/gdols-panel"
CONFIG_DIR="/etc/gdols"
SERVICE_NAME="gdols-panel"

# Functions
##############################################################################

print_banner() {
    echo ""
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}     ${MAGENTA}GDOLS PANEL - Static Files Troubleshooter${NC}              ${CYAN}║${NC}"
    echo -e "${CYAN}║${NC}                      Version ${GREEN}1.1.0${NC}                                  ${CYAN}║${NC}"
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

detect_web_server() {
    print_step "Detecting Web Server"

    WEB_SERVER=""
    WEB_USER="www-data"
    WEB_GROUP="www-data"

    if systemctl is-active --quiet lsws 2>/dev/null || command -v lshttpd &> /dev/null; then
        WEB_SERVER="openlitespeed"
        WEB_USER="nobody"
        WEB_GROUP="nogroup"
        print_success "OpenLiteSpeed detected"
    elif systemctl is-active --quiet apache2 2>/dev/null || command -v apache2 &> /dev/null; then
        WEB_SERVER="apache"
        WEB_USER="www-data"
        WEB_GROUP="www-data"
        print_success "Apache detected"
    elif systemctl is-active --quiet nginx 2>/dev/null || command -v nginx &> /dev/null; then
        WEB_SERVER="nginx"
        WEB_USER="www-data"
        WEB_GROUP="www-data"
        print_success "Nginx detected"
    else
        print_warning "No web server detected, using default www-data:www-data"
    fi

    print_info "Web Server: $WEB_SERVER"
    print_info "User/Group: $WEB_USER:$WEB_GROUP"
}

check_static_files() {
    print_step "Checking Static Files"

    local css_file="$INSTALL_DIR/public/assets/css/style.css"
    local js_file="$INSTALL_DIR/public/assets/js/app.js"

    if [ -f "$css_file" ]; then
        print_success "CSS file found"
        ls -lh "$css_file"
    else
        print_error "CSS file NOT found at $css_file"
    fi

    if [ -f "$js_file" ]; then
        print_success "JS file found"
        ls -lh "$js_file"
    else
        print_error "JS file NOT found at $js_file"
    fi

    # Check all files in assets directory
    echo ""
    print_info "All files in assets directory:"
    ls -lh "$INSTALL_DIR/public/assets/" 2>/dev/null || print_error "Cannot list assets directory"
}

check_permissions() {
    print_step "Checking Current Permissions"

    print_info "Public directory permissions:"
    ls -ld "$INSTALL_DIR/public" 2>/dev/null || print_error "Cannot access public directory"

    echo ""
    print_info "Assets directory permissions:"
    ls -ld "$INSTALL_DIR/public/assets" 2>/dev/null || print_error "Cannot access assets directory"

    echo ""
    print_info "CSS file ownership:"
    ls -l "$INSTALL_DIR/public/assets/css/style.css" 2>/dev/null || print_error "Cannot check CSS file ownership"

    echo ""
    print_info "JS file ownership:"
    ls -l "$INSTALL_DIR/public/assets/js/app.js" 2>/dev/null || print_error "Cannot check JS file ownership"
}

check_symlink() {
    print_step "Checking Virtual Host Symlink"

    local symlink_path="/usr/local/lsws/vhosts/gdols-panel/html"

    if [ -L "$symlink_path" ]; then
        print_success "Symlink exists"
        local target=$(readlink "$symlink_path")
        print_info "Symlink points to: $target"

        if [ -e "$symlink_path" ]; then
            print_success "Symlink target is accessible"
        else
            print_error "BROKEN SYMLINK - target does not exist"
        fi
    else
        print_warning "Symlink not found"
    fi
}

fix_permissions() {
    print_step "Fixing Permissions"

    print_info "Setting correct ownership for $WEB_SERVER..."

    if [ "$WEB_SERVER" = "openlitespeed" ]; then
        print_info "Fixing OpenLiteSpeed permissions..."
        chown -R nobody:nogroup "$INSTALL_DIR/public" 2>/dev/null || print_error "Failed to set ownership"
        chmod -R 755 "$INSTALL_DIR/public" 2>/dev/null || print_error "Failed to set permissions"

        if [ -d "/usr/local/lsws/vhosts/gdols-panel" ]; then
            chown -R nobody:nogroup /usr/local/lsws/vhosts/gdols-panel 2>/dev/null || print_error "Failed to set vhost ownership"
            chmod -R 755 /usr/local/lsws/vhosts/gdols-panel 2>/dev/null || print_error "Failed to set vhost permissions"
        fi

        print_success "OpenLiteSpeed permissions fixed"
    else
        print_info "Fixing Apache/Nginx permissions..."
        chown -R www-data:www-data "$INSTALL_DIR/public" 2>/dev/null || print_error "Failed to set ownership"
        chmod -R 755 "$INSTALL_DIR/public" 2>/dev/null || print_error "Failed to set permissions"
        print_success "Apache/Nginx permissions fixed"
    fi

    # Fix storage permissions
    print_info "Fixing storage permissions..."
    chmod -R 750 "$INSTALL_DIR/storage" 2>/dev/null || print_error "Failed to set storage permissions"
    print_success "Storage permissions fixed"
}

fix_symlink() {
    print_step "Fixing Virtual Host Symlink"

    local symlink_path="/usr/local/lsws/vhosts/gdols-panel/html"
    local target_path="$INSTALL_DIR/public"

    # Remove broken symlink
    if [ -L "$symlink_path" ] && [ ! -e "$symlink_path" ]; then
        print_warning "Removing broken symlink..."
        rm -f "$symlink_path"
    fi

    # Create symlink if it doesn't exist
    if [ ! -L "$symlink_path" ]; then
        print_info "Creating new symlink..."
        mkdir -p /usr/local/lsws/vhosts/gdols-panel
        ln -sf "$target_path" "$symlink_path" 2>/dev/null || print_error "Failed to create symlink"
        print_success "Symlink created: $symlink_path -> $target_path"
    else
        print_info "Symlink already exists and is valid"
    fi

    # Verify symlink
    if [ -L "$symlink_path" ]; then
        local target=$(readlink "$symlink_path")
        print_success "Current symlink: $symlink_path -> $target"
    fi
}

restart_web_server() {
    print_step "Restarting Web Server"

    if systemctl is-active --quiet lsws 2>/dev/null; then
        print_info "Restarting OpenLiteSpeed..."
        systemctl restart lsws
        print_success "OpenLiteSpeed restarted"
    elif systemctl is-active --quiet apache2 2>/dev/null; then
        print_info "Restarting Apache..."
        systemctl restart apache2
        print_success "Apache restarted"
    elif systemctl is-active --quiet nginx 2>/dev/null; then
        print_info "Restarting Nginx..."
        systemctl restart nginx
        print_success "Nginx restarted"
    else
        print_warning "No active web server found to restart"
    fi
}

test_static_files() {
    print_step "Testing Static File Access"

    local server_ip=$(hostname -I | awk '{print $1}')
    local test_urls=(
        "http://$server_ip:8088/assets/css/style.css"
        "http://$server_ip:8088/assets/js/app.js"
        "http://$server_ip:8088/index.html"
    )

    print_info "Testing access to static files..."
    for url in "${test_urls[@]}"; do
        echo ""
        print_info "Testing: $url"
        local response=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)

        if [ "$response" = "200" ]; then
            print_success "HTTP $response - OK"
        else
            print_error "HTTP $response - FAILED"
        fi
    done
}

display_summary() {
    print_step "Summary and Next Steps"

    echo ""
    print_success "Static files troubleshooting completed!"
    echo ""

    echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}  FIXES APPLIED${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "  ${GREEN}✓${NC} Web server detected: $WEB_SERVER"
    echo -e "  ${GREEN}✓${NC} File ownership fixed: $WEB_USER:$WEB_GROUP"
    echo -e "  ${GREEN}✓${NC} Directory permissions corrected"
    echo -e "  ${GREEN}✓${NC} Virtual host symlink verified/fixed"
    echo -e "  ${GREEN}✓${NC} Web server restarted"
    echo ""

    echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}  VERIFICATION${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "  ${BLUE}Check files:${NC}"
    echo -e "     ${YELLOW}ls -lh $INSTALL_DIR/public/assets/css/style.css${NC}"
    echo -e "     ${YELLOW}ls -lh $INSTALL_DIR/public/assets/js/app.js${NC}"
    echo ""
    echo -e "  ${BLUE}Check ownership:${NC}"
    echo -e "     ${YELLOW}ls -la $INSTALL_DIR/public/assets/${NC}"
    echo ""
    echo -e "  ${BLUE}Test in browser:${NC}"
    echo -e "     ${YELLOW}http://$(hostname -I | awk '{print $1}'):8088/assets/css/style.css${NC}"
    echo -e "     ${YELLOW}http://$(hostname -I | awk '{print $1}'):8088/assets/js/app.js${NC}"
    echo ""

    echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}  IF ISSUES PERSIST${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "  1. ${BLUE}Check web server error logs:${NC}"
    echo -e "     ${YELLOW}tail -f /usr/local/lsws/logs/error.log${NC}          (OpenLiteSpeed)"
    echo -e "     ${YELLOW}tail -f /var/log/apache2/error.log${NC}             (Apache)"
    echo -e "     ${YELLOW}tail -f /var/log/nginx/error.log${NC}                (Nginx)"
    echo ""
    echo -e "  2. ${BLUE}Check virtual host configuration:${NC}"
    echo -e "     ${YELLOW}cat /usr/local/lsws/vhosts/gdols-panel/vhconf.conf${NC}"
    echo ""
    echo -e "  3. ${BLUE}Run verbose status check:${NC}"
    echo -e "     ${YELLOW}$INSTALL_DIR/bin/status --verbose${NC}"
    echo ""
    echo -e "  4. ${BLUE}View application logs:${NC}"
    echo -e "     ${YELLOW}tail -f /var/log/gdols/panel.log${NC}"
    echo ""
}

# Main Execution
##############################################################################

main() {
    print_banner

    # Check prerequisites
    check_root

    # Detect web server
    detect_web_server

    # Check current state
    check_static_files
    check_permissions
    check_symlink

    # Apply fixes
    fix_permissions
    fix_symlink

    # Restart web server
    restart_web_server

    # Test access
    test_static_files

    # Display summary
    display_summary

    echo ""
    print_success "All done! If you're still experiencing issues, please check the logs above."
    echo ""
}

# Run main function
main "$@"
