/**
 * GD Panel - Main JavaScript File
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Frontend functionality for GD Panel
 */

// ===== Global State =====
const GDPanel = {
    config: {
        apiBase: '../api/',
        csrfToken: null,
        user: null,
        isAuthenticated: false
    },

    modules: {},

    init() {
        console.log('GD Panel Initializing...');

        // Check authentication
        this.checkAuth();

        // Initialize modules
        this.initModules();

        // Setup event listeners
        this.setupEventListeners();

        // Initialize UI
        this.initUI();

        console.log('GD Panel Initialized');
    },

    async checkAuth() {
        try {
            const response = await this.api.get('auth.php?action=check');

            if (response.success && response.data.authenticated) {
                this.config.isAuthenticated = true;
                this.config.user = response.data.user;
                this.config.csrfToken = response.data.csrf_token;

                // Update UI for authenticated user
                this.updateAuthUI();

                // Load dashboard data
                if (this.modules.dashboard) {
                    this.modules.dashboard.load();
                }
            } else {
                // Redirect to login if not on login page
                if (!window.location.pathname.includes('login.html')) {
                    window.location.href = 'login.html';
                }
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            if (!window.location.pathname.includes('login.html')) {
                window.location.href = 'login.html';
            }
        }
    },

    updateAuthUI() {
        const userAvatar = document.querySelector('.header-user-avatar');
        const userName = document.querySelector('.header-user-name');
        const userRole = document.querySelector('.header-user-role');

        if (this.config.user) {
            if (userAvatar) {
                userAvatar.textContent = this.config.user.username.charAt(0).toUpperCase();
            }
            if (userName) {
                userName.textContent = this.config.user.username;
            }
            if (userRole) {
                userRole.textContent = this.config.user.role;
            }
        }
    },

    initModules() {
        // Dashboard Module
        this.modules.dashboard = new DashboardModule();

        // Virtual Hosts Module
        this.modules.vhosts = new VirtualHostsModule();

        // PHP Extensions Module
        this.modules.phpExtensions = new PHPExtensionsModule();

        // Firewall Module
        this.modules.firewall = new FirewallModule();

        // Redis Module
        this.modules.redis = new RedisModule();

        // System Module
        this.modules.system = new SystemModule();
    },

    setupEventListeners() {
        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.dataset.page;
                if (page) {
                    this.navigate(page);
                }
            });
        });

        // Sidebar menu
        document.querySelectorAll('.sidebar-menu-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = link.dataset.page;
                if (page) {
                    this.navigate(page);
                }
            });
        });

        // Logout button
        const logoutBtn = document.querySelector('[data-action="logout"]');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.logout();
            });
        }

        // Forms
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit(form);
            });
        });

        // Modal close buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => {
                this.closeModal(btn.closest('.modal'));
            });
        });

        // Close modal on backdrop click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModal(modal);
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // ESC to close modal
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    this.closeModal(activeModal);
                }
            }
        });
    },

    initUI() {
        // Initialize tooltips
        this.initTooltips();

        // Initialize charts if available
        if (typeof Chart !== 'undefined') {
            this.initCharts();
        }

        // Auto-refresh for some modules
        setInterval(() => {
            if (this.config.isAuthenticated) {
                this.refreshData();
            }
        }, 60000); // Every minute
    },

    async navigate(page) {
        // Update navigation state
        document.querySelectorAll('.nav-item, .sidebar-menu-link').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.page === page) {
                item.classList.add('active');
            }
        });

        // Show loading
        this.showLoading();

        try {
            // Load page content
            const content = document.querySelector('.content');
            if (content) {
                content.innerHTML = '<div class="loading-skeleton" style="height: 400px;"></div>';
            }

            // Load module data
            const module = this.getModuleForPage(page);
            if (module && module.load) {
                await module.load();
            }

        } catch (error) {
            console.error('Navigation error:', error);
            this.showError('Failed to load page');
        } finally {
            this.hideLoading();
        }
    },

    getModuleForPage(page) {
        const moduleMap = {
            'dashboard': 'dashboard',
            'vhosts': 'vhosts',
            'php-extensions': 'phpExtensions',
            'firewall': 'firewall',
            'redis': 'redis',
            'system': 'system',
            'databases': 'databases',
            'backups': 'backups',
            'logs': 'logs',
            'settings': 'settings'
        };

        const moduleName = moduleMap[page];
        return moduleName ? this.modules[moduleName] : null;
    },

    async logout() {
        try {
            await this.api.post('auth.php?action=logout');
            window.location.href = 'login.html';
        } catch (error) {
            console.error('Logout failed:', error);
            // Force redirect even if API call fails
            window.location.href = 'login.html';
        }
    },

    async handleFormSubmit(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const action = form.dataset.action;
        const method = form.dataset.method || 'POST';

        try {
            this.showLoading();

            let response;
            if (method === 'POST') {
                response = await this.api.post(action, data);
            } else if (method === 'PUT') {
                response = await this.api.put(action, data);
            } else if (method === 'DELETE') {
                response = await this.api.delete(action, data);
            }

            if (response.success) {
                this.showSuccess(response.message || 'Operation successful');

                // Reset form
                form.reset();

                // Reload current module if needed
                const currentPage = document.querySelector('.nav-item.active')?.dataset.page;
                if (currentPage) {
                    const module = this.getModuleForPage(currentPage);
                    if (module && module.load) {
                        await module.load();
                    }
                }

                // Close modal if form is in modal
                const modal = form.closest('.modal');
                if (modal) {
                    this.closeModal(modal);
                }
            } else {
                this.showError(response.message || 'Operation failed');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showError('An error occurred. Please try again.');
        } finally {
            this.hideLoading();
        }
    },

    async refreshData() {
        // Refresh current page data
        const currentPage = document.querySelector('.nav-item.active')?.dataset.page;
        if (currentPage) {
            const module = this.getModuleForPage(currentPage);
            if (module && module.load) {
                try {
                    await module.load();
                } catch (error) {
                    console.error('Auto-refresh error:', error);
                }
            }
        }
    },

    // ===== UI Helpers =====
    showLoading() {
        const loading = document.querySelector('.global-loading') || this.createLoadingIndicator();
        loading.classList.add('active');
    },

    hideLoading() {
        const loading = document.querySelector('.global-loading');
        if (loading) {
            loading.classList.remove('active');
        }
    },

    createLoadingIndicator() {
        const loading = document.createElement('div');
        loading.className = 'global-loading';
        loading.innerHTML = `
            <div class="loading-overlay">
                <div class="loading-spinner"></div>
            </div>
        `;
        document.body.appendChild(loading);
        return loading;
    },

    showSuccess(message) {
        this.showAlert(message, 'success');
    },

    showError(message) {
        this.showAlert(message, 'danger');
    },

    showWarning(message) {
        this.showAlert(message, 'warning');
    },

    showInfo(message) {
        this.showAlert(message, 'info');
    },

    showAlert(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible`;
        alert.innerHTML = `
            <div class="alert-header">
                <span class="alert-icon">${this.getAlertIcon(type)}</span>
                <span class="alert-title">${this.getAlertTitle(type)}</span>
                <button type="button" class="alert-close" onclick="this.closest('.alert').remove()">×</button>
            </div>
            <div class="alert-body">${message}</div>
        `;

        // Add to container
        let container = document.querySelector('.alerts-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'alerts-container';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; max-width: 400px;';
            document.body.appendChild(container);
        }

        container.appendChild(alert);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    },

    getAlertIcon(type) {
        const icons = {
            success: '✓',
            danger: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    },

    getAlertTitle(type) {
        const titles = {
            success: 'Success',
            danger: 'Error',
            warning: 'Warning',
            info: 'Information'
        };
        return titles[type] || titles.info;
    },

    openModal(modal) {
        if (typeof modal === 'string') {
            modal = document.querySelector(modal);
        }

        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Focus first input
            setTimeout(() => {
                const firstInput = modal.querySelector('input, textarea, select');
                if (firstInput) {
                    firstInput.focus();
                }
            }, 100);
        }
    },

    closeModal(modal) {
        if (typeof modal === 'string') {
            modal = document.querySelector(modal);
        }

        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    },

    initTooltips() {
        // Simple tooltip implementation
        document.querySelectorAll('[data-tooltip]').forEach(el => {
            el.addEventListener('mouseenter', (e) => {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = el.dataset.tooltip;
                tooltip.style.cssText = `
                    position: absolute;
                    background: var(--bg-tertiary);
                    color: var(--text-primary);
                    padding: 8px 12px;
                    border-radius: 6px;
                    font-size: 12px;
                    z-index: 10000;
                    pointer-events: none;
                    white-space: nowrap;
                `;

                const rect = el.getBoundingClientRect();
                tooltip.style.top = (rect.bottom + 8) + 'px';
                tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';

                document.body.appendChild(tooltip);
                el._tooltip = tooltip;
            });

            el.addEventListener('mouseleave', () => {
                if (el._tooltip) {
                    el._tooltip.remove();
                    delete el._tooltip;
                }
            });
        });
    },

    initCharts() {
        // Initialize charts if Chart.js is available
        console.log('Charts initialized');
    },

    // ===== API Helper =====
    api: {
        async request(endpoint, options = {}) {
            const url = GDPanel.config.apiBase + endpoint;

            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': GDPanel.config.csrfToken
                }
            };

            const finalOptions = { ...defaultOptions, ...options };

            // Add body for POST/PUT requests
            if (finalOptions.body && typeof finalOptions.body === 'object') {
                finalOptions.body = JSON.stringify(finalOptions.body);
            }

            try {
                const response = await fetch(url, finalOptions);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Request failed');
                }

                return data;
            } catch (error) {
                console.error('API request failed:', error);
                throw error;
            }
        },

        get(endpoint) {
            return this.request(endpoint, { method: 'GET' });
        },

        post(endpoint, data) {
            return this.request(endpoint, { method: 'POST', body: data });
        },

        put(endpoint, data) {
            return this.request(endpoint, { method: 'PUT', body: data });
        },

        delete(endpoint, data) {
            return this.request(endpoint, { method: 'DELETE', body: data });
        }
    }
};

// ===== Dashboard Module =====
class DashboardModule {
    constructor() {
        this.name = 'dashboard';
        this.data = null;
    }

    async load() {
        try {
            const response = await GDPanel.api.get('system.php?action=dashboard');

            if (response.success) {
                this.data = response.data;
                this.render();
            }
        } catch (error) {
            console.error('Failed to load dashboard:', error);
        }
    }

    render() {
        const content = document.querySelector('.content');
        if (!content) return;

        content.innerHTML = `
            <div class="content-header">
                <div>
                    <h1 class="content-title">Dashboard</h1>
                    <p class="content-subtitle">Server overview and quick actions</p>
                </div>
                <div class="content-actions">
                    <button class="btn btn-outline" onclick="GDPanel.modules.system.refresh()">
                        <span>🔄</span> Refresh
                    </button>
                </div>
            </div>

            <div class="stats-grid">
                ${this.renderStatCards()}
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions-grid">
                        <a href="#" class="quick-action" data-page="vhosts">
                            <span class="quick-action-icon">🌐</span>
                            <span class="quick-action-text">Add Virtual Host</span>
                        </a>
                        <a href="#" class="quick-action" data-page="php-extensions">
                            <span class="quick-action-icon">🔧</span>
                            <span class="quick-action-text">PHP Extensions</span>
                        </a>
                        <a href="#" class="quick-action" data-page="firewall">
                            <span class="quick-action-icon">🔥</span>
                            <span class="quick-action-text">Firewall</span>
                        </a>
                        <a href="#" class="quick-action" data-page="redis">
                            <span class="quick-action-icon">🔴</span>
                            <span class="quick-action-text">Redis</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    <div class="card-body">
                        ${this.renderRecentActivity()}
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Service Status</h3>
                    </div>
                    <div class="card-body">
                        ${this.renderServiceStatus()}
                    </div>
                </div>
            </div>
        `;

        // Attach event listeners to quick actions
        content.querySelectorAll('.quick-action').forEach(action => {
            action.addEventListener('click', (e) => {
                e.preventDefault();
                const page = action.dataset.page;
                if (page) {
                    GDPanel.navigate(page);
                }
            });
        });
    }

    renderStatCards() {
        if (!this.data || !this.data.stats) {
            return '<p>Loading...</p>';
        }

        const stats = this.data.stats;

        return `
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon primary">⚡</div>
                    <div class="stat-info">
                        <div class="stat-label">CPU Usage</div>
                        <div class="stat-value">${stats.cpu.usage_percent.toFixed(1)}%</div>
                        <div class="stat-change ${stats.cpu.change >= 0 ? 'positive' : 'negative'}">
                            ${stats.cpu.change >= 0 ? '↑' : '↓'} ${Math.abs(stats.cpu.change).toFixed(1)}%
                        </div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon success">💾</div>
                    <div class="stat-info">
                        <div class="stat-label">Memory Usage</div>
                        <div class="stat-value">${stats.memory.usage_percent.toFixed(1)}%</div>
                        <div class="stat-change ${stats.memory.change >= 0 ? 'positive' : 'negative'}">
                            ${stats.memory.change >= 0 ? '↑' : '↓'} ${Math.abs(stats.memory.change).toFixed(1)}%
                        </div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon warning">💿</div>
                    <div class="stat-info">
                        <div class="stat-label">Disk Usage</div>
                        <div class="stat-value">${stats.disk.usage_percent}%</div>
                        <div class="stat-change">${stats.disk.used} / ${stats.disk.total}</div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon danger">🌐</div>
                    <div class="stat-info">
                        <div class="stat-label">Virtual Hosts</div>
                        <div class="stat-value">${stats.vhosts.total}</div>
                        <div class="stat-change">${stats.vhosts.active} active</div>
                    </div>
                </div>
            </div>
        `;
    }

    renderRecentActivity() {
        if (!this.data || !this.data.recentActivity) {
            return '<p class="text-muted">No recent activity</p>';
        }

        return `
            <div class="activity-list">
                ${this.data.recentActivity.map(activity => `
                    <div class="activity-item">
                        <div class="activity-icon">${this.getActivityIcon(activity.action)}</div>
                        <div class="activity-content">
                            <div class="activity-title">${activity.title}</div>
                            <div class="activity-time">${activity.time}</div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    renderServiceStatus() {
        if (!this.data || !this.data.services) {
            return '<p class="text-muted">Loading...</p>';
        }

        return `
            <div class="service-list">
                ${Object.entries(this.data.services).map(([name, service]) => `
                    <div class="service-item">
                        <div class="service-info">
                            <div class="service-name">${name}</div>
                            <div class="service-version">${service.version}</div>
                        </div>
                        <span class="table-status ${service.status.toLowerCase()}">
                            <span class="table-status-dot"></span>
                            ${service.status}
                        </span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    getActivityIcon(action) {
        const icons = {
            'vhost_create': '🌐',
            'vhost_delete': '🗑️',
            'extension_install': '🔧',
            'firewall_add': '🔥',
            'redis_restart': '🔴',
            'backup_create': '💾',
            'user_login': '👤'
        };
        return icons[action] || '📌';
    }
}

// ===== Virtual Hosts Module =====
class VirtualHostsModule {
    constructor() {
        this.name = 'vhosts';
        this.vhosts = [];
    }

    async load() {
        try {
            const response = await GDPanel.api.get('vhost.php?action=list');

            if (response.success) {
                this.vhosts = response.data;
                this.render();
            }
        } catch (error) {
            console.error('Failed to load virtual hosts:', error);
        }
    }

    render() {
        const content = document.querySelector('.content');
        if (!content) return;

        content.innerHTML = `
            <div class="content-header">
                <div>
                    <h1 class="content-title">Virtual Hosts</h1>
                    <p class="content-subtitle">Manage your virtual hosts</p>
                </div>
                <div class="content-actions">
                    <button class="btn btn-primary" onclick="GDPanel.modules.vhosts.showAddModal()">
                        <span>+</span> Add Virtual Host
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Virtual Hosts</h3>
                    <div class="card-actions">
                        <input type="search" placeholder="Search..." class="form-input" style="width: 250px;">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Type</th>
                                    <th>Document Root</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${this.vhosts.length > 0 ? this.vhosts.map(vhost => `
                                    <tr>
                                        <td>
                                            <strong>${vhost.domain}</strong>
                                            <br>
                                            <small class="text-muted">${vhost.email}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-${this.getTypeBadgeClass(vhost.type)}">${vhost.type}</span>
                                        </td>
                                        <td>
                                            <code style="font-size: 12px;">${vhost.docroot}</code>
                                        </td>
                                        <td>
                                            <span class="table-status ${vhost.status.toLowerCase()}">
                                                <span class="table-status-dot"></span>
                                                ${vhost.status}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline" onclick="GDPanel.modules.vhosts.edit('${vhost.id}')">Edit</button>
                                                <button class="btn btn-sm btn-danger" onclick="GDPanel.modules.vhosts.delete('${vhost.id}')">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                `).join('') : '<tr><td colspan="5" class="text-center text-muted">No virtual hosts found</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    getTypeBadgeClass(type) {
        const classes = {
            'wordpress': 'primary',
            'custom': 'success',
            'proxy': 'info'
        };
        return classes[type] || 'secondary';
    }

    showAddModal() {
        // Show modal for adding virtual host
        const modal = document.querySelector('#vhost-add-modal');
        if (modal) {
            GDPanel.openModal(modal);
        }
    }

    async add(data) {
        try {
            GDPanel.showLoading();

            const response = await GDPanel.api.post('vhost.php?action=create', data);

            if (response.success) {
                GDPanel.showSuccess('Virtual host created successfully');
                await this.load();
            } else {
                GDPanel.showError(response.message || 'Failed to create virtual host');
            }
        } catch (error) {
            console.error('Failed to add virtual host:', error);
            GDPanel.showError('An error occurred');
        } finally {
            GDPanel.hideLoading();
        }
    }

    async delete(id) {
        if (!confirm('Are you sure you want to delete this virtual host?')) {
            return;
        }

        try {
            GDPanel.showLoading();

            const response = await GDPanel.api.delete(`vhost.php?action=delete&id=${id}`);

            if (response.success) {
                GDPanel.showSuccess('Virtual host deleted successfully');
                await this.load();
            } else {
                GDPanel.showError(response.message || 'Failed to delete virtual host');
            }
        } catch (error) {
            console.error('Failed to delete virtual host:', error);
            GDPanel.showError('An error occurred');
        } finally {
            GDPanel.hideLoading();
        }
    }
}

// ===== PHP Extensions Module =====
class PHPExtensionsModule {
    constructor() {
        this.name = 'php-extensions';
        this.extensions = [];
    }

    async load() {
        try {
            const response = await GDPanel.api.get('php.php?action=extensions');

            if (response.success) {
                this.extensions = response.data;
                this.render();
            }
        } catch (error) {
            console.error('Failed to load PHP extensions:', error);
        }
    }

    render() {
        const content = document.querySelector('.content');
        if (!content) return;

        const installedCount = this.extensions.filter(e => e.installed).length;
        const enabledCount = this.extensions.filter(e => e.enabled).length;

        content.innerHTML = `
            <div class="content-header">
                <div>
                    <h1 class="content-title">PHP Extensions</h1>
                    <p class="content-subtitle">Manage PHP 8.3 extensions (${installedCount} installed, ${enabledCount} enabled)</p>
                </div>
                <div class="content-actions">
                    <button class="btn btn-primary" onclick="GDPanel.modules.phpExtensions.applyChanges()">
                        <span>✓</span> Apply Changes
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Available Extensions</h3>
                </div>
                <div class="card-body">
                    <div class="extensions-grid">
                        ${this.extensions.map(ext => `
                            <div class="extension-card">
                                <div class="extension-header">
                                    <div class="extension-info">
                                        <div class="extension-name">${ext.display_name}</div>
                                        <div class="extension-version">${ext.name}</div>
                                    </div>
                                    <div class="extension-status">
                                        ${ext.installed ?
                                            '<span class="badge badge-success">Installed</span>' :
                                            '<span class="badge badge-warning">Not Installed</span>'}
                                        ${ext.installed && ext.enabled ?
                                            '<span class="badge badge-primary">Enabled</span>' :
                                            ''}
                                    </div>
                                </div>
                                <div class="extension-description">${ext.description}</div>
                                <div class="extension-actions">
                                    ${!ext.installed ? `
                                        <button class="btn btn-sm btn-primary" onclick="GDPanel.modules.phpExtensions.install('${ext.name}')">
                                            Install
                                        </button>
                                    ` : ''}
                                    ${ext.installed ? `
                                        <label class="checkbox">
                                            <input type="checkbox"
                                                   ${ext.enabled ? 'checked' : ''}
                                                   onchange="GDPanel.modules.phpExtensions.toggle('${ext.name}', this.checked)">
                                            Enable
                                        </label>
                                    ` : ''}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    }

    async install(extensionName) {
        try {
            GDPanel.showLoading();

            const response = await GDPanel.api.post('php.php?action=install_extension', {
                extension: extensionName
            });

            if (response.success) {
                GDPanel.showSuccess(`Extension ${extensionName} installed successfully`);
                await this.load();
            } else {
                GDPanel.showError(response.message || 'Failed to install extension');
            }
        } catch (error) {
            console.error('Failed to install extension:', error);
            GDPanel.showError('An error occurred');
        } finally {
            GDPanel.hideLoading();
        }
    }

    async toggle(extensionName, enabled) {
        const ext = this.extensions.find(e => e.name === extensionName);
        if (ext) {
            ext.enabled = enabled;
        }
    }

    async applyChanges() {
        try {
            GDPanel.showLoading();

            const enabledExtensions = this.extensions
                .filter(e => e.enabled)
                .map(e => e.name);

            const response = await GDPanel.api.post('php.php?action=update_extensions', {
                extensions: enabledExtensions
            });

            if (response.success) {
                GDPanel.showSuccess('PHP extensions updated successfully');
                await this.load();
            } else {
                GDPanel.showError(response.message || 'Failed to update extensions');
            }
        } catch (error) {
            console.error('Failed to apply changes:', error);
            GDPanel.showError('An error occurred');
        } finally {
            GDPanel.hideLoading();
        }
    }
}

// ===== Firewall Module =====
class FirewallModule {
    constructor() {
        this.name = 'firewall';
        this.rules = [];
    }

    async load() {
        try {
            const response = await GDPanel.api.get('firewall.php?action=status');

            if (response.success) {
                this.rules = response.data.rules;
                this.render();
            }
        } catch (error) {
            console.error('Failed to load firewall rules:', error);
        }
    }

    render() {
        const content = document.querySelector('.content');
        if (!content) return;

        content.innerHTML = `
            <div class="content-header">
                <div>
                    <h1 class="content-title">Firewall</h1>
                    <p class="content-subtitle">Manage UFW firewall rules</p>
                </div>
                <div class="content-actions">
                    <button class="btn btn-primary" onclick="GDPanel.modules.firewall.showAddRuleModal()">
                        <span>+</span> Add Rule
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Firewall Rules</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rule</th>
                                    <th>Action</th>
                                    <th>Port</th>
                                    <th>Protocol</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${this.rules.length > 0 ? this.rules.map(rule => `
                                    <tr>
                                        <td>${rule.description || rule.rule_id}</td>
                                        <td>
                                            <span class="badge badge-${rule.action === 'allow' ? 'success' : 'danger'}">
                                                ${rule.action}
                                            </span>
                                        </td>
                                        <td>${rule.port}</td>
                                        <td>${rule.protocol}</td>
                                        <td>${rule.source}</td>
                                        <td>
                                            <span class="table-status ${rule.enabled ? 'active' : 'inactive'}">
                                                <span class="table-status-dot"></span>
                                                ${rule.enabled ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger" onclick="GDPanel.modules.firewall.deleteRule('${rule.rule_id}')">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                `).join('') : '<tr><td colspan="7" class="text-center text-muted">No firewall rules found</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    showAddRuleModal() {
        // Show modal for adding firewall rule
        const modal = document.querySelector('#firewall-add-modal');
        if (modal) {
            GDPanel.openModal(modal);
        }
    }

    async addRule(data) {
        try {
            GDPanel.showLoading();

            const response = await GDPanel.api.post('firewall.php?action=add', data);

            if (response.success) {
                GDPanel.showSuccess('Firewall rule added successfully');
                await this.load();
            } else {
                GDPanel.showError(response.message || 'Failed to add rule');
            }
        } catch (error) {
            console.error('Failed to add firewall rule:', error);
            GDPanel.showError('An error occurred');
        } finally {
            GDPanel.hideLoading();
        }
    }

    async deleteRule(ruleId) {
        if (!confirm('Are you sure you want to delete this rule?')) {
            return;
        }

        try {
            GDPanel.showLoading();

            const response = await GDPanel.api.delete(`firewall.php?action=delete&rule_id=${ruleId}`);

            if (response.success) {
                GDPanel.showSuccess('Rule deleted successfully');
                await this.load();
            } else {
                GDPanel.showError(response.message || 'Failed to delete rule');
            }
        } catch (error) {
            console.error('Failed to delete rule:', error);
            GDPanel.showError('An error occurred');
        } finally {
            GDPanel.hideLoading();
        }
    }
}

// ===== Redis Module =====
class RedisModule {
    constructor() {
        this.name = 'redis';
        this.config = null;
        this.status = null;
    }

    async load() {
        try {
            const [configResponse, statusResponse] = await Promise.all([
                GDPanel.api.get('redis.php?action=config'),
                GDPanel.api.get('redis.php?action=status')
            ]);

            if (configResponse.success) {
                this.config = configResponse.data;
            }

            if (statusResponse.success) {
                this.status = statusResponse.data;
            }

            this.render();
        } catch (error) {
            console.error('Failed to load Redis data:', error);
        }
    }

    render() {
        const content = document.querySelector('.content');
        if (!content) return;

        content.innerHTML = `
            <div class="content-header">
                <div>
                    <h1 class="content-title">Redis</h1>
                    <p class="content-subtitle">Manage Redis server</p>
                </div>
                <div class="content-actions">
                    <button class="btn btn-outline" onclick="GDPanel.modules.redis.restart()">
                        <span>🔄</span> Restart
                    </button>
                    <button class="btn btn-danger" onclick="GDPanel.modules.redis.flush()">
                        <span>🗑️</span> Flush All
                    </button>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon ${this.status?.running ? 'success' : 'danger'}">🔴</div>
                        <div class="stat-info">
                            <div class="stat-label">Status</div>
                            <div class="stat-value">${this.status?.running ? 'Running' : 'Stopped'}</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">💾</div>
                        <div class="stat-info">
                            <div class="stat-label">Memory</div>
                            <div class="stat-value">${this.config?.maxmemory || 'N/A'}</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon warning">⚡</div>
                        <div class="stat-info">
                            <div class="stat-label">Policy</div>
                            <div class="stat-value">${this.config?.maxmemory_policy || 'N/A'}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Configuration</h3>
                </div>
                <div class="card-body">
                    <form data-ajax="true" data-action="redis.php?action=update_config" data-method="POST">
                        <div class="form-group">
                            <label class="form-label">Max Memory</label>
                            <select class="form-select" name="maxmemory">
                                <option value="1g" ${this.config?.maxmemory === '1g' ? 'selected' : ''}>1GB</option>
                                <option value="2g" ${this.config?.maxmemory === '2g' ? 'selected' : ''}>2GB</option>
                                <option value="4g" ${this.config?.maxmemory === '4g' ? 'selected' : ''}>4GB</option>
                                <option value="8g" ${this.config?.maxmemory === '8g' ? 'selected' : ''}>8GB</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Memory Policy</label>
                            <select class="form-select" name="maxmemory_policy">
                                <option value="allkeys-lru" ${this.config?.maxmemory_policy === 'allkeys-lru' ? 'selected' : ''}>allkeys-lru</option>
                                <option value="volatile-lru" ${this.config?.maxmemory_policy === 'volatile-lru' ? 'selected' : ''}>volatile-lru</option>
                                <option value="allkeys-random" ${this.config?.maxmemory_policy === 'allkeys-random' ? 'selected' : ''}>allkeys-random</option>
                                <option value="volatile-random" ${this.config?.maxmemory_policy === 'volatile-random' ? 'selected' : ''}>volatile-random</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Timeout (seconds)</label>
                            <input type="number" class="form-input" name="timeout" value="${this.config?.timeout || 300}">
                        </div>

                        <div class="form-group">
                            <label class="form-label">TCP Keepalive</label>
                            <input type="number" class="form-input" name="tcp_keepalive" value="${this.config?.tcp_keepalive || 60}">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Configuration</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    async restart() {
        if (!confirm('Are you sure you want to restart Redis?')) {
            return;
        }

        try {
            GDPanel.showLoading();

            const response = await GDPanel.api.post('redis.php?action=restart');

            if (response.success) {
                GDPanel.showSuccess('Redis restarted successfully');
                await this.load();
            } else {
                GDPanel.showError(response.message || 'Failed to restart Redis');
            }
        } catch (error) {
            console.error('Failed to restart Redis:', error);
            GDPanel.showError('An error occurred');
        } finally {
            GDPanel.hideLoading();
        }
    }

    async flush() {
        if (!confirm('Are you sure you want to flush all Redis data? This action cannot be undone!')) {
            return;
        }

        try {
            GDPanel.showLoading();

            const response = await GDPanel.api.post('redis.php?action=flush');

            if (response.success) {
                GDPanel.showSuccess('Redis flushed successfully');
                await this.load();
            } else {
                GDPanel.showError(response.message || 'Failed to flush Redis');
            }
        } catch (error) {
            console.error('Failed to flush Redis:', error);
            GDPanel.showError('An error occurred');
        } finally {
            GDPanel.hideLoading();
        }
    }
}

// ===== System Module =====
class SystemModule {
    constructor() {
        this.name = 'system';
        this.info = null;
    }

    async load() {
        try {
            const response = await GDPanel.api.get('system.php?action=info');

            if (response.success) {
                this.info = response.data;
                this.render();
            }
        } catch (error) {
            console.error('Failed to load system info:', error);
        }
    }

    render() {
        const content = document.querySelector('.content');
        if (!content || !this.info) return;

        content.innerHTML = `
            <div class="content-header">
                <div>
                    <h1 class="content-title">System Information</h1>
                    <p class="content-subtitle">Server details and resource usage</p>
                </div>
                <div class="content-actions">
                    <button class="btn btn-outline" onclick="GDPanel.modules.system.refresh()">
                        <span>🔄</span> Refresh
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">System Details</h3>
                </div>
                <div class="card-body">
                    <dl class="system-info">
                        <dt>Hostname</dt>
                        <dd>${this.info.hostname}</dd>

                        <dt>Operating System</dt>
                        <dd>${this.info.os} ${this.info.kernel}</dd>

                        <dt>Architecture</dt>
                        <dd>${this.info.architecture}</dd>

                        <dt>PHP Version</dt>
                        <dd>${this.info.php_version}</dd>

                        <dt>Uptime</dt>
                        <dd>${this.info.uptime}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Installed Software</h3>
                </div>
                <div class="card-body">
                    <dl class="system-info">
                        ${Object.entries(this.info.software).map(([name, version]) => `
                            <dt>${name}</dt>
                            <dd>${version}</dd>
                        `).join('')}
                    </dl>
                </div>
            </div>
        `;
    }

    async refresh() {
        await this.load();
        GDPanel.showSuccess('System information refreshed');
    }
}

// ===== Initialize App =====
document.addEventListener('DOMContentLoaded', () => {
    GDPanel.init();
});

// Export for global access
window.GDPanel = GDPanel;
