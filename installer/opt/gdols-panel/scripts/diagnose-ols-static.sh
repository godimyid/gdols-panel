#!/bin/bash
##############################################################################
# GDOLS Panel - OpenLiteSpeed Static Files Diagnostic Script
# Description: Comprehensive diagnostic and fix for static file serving issues
# Version: 1.1.0
# Author: GDOLS Panel Team
# License: MIT
##############################################################################

set -e

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/opt/gdols-panel"
VHOST_DIR="/usr/local/lsws/vhosts/gdols-panel"
PUBLIC_DIR="$INSTALL_DIR/public"
ASSETS_DIR="$PUBLIC_DIR/assets"

print_banner() {
    echo ""
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}     ${MAGENTA}GDOLS Panel - OLS Static Files Diagnostic${NC}              ${CYAN}║${NC}"
    echo -e "${CYAN}║${NC}                      Version ${GREEN}1.1.0${NC}                                  ${CYAN}║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

print_section() {
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

# ============================================================================
# DIAGNOSTIC FUNCTIONS
# ============================================================================

check_file_existence() {
    print_section "1. CHECKING FILE EXISTENCE"

    local all_exists=true

    # Check CSS file
    if [ -f "$ASSETS_DIR/css/style.css" ]; then
        SIZE=$(stat -c%s "$ASSETS_DIR/css/style.css")
        print_success "CSS file exists: $ASSETS_DIR/css/style.css ($SIZE bytes)"
    else
        print_error "CSS file NOT found: $ASSETS_DIR/css/style.css"
        all_exists=false
    fi

    # Check JS file
    if [ -f "$ASSETS_DIR/js/app.js" ]; then
        SIZE=$(stat -c%s "$ASSETS_DIR/js/app.js")
        print_success "JS file exists: $ASSETS_DIR/js/app.js ($SIZE bytes)"
    else
        print_error "JS file NOT found: $ASSETS_DIR/js/app.js"
        all_exists=false
    fi

    # Check images directory
    if [ -d "$ASSETS_DIR/img" ]; then
        IMG_COUNT=$(find "$ASSETS_DIR/img" -type f | wc -l)
        print_success "Images directory exists: $ASSETS_DIR/img ($IMG_COUNT files)"
    else
        print_warning "Images directory not found: $ASSETS_DIR/img"
    fi

    if [ "$all_exists" = true ]; then
        print_success "All critical files exist"
        return 0
    else
        print_error "Some critical files are missing"
        return 1
    fi
}

check_permissions() {
    print_section "2. CHECKING FILE PERMISSIONS"

    local issues=0

    # Check public directory permissions
    PUB_PERMS=$(stat -c%a "$PUBLIC_DIR" 2>/dev/null || echo "000")
    PUB_OWNER=$(stat -c%U "$PUBLIC_DIR" 2>/dev/null || echo "0")
    PUB_GROUP=$(stat -c%G "$PUBLIC_DIR" 2>/dev/null || echo "0")

    print_info "Public directory:"
    echo "   Permissions: $PUB_PERMS"
    echo "   Owner: $PUB_OWNER (should be nobody for OLS)"
    echo "   Group: $PUB_GROUP (should be nogroup for OLS)"

    if [ "$PUB_PERMS" != "755" ]; then
        print_warning "Public directory permissions should be 755, currently $PUB_PERMS"
        issues=$((issues + 1))
    fi

    if [ "$PUB_OWNER" != "nobody" ]; then
        print_warning "Public directory owner should be nobody, currently $PUB_OWNER"
        issues=$((issues + 1))
    fi

    # Check assets directory
    AS_PERMS=$(stat -c%a "$ASSETS_DIR" 2>/dev/null || echo "000")
    AS_OWNER=$(stat -c%U "$ASSETS_DIR" 2>/dev/null || echo "0")

    print_info "Assets directory:"
    echo "   Permissions: $AS_PERMS"
    echo "   Owner: $AS_OWNER"

    if [ "$AS_PERMS" != "755" ]; then
        print_warning "Assets permissions should be 755, currently $AS_PERMS"
        issues=$((issues + 1))
    fi

    if [ "$AS_OWNER" != "nobody" ]; then
        print_warning "Assets owner should be nobody, currently $AS_OWNER"
        issues=$((issues + 1))
    fi

    # Test file accessibility by nobody user
    print_info "Testing file accessibility as 'nobody' user..."
    if sudo -u nobody test -r "$ASSETS_DIR/css/style.css"; then
        print_success "CSS file readable by 'nobody'"
    else
        print_error "CSS file NOT readable by 'nobody'"
        issues=$((issues + 1))
    fi

    if sudo -u nobody test -r "$ASSETS_DIR/js/app.js"; then
        print_success "JS file readable by 'nobody'"
    else
        print_error "JS file NOT readable by 'nobody'"
        issues=$((issues + 1))
    fi

    if [ $issues -eq 0 ]; then
        print_success "All permissions are correct"
        return 0
    else
        print_error "Found $issues permission issues"
        return 1
    fi
}

check_symlink() {
    print_section "3. CHECKING VHOST SYMLINK"

    if [ ! -d "$VHOST_DIR" ]; then
        print_error "Virtual host directory NOT found: $VHOST_DIR"
        return 1
    fi

    print_info "Virtual host directory exists: $VHOST_DIR"

    # Check html link
    if [ -L "$VHOST_DIR/html" ]; then
        TARGET=$(readlink "$VHOST_DIR/html")
        print_success "Symlink exists: html -> $TARGET"

        if [ "$TARGET" = "$PUBLIC_DIR" ]; then
            print_success "Symlink points to correct location"
        else
            print_error "Symlink points to WRONG location: $TARGET (should be $PUBLIC_DIR)"
            return 1
        fi

        # Check if target is accessible
        if [ -e "$VHOST_DIR/html" ]; then
            print_success "Symlink target is accessible"
        else
            print_error "Symlink target is NOT accessible (broken symlink)"
            return 1
        fi
    elif [ -d "$VHOST_DIR/html" ]; then
        print_error "'html' is a DIRECTORY, not a symlink"
        print_info "This is the problem - OpenLiteSpeed expects a symlink"
        return 1
    else
        print_warning "'html' does not exist"
        return 1
    fi

    return 0
}

check_vhost_config() {
    print_section "4. CHECKING VHOST CONFIGURATION"

    local config_file="$VHOST_DIR/vhconf.conf"

    if [ ! -f "$config_file" ]; then
        print_error "VHost config file NOT found: $config_file"
        print_info "Creating proper vhost configuration..."
        create_vhost_config
        return $?
    fi

    print_success "VHost config file exists: $config_file"

    # Check if config has /assets/ context
    if grep -q "context /assets/" "$config_file"; then
        print_success "VHost config has /assets/ context"
    else
        print_warning "VHost config missing /assets/ context"
    fi

    # Check if config has /css/ context
    if grep -q "context /css/" "$config_file"; then
        print_success "VHost config has /css/ context"
    else
        print_warning "VHost config missing /css/ context"
    fi

    # Check if config has /js/ context
    if grep -q "context /js/" "$config_file"; then
        print_success "VHost config has /js/ context"
    else
        print_warning "VHost config missing /js/ context"
    fi

    # Show current config
    print_info "Current vhost configuration:"
    echo "----------------------------------------"
    cat "$config_file"
    echo "----------------------------------------"

    return 0
}

check_ols_status() {
    print_section "5. CHECKING OPENLITESPEED STATUS"

    if systemctl is-active --quiet lsws; then
        print_success "OpenLiteSpeed is running"
    else
        print_error "OpenLiteSpeed is NOT running"
        return 1
    fi

    # Check OLS version
    OLS_VERSION=$(/usr/local/lsws/bin/openlitespeed -v 2>/dev/null | head -1 || echo "Unknown")
    print_info "OpenLiteSpeed version: $OLS_VERSION"

    # Check if OLS is listening on port 8088
    if netstat -tlnp 2>/dev/null | grep -q ":8088"; then
        print_success "OpenLiteSpeed listening on port 8088"
    else
        print_warning "OpenLiteSpeed NOT listening on port 8088"
    fi

    return 0
}

test_http_access() {
    print_section "6. TESTING HTTP ACCESS"

    local server_ip=$(hostname -I | awk '{print $1}')

    print_info "Testing static file access..."

    # Test CSS file
    echo -n "   CSS file (style.css): "
    HTTP_CSS=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8088/assets/css/style.css" 2>/dev/null || echo "000")
    if [ "$HTTP_CSS" = "200" ]; then
        echo -e "${GREEN}HTTP $HTTP_CSS - OK${NC}"
    else
        echo -e "${RED}HTTP $HTTP_CSS - FAILED${NC}"
    fi

    # Test JS file
    echo -n "   JS file (app.js): "
    HTTP_JS=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8088/assets/js/app.js" 2>/dev/null || echo "000")
    if [ "$HTTP_JS" = "200" ]; then
        echo -e "${GREEN}HTTP $HTTP_JS - OK${NC}"
    else
        echo -e "${RED}HTTP $HTTP_JS - FAILED${NC}"
    fi

    # Test main page
    echo -n "   Main page (index.html): "
    HTTP_INDEX=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8088/" 2>/dev/null || echo "000")
    if [ "$HTTP_INDEX" = "200" ]; then
        echo -e "${GREEN}HTTP $HTTP_INDEX - OK${NC}"
    else
        echo -e "${RED}HTTP $HTTP_INDEX - FAILED${NC}"
    fi

    # Get actual error response for CSS
    if [ "$HTTP_CSS" != "200" ]; then
        print_info "CSS file error response:"
        curl -s "http://localhost:8088/assets/css/style.css" 2>/dev/null | head -5
    fi

    if [ "$HTTP_CSS" = "200" ] && [ "$HTTP_JS" = "200" ]; then
        print_success "All static files accessible via HTTP"
        return 0
    else
        print_error "Some static files NOT accessible via HTTP"
        return 1
    fi
}

# ============================================================================
# FIX FUNCTIONS
# ============================================================================

fix_permissions() {
    print_section "APPLYING PERMISSION FIXES"

    print_info "Setting correct ownership..."
    chown -R nobody:nogroup "$PUBLIC_DIR"
    chown -R nobody:nogroup "$VHOST_DIR"

    print_info "Setting correct permissions..."
    chmod -R 755 "$PUBLIC_DIR"
    chmod -R 755 "$VHOST_DIR"

    print_success "Permissions fixed"
}

fix_symlink() {
    print_section "FIXING VHOST SYMLINK"

    # Remove directory or broken symlink
    if [ -d "$VHOST_DIR/html" ] && [ ! -L "$VHOST_DIR/html" ]; then
        print_warning "Removing directory 'html'..."
        rm -rf "$VHOST_DIR/html"
    elif [ -L "$VHOST_DIR/html" ] && [ ! -e "$VHOST_DIR/html" ]; then
        print_warning "Removing broken symlink..."
        rm -f "$VHOST_DIR/html"
    fi

    # Create proper symlink
    print_info "Creating symlink: html -> $PUBLIC_DIR"
    ln -sf "$PUBLIC_DIR" "$VHOST_DIR/html"

    # Verify
    if [ -L "$VHOST_DIR/html" ]; then
        TARGET=$(readlink "$VHOST_DIR/html")
        print_success "Symlink created: html -> $TARGET"
    else
        print_error "Failed to create symlink"
        return 1
    fi

    return 0
}

create_vhost_config() {
    print_info "Creating proper vhost configuration..."

    cat > "$VHOST_DIR/vhconf.conf" << 'EOF'
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
  extraHeaders            <<<END_extraHeaders
Cache-Control: public, max-age=31536000
  END_extraHeaders
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

    print_success "VHost configuration created"

    # Set proper ownership
    chown nobody:nogroup "$VHOST_DIR/vhconf.conf"
    chmod 644 "$VHOST_DIR/vhconf.conf"

    return 0
}

restart_lsws() {
    print_section "RESTARTING OPENLITESPEED"

    print_info "Restarting OpenLiteSpeed..."
    if systemctl restart lsws; then
        print_success "OpenLiteSpeed restarted successfully"

        # Wait for service to be fully ready
        sleep 3
        return 0
    else
        print_error "Failed to restart OpenLiteSpeed"
        return 1
    fi
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================

main() {
    print_banner
    check_root

    # Run diagnostics
    local exit_code=0

    check_file_existence || exit_code=$((exit_code + 1))
    check_permissions || exit_code=$((exit_code + 1))
    check_symlink || exit_code=$((exit_code + 1))
    check_vhost_config || exit_code=$((exit_code + 1))
    check_ols_status || exit_code=$((exit_code + 1))
    test_http_access || exit_code=$((exit_code + 1))

    # Show summary
    echo ""
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}  DIAGNOSTIC SUMMARY${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
    echo ""

    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ All checks passed! Static files should be working.${NC}"
        echo ""
    else
        echo -e "${RED}✗ Found $exit_code issue(s) that need to be fixed.${NC}"
        echo ""
        echo -e "${YELLOW}Do you want to apply automatic fixes? (y/N)${NC}"
        read -r -p "> " response

        if [[ $response =~ ^[Yy]$ ]]; then
            echo ""
            print_info "Applying fixes..."
            echo ""

            fix_permissions
            fix_symlink
            create_vhost_config
            restart_lsws

            echo ""
            print_success "Fixes applied! Testing again..."
            echo ""

            # Test again
            sleep 2
            test_http_access

            echo ""
            echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
            echo -e "${CYAN}  FINAL VERIFICATION${NC}"
            echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
            echo ""

            local server_ip=$(hostname -I | awk '{print $1}')
            echo -e "Test in browser:"
            echo -e "  ${YELLOW}http://$server_ip:8088/assets/css/style.css${NC}"
            echo -e "  ${YELLOW}http://$server_ip:8088/assets/js/app.js${NC}"
            echo ""
        else
            echo ""
            print_info "Fixes cancelled. You can apply them manually:"
            echo "  sudo chown -R nobody:nogroup $PUBLIC_DIR"
            echo "  sudo chmod -R 755 $PUBLIC_DIR"
            echo "  sudo rm -rf $VHOST_DIR/html"
            echo "  sudo ln -sf $PUBLIC_DIR $VHOST_DIR/html"
            echo "  sudo systemctl restart lsws"
            echo ""
        fi
    fi

    echo -e "${CYAN}═══════════════════════════════════════════════════════════════════════════${NC}"
    echo ""
}

# Run main
main "$@"
