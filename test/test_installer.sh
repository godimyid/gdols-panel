#!/bin/bash
##############################################################################
# GDOLS Panel - Installer Test Script
# Description: Validate installer without actual installation
# Version: 1.0.0
##############################################################################

set -e

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

INSTALLER_PATH="../installer/install.sh"

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  GDOLS Panel - Installer Validation Test${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

# Test 1: Check file exists
echo -e "${YELLOW}[TEST 1]${NC} Checking if installer file exists..."
if [ -f "$INSTALLER_PATH" ]; then
    echo -e "${GREEN}✓ PASS${NC} - Installer file found"
else
    echo -e "${RED}✗ FAIL${NC} - Installer file not found"
    exit 1
fi

# Test 2: Syntax check
echo -e "\n${YELLOW}[TEST 2]${NC} Checking bash syntax..."
if bash -n "$INSTALLER_PATH" 2>&1; then
    echo -e "${GREEN}✓ PASS${NC} - No syntax errors"
else
    echo -e "${RED}✗ FAIL${NC} - Syntax errors found"
    exit 1
fi

# Test 3: Check required functions
echo -e "\n${YELLOW}[TEST 3]${NC} Checking required functions..."
REQUIRED_FUNCTIONS=(
    "print_banner"
    "check_root"
    "detect_os"
    "install_dependencies"
    "install_openlitespeed"
    "install_php"
    "install_mariadb"
    "install_redis"
    "create_directory_structure"
    "copy_application_files"
    "setup_permissions"
    "setup_configuration"
    "setup_database"
    "setup_systemd_service"
    "configure_openlitespeed"
    "fix_static_files"
    "setup_cron_jobs"
    "main"
)

MISSING_FUNCTIONS=0
for func in "${REQUIRED_FUNCTIONS[@]}"; do
    if grep -q "^${func}()" "$INSTALLER_PATH"; then
        echo -e "${GREEN}  ✓${NC} $func"
    else
        echo -e "${RED}  ✗${NC} $func - NOT FOUND"
        MISSING_FUNCTIONS=$((MISSING_FUNCTIONS + 1))
    fi
done

if [ $MISSING_FUNCTIONS -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - All required functions found"
else
    echo -e "${RED}✗ FAIL${NC} - $MISSING_FUNCTIONS functions missing"
    exit 1
fi

# Test 4: Check variables
echo -e "\n${YELLOW}[TEST 4]${NC} Checking required variables..."
REQUIRED_VARS=(
    "APP_NAME"
    "APP_VERSION"
    "INSTALL_DIR"
    "CONFIG_DIR"
    "LOG_DIR"
    "SERVICE_NAME"
)

MISSING_VARS=0
for var in "${REQUIRED_VARS[@]}"; do
    if grep -q "^${var}=" "$INSTALLER_PATH"; then
        echo -e "${GREEN}  ✓${NC} $var"
    else
        echo -e "${RED}  ✗${NC} $var - NOT FOUND"
        MISSING_VARS=$((MISSING_VARS + 1))
    fi
done

if [ $MISSING_VARS -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - All required variables found"
else
    echo -e "${RED}✗ FAIL${NC} - $MISSING_VARS variables missing"
    exit 1
fi

# Test 5: Check for web server detection
echo -e "\n${YELLOW}[TEST 5]${NC} Checking web server auto-detection..."
if grep -q "WEB_SERVER=" "$INSTALLER_PATH" && \
   grep -q "nobody:nogroup" "$INSTALLER_PATH" && \
   grep -q "www-data:www-data" "$INSTALLER_PATH"; then
    echo -e "${GREEN}✓ PASS${NC} - Web server detection implemented"
else
    echo -e "${RED}✗ FAIL${NC} - Web server detection incomplete"
    exit 1
fi

# Test 6: Check for static files fix
echo -e "\n${YELLOW}[TEST 6]${NC} Checking static files troubleshooting..."
if grep -q "fix_static_files()" "$INSTALLER_PATH" && \
   grep -q "nobody:nogroup" "$INSTALLER_PATH" && \
   grep -q "chown -R" "$INSTALLER_PATH"; then
    echo -e "${GREEN}✓ PASS${NC} - Static files fix implemented"
else
    echo -e "${RED}✗ FAIL${NC} - Static files fix incomplete"
    exit 1
fi

# Test 7: Check for OpenLiteSpeed vhost config
echo -e "\n${YELLOW}[TEST 7]${NC} Checking OpenLiteSpeed vhost configuration..."
if grep -q "configure_openlitespeed()" "$INSTALLER_PATH" && \
   grep -q "/usr/local/lsws/vhosts/gdols-panel" "$INSTALLER_PATH" && \
   grep -q "ln -sf" "$INSTALLER_PATH"; then
    echo -e "${GREEN}✓ PASS${NC} - OpenLiteSpeed vhost configuration found"
else
    echo -e "${RED}✗ FAIL${NC} - OpenLiteSpeed vhost configuration incomplete"
    exit 1
fi

# Test 8: Check for Saweria support
echo -e "\n${YELLOW}[TEST 8]${NC} Checking Saweria integration..."
if grep -q "saweria.co/godi" "$INSTALLER_PATH"; then
    echo -e "${GREEN}✓ PASS${NC} - Saweria support link found"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - Saweria support link not found (optional)"
fi

# Test 9: Check executable permissions
echo -e "\n${YELLOW}[TEST 9]${NC} Checking file permissions..."
PERMS=$(stat -c %a "$INSTALLER_PATH" 2>/dev/null || echo "000")
if [ "$PERMS" = "755" ] || [ "$PERMS" = "644" ]; then
    echo -e "${GREEN}✓ PASS${NC} - File has correct permissions ($PERMS)"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - File has permissions $PERMS (should be 755 or 644)"
fi

# Test 10: Check file size
echo -e "\n${YELLOW}[TEST 10]${NC} Checking file size..."
SIZE=$(stat -c%s "$INSTALLER_PATH" 2>/dev/null || echo "0")
if [ "$SIZE" -gt 20000 ]; then
    echo -e "${GREEN}✓ PASS${NC} - File size is reasonable ($SIZE bytes)"
else
    echo -e "${RED}✗ FAIL${NC} - File size too small ($SIZE bytes)"
    exit 1
fi

# Summary
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✓ ALL TESTS PASSED${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo "Installer is ready for deployment!"
echo "File: $INSTALLER_PATH"
echo "Size: $SIZE bytes"
echo ""

exit 0
