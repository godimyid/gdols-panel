#!/bin/bash
##############################################################################
# GDOLS Panel - Main Installer Script
# Description: Automated installer for GDOLS Panel on Ubuntu 24.04 LTS
# Version: 1.1.0
# Author: GDOLS Panel Team
# License: MIT
##############################################################################

set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="GDOLS Panel"
APP_VERSION="1.1.0"
APP_NAME_LOWER="gdols-panel"
INSTALL_DIR="/opt/${APP_NAME_LOWER}"
CONFIG_DIR="/etc/gdols"
LOG_DIR="/var/log/gdols"
RUNTIME_DIR="/var/lib/gdols"
BACKUP_DIR="/var/lib/gdols/backups"
SERVICE_NAME="gdols-panel"

# Installer temporary directory
INSTALLER_TEMP="/tmp/gdols-panel-installer"
CURRENT_DIR="$(pwd)"

# Functions
##############################################################################

print_banner() {
    echo ""
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                                                                      ║${NC}"
    echo -e "${CYAN}║${NC}     ${MAGENTA}GDOLS PANEL - OpenLiteSpeed Management Panel${NC}               ${CYAN}║${NC}"
    echo -e "${CYAN}║${NC}                      Version ${GREEN}${APP_VERSION}${NC}                                  ${CYAN}║${NC}"
    echo -e "${CYAN}║                                                                      ║${NC}"
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

detect_os() {
    print_step "Detecting Operating System"

    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        OS_VERSION=$VERSION_ID

        print_success "Detected: $PRETTY_NAME"

        if [ "$OS" = "ubuntu" ]; then
            print_info "Ubuntu detected"
            if [ "$(echo "$OS_VERSION >= 24.04" | bc)" -eq 1 ] 2>/dev/null || [ "$OS_VERSION" = "24.04" ]; then
                print_success "Ubuntu 24.04 LTS detected"
            else
                print_warning "Ubuntu version not 24.04 LTS, but continuing..."
            fi
        else
            print_warning "Non-Ubuntu system detected. Some features may not work."
        fi
    else
        print_error "Cannot detect operating system"
        exit 1
    fi
}

check_existing_installation() {
    print_step "Checking for Existing Installation"

    if [ -d "$INSTALL_DIR" ]; then
        print_warning "GDOLS Panel is already installed at $INSTALL_DIR"
        read -p "Do you want to remove the existing installation and reinstall? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            print_info "Backing up existing installation..."
            BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
            BACKUP_PATH="${BACKUP_DIR}/backup_${BACKUP_TIMESTAMP}"
            mkdir -p "$BACKUP_PATH"
            cp -r "$INSTALL_DIR" "$BACKUP_PATH/" 2>/dev/null || true
            print_success "Backup created at $BACKUP_PATH"

            print_info "Removing existing installation..."
            systemctl stop ${SERVICE_NAME} 2>/dev/null || true
            rm -rf "$INSTALL_DIR"
            print_success "Existing installation removed"
        else
            print_info "Installation cancelled"
            exit 0
        fi
    fi
}

install_dependencies() {
    print_step "Installing Dependencies"

    # Update package list
    print_info "Updating package list..."
    apt-get update -qq

    # Install required packages
    print_info "Installing required packages..."
    apt-get install -y \
        curl \
        wget \
        git \
        unzip \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        gnupg \
        lsb-release \
        bc \
        > /dev/null 2>&1

    print_success "Base dependencies installed"
}

install_openlitespeed() {
    print_step "Installing OpenLiteSpeed Web Server"

    if command -v lshttpd &> /dev/null; then
        print_success "OpenLiteSpeed already installed"
        return
    fi

    print_info "Adding OpenLiteSpeed repository..."
    wget -qO - https://repo.litespeed.sh | bash

    print_info "Installing OpenLiteSpeed..."
    apt-get install -y openlitespeed > /dev/null 2>&1

    print_success "OpenLiteSpeed installed successfully"
}

install_php() {
    print_step "Installing PHP 8.3"

    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
        print_success "PHP already installed: $PHP_VERSION"

        # Check if version is 8.3 or higher
        if [ "$(echo "$PHP_VERSION >= 8.3" | bc)" -eq 1 ] 2>/dev/null; then
            print_success "PHP version compatible"
        else
            print_warning "PHP version may not be compatible. Consider upgrading."
        fi
        return
    fi

    print_info "Adding PHP repository..."
    add-apt-repository ppa:ondrej/php -y

    print_info "Updating package list..."
    apt-get update -qq

    print_info "Installing PHP 8.3 and extensions..."
    if ! apt-get install -y \
        php8.3 \
        php8.3-fpm \
        php8.3-mysql \
        php8.3-redis \
        php8.3-curl \
        php8.3-gd \
        php8.3-mbstring \
        php8.3-xml \
        php8.3-zip \
        php8.3-bcmath \
        php8.3-intl \
        php8.3-json \
        php8.3-cli; then
        print_error "Failed to install PHP 8.3"
        print_info "Trying alternative installation method..."

        # Try installing without specific version
        if ! apt-get install -y php php-mysql php-redis php-curl php-gd php-mbstring php-xml php-zip php-bcmath php-intel; then
            print_error "PHP installation failed completely"
            print_info "Please install PHP manually:"
            print_info "  sudo apt update"
            print_info "  sudo apt install software-properties-common"
            print_info "  sudo add-apt-repository ppa:ondrej/php -y"
            print_info "  sudo apt update"
            print_info "  sudo apt install php8.3 php8.3-mysql php8.3-redis php8.3-curl php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip"
            exit 1
        fi
    fi

    print_success "PHP 8.3 installed successfully"
}

install_mariadb() {
    print_step "Installing MariaDB Database Server"

    if command -v mysql &> /dev/null || command -v mariadb &> /dev/null; then
        print_success "MariaDB/MySQL already installed"
        return
    fi

    print_info "Installing MariaDB..."
    if ! apt-get install -y mariadb-server mariadb-client; then
        print_error "Failed to install MariaDB"
        print_info "Please install manually:"
        print_info "  sudo apt install mariadb-server mariadb-client"
        print_info "  sudo systemctl start mysql"
        print_info "  sudo systemctl enable mysql"
        exit 1
    fi

    print_info "Starting MariaDB service..."
    systemctl start mysql || true
    systemctl enable mysql || true

    print_info "Securing MariaDB installation..."
    # Set root password (you should change this)
    mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'root'; FLUSH PRIVILEGES;" 2>/dev/null || true

    print_success "MariaDB installed successfully"
}

install_redis() {
    print_step "Installing Redis Cache Server"

    if command -v redis-server &> /dev/null; then
        print_success "Redis already installed"
        return
    fi

    print_info "Installing Redis..."
    if ! apt-get install -y redis-server; then
        print_error "Failed to install Redis"
        print_info "Please install manually:"
        print_info "  sudo apt install redis-server"
        print_info "  sudo systemctl start redis-server"
        print_info "  sudo systemctl enable redis-server"
        exit 1
    fi

    print_info "Starting Redis service..."
    systemctl start redis-server || true
    systemctl enable redis-server || true

    print_success "Redis installed successfully"
}

create_directory_structure() {
    print_step "Creating Directory Structure"

    print_info "Creating base directories..."
    mkdir -p "$INSTALL_DIR"
    mkdir -p "$CONFIG_DIR"
    mkdir -p "$LOG_DIR"
    mkdir -p "$RUNTIME_DIR/runtime"
    mkdir -p "$RUNTIME_DIR/backups"
    mkdir -p "$RUNTIME_DIR/backups/database"

    print_info "Creating application directories..."
    mkdir -p "$INSTALL_DIR/app"
    mkdir -p "$INSTALL_DIR/bin"
    mkdir -p "$INSTALL_DIR/config"
    mkdir -p "$INSTALL_DIR/storage/cache"
    mkdir -p "$INSTALL_DIR/storage/sessions"
    mkdir -p "$INSTALL_DIR/storage/uploads"
    mkdir -p "$INSTALL_DIR/public"
    mkdir -p "$INSTALL_DIR/logs"
    mkdir -p "$INSTALL_DIR/scripts"

    print_success "Directory structure created"
}

copy_application_files() {
    print_step "Copying Application Files"

    print_info "Detecting installation source..."

    # Check if running from installer directory
    if [ -f "opt/gdols-panel/VERSION" ]; then
        print_info "Copying from local installer directory..."
        cp -r opt/gdols-panel/* "$INSTALL_DIR/"
    elif [ -f "../opt/gdols-panel/VERSION" ]; then
        print_info "Copying from parent installer directory..."
        cp -r ../opt/gdols-panel/* "$INSTALL_DIR/"
    elif [ -d "GDOLS Panel" ]; then
        print_info "Copying from GDOLS Panel directory..."
        # Copy API files
        if [ -d "GDOLS Panel/api" ]; then
            cp -r "GDOLS Panel/api" "$INSTALL_DIR/app/"
        fi

        # Copy templates
        if [ -d "GDOLS Panel/templates" ]; then
            cp -r "GDOLS Panel/templates" "$INSTALL_DIR/app/"
        fi

        # Copy public files
        if [ -d "GDOLS Panel/public" ]; then
            cp -r "GDOLS Panel/public"/* "$INSTALL_DIR/public/"
        fi

        # Copy scripts
        if [ -d "GDOLS Panel/scripts" ]; then
            cp -r "GDOLS Panel/scripts"/* "$INSTALL_DIR/scripts/"
        fi

        # Copy config
        if [ -d "GDOLS Panel/config" ]; then
            cp -r "GDOLS Panel/config"/* "$INSTALL_DIR/config/"
        fi
    else
        print_error "Cannot find application files"
        exit 1
    fi

    # Create VERSION file if it doesn't exist
    if [ ! -f "$INSTALL_DIR/VERSION" ]; then
        echo "$APP_VERSION" > "$INSTALL_DIR/VERSION"
    fi

    print_success "Application files copied"
}

setup_permissions() {
    print_step "Setting Up Permissions"

    print_info "Setting directory permissions..."
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR/public"
    chmod -R 750 "$INSTALL_DIR/storage"
    chmod -R 755 "$INSTALL_DIR/bin"
    chmod +x "$INSTALL_DIR/bin"/* 2>/dev/null || true
    chmod +x "$INSTALL_DIR/scripts"/* 2>/dev/null || true

    print_info "Setting system directory permissions..."
    chmod 750 "$CONFIG_DIR"
    chmod 750 "$LOG_DIR"
    chmod 750 "$RUNTIME_DIR"

    print_success "Permissions configured"
}

setup_configuration() {
    print_step "Setting Up Configuration"

    # Copy configuration file
    if [ -f "$INSTALLER_TEMP/etc/gdols/gdols.conf" ]; then
        cp "$INSTALLER_TEMP/etc/gdols/gdols.conf" "$CONFIG_DIR/"
    elif [ -f "etc/gdols/gdols.conf" ]; then
        cp etc/gdols/gdols.conf "$CONFIG_DIR/"
    elif [ -f "../etc/gdols/gdols.conf" ]; then
        cp ../etc/gdols/gdols.conf "$CONFIG_DIR/"
    else
        print_warning "Default configuration file not found, creating basic config..."
        cat > "$CONFIG_DIR/gdols.conf" << 'EOF'
<?php
return [
    'app' => [
        'name' => 'GDOLS Panel',
        'version' => '1.0.0',
        'environment' => 'production',
        'debug' => false,
        'timezone' => 'Asia/Jakarta',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'gdols_panel',
        'username' => 'gdols_user',
        'password' => 'CHANGE_THIS_PASSWORD',
    ],
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
    ],
];
EOF
    fi

    # Set secure permissions
    chmod 600 "$CONFIG_DIR/gdols.conf"

    print_success "Configuration file created at $CONFIG_DIR/gdols.conf"
    print_warning "Please edit $CONFIG_DIR/gdols.conf and update passwords and settings!"
}

setup_database() {
    print_step "Setting Up Database"

    print_info "Creating database and user..."

    # Generate random password
    DB_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)

    # Create database and user
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS gdols_panel;" 2>/dev/null || print_warning "Database creation failed"
    mysql -u root -e "CREATE USER IF NOT EXISTS 'gdols_user'@'localhost' IDENTIFIED BY '$DB_PASSWORD';" 2>/dev/null || print_warning "User creation failed"
    mysql -u root -e "GRANT ALL PRIVILEGES ON gdols_panel.* TO 'gdols_user'@'localhost';" 2>/dev/null || print_warning "Privilege grant failed"
    mysql -u root -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    # Update configuration with generated password
    if [ -f "$CONFIG_DIR/gdols.conf" ]; then
        sed -i "s/'password' => 'CHANGE_THIS_PASSWORD'/'password' => '$DB_PASSWORD'/" "$CONFIG_DIR/gdols.conf"
        print_success "Database configured with auto-generated password"
        print_warning "Password saved to $CONFIG_DIR/gdols.conf"
    fi
}

setup_systemd_service() {
    print_step "Setting Up Systemd Service"

    # Copy service file
    if [ -f "$INSTALLER_TEMP/etc/systemd/system/gdols-panel.service" ]; then
        cp "$INSTALLER_TEMP/etc/systemd/system/gdols-panel.service" /etc/systemd/system/
    elif [ -f "etc/systemd/system/gdols-panel.service" ]; then
        cp etc/systemd/system/gdols-panel.service /etc/systemd/system/
    elif [ -f "../etc/systemd/system/gdols-panel.service" ]; then
        cp ../etc/systemd/system/gdols-panel.service /etc/systemd/system/
    else
        print_warning "Service file not found, creating default..."
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

[Install]
WantedBy=multi-user.target
EOF
    fi

    # Reload systemd and enable service
    systemctl daemon-reload
    systemctl enable ${SERVICE_NAME}

    print_success "Systemd service created and enabled"
}

configure_openlitespeed() {
    print_step "Configuring OpenLiteSpeed"

    print_info "Creating virtual host configuration..."

    # Create virtual host directory
    mkdir -p /usr/local/lsws/vhosts/gdols-panel
    mkdir -p /usr/local/lsws/vhosts/gdols-panel/{html,logs,conf}

    # Create symlink to public directory
    ln -sf "$INSTALL_DIR/public" /usr/local/lsws/vhosts/gdols-panel/html

    print_success "OpenLiteSpeed virtual host configured"
    print_warning "Please restart OpenLiteSpeed to apply changes: systemctl restart lsws"
}

setup_cron_jobs() {
    print_step "Setting Up Cron Jobs"

    print_info "Installing backup automation..."

    # Copy backup script if available
    if [ -f "$INSTALL_DIR/scripts/backup-cron.sh" ]; then
        # Add daily backup cron job
        (crontab -l 2>/dev/null || true; echo "0 2 * * * $INSTALL_DIR/scripts/backup-cron.sh >> $LOG_DIR/backup-cron.log 2>&1") | crontab -
        print_success "Backup cron job scheduled for 2 AM daily"
    fi

    print_success "Cron jobs configured"
}

create_symlinks() {
    print_step "Creating System Symlinks"

    # Create symlink for logs
    ln -sf "$LOG_DIR" "$INSTALL_DIR/logs/system" 2>/dev/null || true

    print_success "Symlinks created"
}

run_post_install() {
    print_step "Running Post-Installation Tasks"

    # Set up logrotate
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

    print_success "Log rotation configured"
}

display_completion() {
    print_step "Installation Complete!"

    # Get server IP
    SERVER_IP=$(hostname -I | awk '{print $1}')

    echo ""
    print_success "GDOLS Panel v${APP_VERSION} has been successfully installed!"
    echo ""
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}  INSTALLATION SUMMARY${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "  ${GREEN}Installation Directory:${NC} $INSTALL_DIR"
    echo -e "  ${GREEN}Configuration File:${NC}    $CONFIG_DIR/gdols.conf"
    echo -e "  ${GREEN}Log Directory:${NC}         $LOG_DIR"
    echo -e "  ${GREEN}Runtime Directory:${NC}     $RUNTIME_DIR"
    echo -e "  ${GREEN}Service Name:${NC}          $SERVICE_NAME"
    echo ""
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}  QUICK START${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "  1. ${BLUE}Edit configuration:${NC}"
    echo -e "     ${YELLOW}sudo nano $CONFIG_DIR/gdols.conf${NC}"
    echo ""
    echo -e "  2. ${BLUE}Start the service:${NC}"
    echo -e "     ${YELLOW}sudo systemctl start $SERVICE_NAME${NC}"
    echo ""
    echo -e "  3. ${BLUE}Check service status:${NC}"
    echo -e "     ${YELLOW}sudo systemctl status $SERVICE_NAME${NC}"
    echo ""
    echo -e "  4. ${BLUE}Enable service on boot:${NC}"
    echo -e "     ${YELLOW}sudo systemctl enable $SERVICE_NAME${NC}"
    echo ""
    echo -e "  5. ${BLUE}Restart OpenLiteSpeed:${NC}"
    echo -e "     ${YELLOW}sudo systemctl restart lsws${NC}"
    echo ""
    echo -e "  6. ${BLUE}Access the panel:${NC}"
    echo -e "     ${YELLOW}http://$SERVER_IP:8088${NC} (OpenLiteSpeed default port)"
    echo ""
    echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
    echo ""

    print_warning "IMPORTANT: Please update the configuration file with your secure passwords!"
    print_info "Run: $INSTALL_DIR/bin/status --verbose for detailed status information"
    echo ""
}

# Main Installation Flow
##############################################################################

main() {
    print_banner

    # Check prerequisites
    check_root
    detect_os
    check_existing_installation

    # Install dependencies
    install_dependencies
    install_openlitespeed
    install_php
    install_mariadb
    install_redis

    # Set up directory structure
    create_directory_structure

    # Copy application files
    copy_application_files

    # Configure permissions
    setup_permissions

    # Set up configuration
    setup_configuration
    setup_database

    # Set up services
    setup_systemd_service
    configure_openlitespeed
    setup_cron_jobs

    # Final touches
    create_symlinks
    run_post_install

    # Display completion
    display_completion
}

# Run main function
main "$@"
