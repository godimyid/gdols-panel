/**
 * GD Panel - Database Management JavaScript
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Database and user management functionality
 */

// Database Management Class
class DatabaseManager {
    constructor() {
        this.apiUrl = '/api/endpoints/database.php';
        this.databases = [];
        this.users = [];
        this.backups = [];
        this.currentTab = 'databases';
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadDatabases();
        this.loadUsers();
        this.loadBackups();
    }

    bindEvents() {
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchTab(e.target.dataset.tab);
            });
        });

        // Create database button
        document.getElementById('createDbBtn').addEventListener('click', () => {
            this.openModal('createDbModal');
        });

        // Create user button
        document.getElementById('createUserBtn').addEventListener('click', () => {
            this.openModal('createUserModal');
            this.populateDatabaseSelect('userDatabase');
        });

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            this.loadDatabases();
            this.loadUsers();
            this.loadBackups();
            this.showToast('Data refreshed successfully', 'success');
        });

        // Create database form
        document.getElementById('createDbForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.createDatabase();
        });

        // Create user form
        document.getElementById('createUserForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.createUser();
        });

        // Import form
        document.getElementById('importForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.importDatabase();
        });

        // Export form
        document.getElementById('exportForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.exportDatabase();
        });

        // Create user checkbox toggle
        document.getElementById('createUser').addEventListener('change', (e) => {
            const userFields = document.getElementById('userFields');
            const passwordFields = document.getElementById('passwordFields');
            if (e.target.checked) {
                userFields.style.display = 'block';
                passwordFields.style.display = 'block';
            } else {
                userFields.style.display = 'none';
                passwordFields.style.display = 'none';
            }
        });

        // Modal close buttons
        document.querySelectorAll('.modal-close, .btn-secondary').forEach(btn => {
            btn.addEventListener('click', () => {
                this.closeAllModals();
            });
        });

        // Delete confirmation
        document.getElementById('confirmDelete').addEventListener('click', () => {
            this.executeDelete();
        });

        document.getElementById('cancelDelete').addEventListener('click', () => {
            this.closeModal('deleteModal');
        });

        // Search functionality
        document.getElementById('searchDatabase').addEventListener('input', (e) => {
            this.filterDatabases(e.target.value);
        });

        // Auto-fill username when database name is entered
        document.getElementById('dbName').addEventListener('input', (e) => {
            if (document.getElementById('createUser').checked) {
                document.getElementById('dbUsername').value = e.target.value + '_user';
            }
        });

        // Populate database selects
        this.populateDatabaseSelect('importDatabase');
        this.populateDatabaseSelect('exportDatabase');
    }

    switchTab(tabName) {
        this.currentTab = tabName;

        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
            }
        });

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
            if (content.id === tabName + '-tab') {
                content.classList.add('active');
            }
        });

        // Load data based on tab
        if (tabName === 'databases') {
            this.loadDatabases();
        } else if (tabName === 'users') {
            this.loadUsers();
        } else if (tabName === 'backups') {
            this.loadBackups();
        }
    }

    async loadDatabases() {
        try {
            const response = await fetch(`${this.apiUrl}?action=list`);
            const result = await response.json();

            if (result.status === 'success') {
                this.databases = result.data;
                this.renderDatabases();
                this.updateStats();
            } else {
                this.showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error loading databases:', error);
            this.showToast('Failed to load databases', 'error');
        }
    }

    renderDatabases(filter = '') {
        const tbody = document.getElementById('databaseList');
        let html = '';

        const filteredDatabases = this.databases.filter(db =>
            db.name.toLowerCase().includes(filter.toLowerCase())
        );

        if (filteredDatabases.length === 0) {
            html = '<tr><td colspan="4" class="text-center">No databases found</td></tr>';
        } else {
            filteredDatabases.forEach(db => {
                html += `
                    <tr>
                        <td><strong>${db.name}</strong></td>
                        <td>${db.size} MB</td>
                        <td>${db.tables}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="dbManager.backupDatabase('${db.name}')">
                                💾 Backup
                            </button>
                            <button class="btn btn-sm btn-info" onclick="dbManager.exportDatabase('${db.name}')">
                                📤 Export
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="dbManager.confirmDeleteDatabase('${db.name}')">
                                🗑️ Delete
                            </button>
                        </td>
                    </tr>
                `;
            });
        }

        tbody.innerHTML = html;
    }

    updateStats() {
        const totalDatabases = this.databases.length;
        const totalSize = this.databases.reduce((sum, db) => sum + parseFloat(db.size), 0);
        const totalTables = this.databases.reduce((sum, db) => sum + parseInt(db.tables), 0);

        document.getElementById('totalDatabases').textContent = totalDatabases;
        document.getElementById('totalSize').textContent = totalSize.toFixed(2) + ' MB';
        document.getElementById('totalTables').textContent = totalTables;
    }

    filterDatabases(searchTerm) {
        this.renderDatabases(searchTerm);
    }

    async createDatabase() {
        const form = document.getElementById('createDbForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(`${this.apiUrl}?action=create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': localStorage.getItem('csrf_token')
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showToast(result.message, 'success');
                this.closeModal('createDbModal');
                form.reset();
                this.loadDatabases();

                // Create user if checked
                if (document.getElementById('createUser').checked && data.username) {
                    await this.createDatabaseUser(data);
                }
            } else {
                this.showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error creating database:', error);
            this.showToast('Failed to create database', 'error');
        }
    }

    async createDatabaseUser(dbData) {
        const userData = {
            username: dbData.username,
            password: dbData.password,
            host: 'localhost',
            database: dbData.name
        };

        try {
            const response = await fetch(`${this.apiUrl}?action=create_user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': localStorage.getItem('csrf_token')
                },
                body: JSON.stringify(userData)
            });

            const result = await response.json();
            if (result.status === 'success') {
                this.showToast(`Database user created successfully`, 'success');
            }
        } catch (error) {
            console.error('Error creating user:', error);
        }
    }

    confirmDeleteDatabase(dbName) {
        this.deleteTarget = { type: 'database', name: dbName };
        document.getElementById('deleteTarget').textContent = `database: ${dbName}`;
        this.openModal('deleteModal');
    }

    async deleteDatabase(dbName) {
        try {
            const response = await fetch(`${this.apiUrl}?action=delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': localStorage.getItem('csrf_token')
                },
                body: JSON.stringify({ name: dbName })
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showToast(result.message, 'success');
                this.loadDatabases();
            } else {
                this.showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error deleting database:', error);
            this.showToast('Failed to delete database', 'error');
        }
    }

    async loadUsers() {
        try {
            const response = await fetch(`${this.apiUrl}?action=users`);
            const result = await response.json();

            if (result.status === 'success') {
                this.users = result.data;
                this.renderUsers();
            } else {
                this.showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showToast('Failed to load users', 'error');
        }
    }

    renderUsers() {
        const tbody = document.getElementById('usersList');
        let html = '';

        if (this.users.length === 0) {
            html = '<tr><td colspan="3" class="text-center">No users found</td></tr>';
        } else {
            this.users.forEach(user => {
                html += `
                    <tr>
                        <td><strong>${user.username}</strong></td>
                        <td>${user.host}</td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="dbManager.confirmDeleteUser('${user.username}', '${user.host}')">
                                🗑️ Delete
                            </button>
                        </td>
                    </tr>
                `;
            });
        }

        tbody.innerHTML = html;
    }

    async createUser() {
        const form = document.getElementById('createUserForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(`${this.apiUrl}?action=create_user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': localStorage.getItem('csrf_token')
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showToast(result.message, 'success');
                this.closeModal('createUserModal');
                form.reset();
                this.loadUsers();
            } else {
                this.showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error creating user:', error);
            this.showToast('Failed to create user', 'error');
        }
    }

    confirmDeleteUser(username, host) {
        this.deleteTarget = { type: 'user', username, host };
        document.getElementById('deleteTarget').textContent = `user: ${username}@${host}`;
        this.openModal('deleteModal');
    }

    async deleteUser(username, host) {
        try {
            const response = await fetch(`${this.apiUrl}?action=delete_user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': localStorage.getItem('csrf_token')
                },
                body: JSON.stringify({ username, host })
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showToast(result.message, 'success');
                this.loadUsers();
            } else {
                this.showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            this.showToast('Failed to delete user', 'error');
        }
    }

    async executeDelete() {
        if (!this.deleteTarget) return;

        if (this.deleteTarget.type === 'database') {
            await this.deleteDatabase(this.deleteTarget.name);
        } else if (this.deleteTarget.type === 'user') {
            await this.deleteUser(this.deleteTarget.username, this.deleteTarget.host);
        }

        this.closeModal('deleteModal');
        this.deleteTarget = null;
    }

    async backupDatabase(dbName) {
        try {
            const response = await fetch(`${this.apiUrl}?action=backup`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': localStorage.getItem('csrf_token')
                },
                body: JSON.stringify({ database: dbName })
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showToast(result.message, 'success');
                this.loadBackups();
            } else {
                this.showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error backing up database:', error);
            this.showToast('Failed to backup database', 'error');
        }
    }

    async loadBackups() {
        try {
            const response = await fetch(`${this.apiUrl}?action=list`);
            const result = await response.json();

            if (result.status === 'success') {
                // For now, we'll simulate backup list from databases
                // In production, this should be a separate endpoint
                this.renderBackups();
            }
        } catch (error) {
            console.error('Error loading backups:', error);
        }
    }

    renderBackups() {
        const tbody = document.getElementById('backupsList');
        let html = '<tr><td colspan="5" class="text-center">Use the backup button on each database to create backups</td></tr>';
        tbody.innerHTML = html;
    }

    async importDatabase() {
        const form = document.getElementById('importForm');
        const formData = new FormData(form);
        const database = document.getElementById('importDatabase').value;
        const fileInput = document.getElementById('sqlFile');

        if (!fileInput.files.length) {
            this.showToast('Please select a SQL file', 'error');
            return;
        }

        const importFormData = new FormData();
        importFormData.append('database', database);
        importFormData.append('sql_file', fileInput.files[0]);

        try {
            const response = await fetch(`${this.apiUrl}?action=import`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': localStorage.getItem('csrf_token')
                },
                body: importFormData
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showToast(result.message, 'success');
                form.reset();
            } else {
                this.showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error importing database:', error);
            this.showToast('Failed to import database', 'error');
        }
    }

    async exportDatabase(dbName) {
        try {
            const response = await fetch(`${this.apiUrl}?action=export`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': localStorage.getItem('csrf_token')
                },
                body: JSON.stringify({ database: dbName })
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showToast(result.message, 'success');

                // Trigger download if available
                if (result.data.download_url) {
                    window.location.href = result.data.download_url;
                }
            } else {
                this.showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error exporting database:', error);
            this.showToast('Failed to export database', 'error');
        }
    }

    populateDatabaseSelect(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;

        // Save current value
        const currentValue = select.value;

        // Clear options except first
        while (select.options.length > 1) {
            select.remove(1);
        }

        // Add database options
        this.databases.forEach(db => {
            const option = document.createElement('option');
            option.value = db.name;
            option.textContent = db.name;
            select.appendChild(option);
        });

        // Restore value if still valid
        if (currentValue && this.databases.find(db => db.name === currentValue)) {
            select.value = currentValue;
        }
    }

    openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }

    closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('active');
        });
    }

    showToast(message, type = 'info') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type}`;
        toast.classList.add('active');

        setTimeout(() => {
            toast.classList.remove('active');
        }, 3000);
    }
}

// Initialize when DOM is ready
let dbManager;
document.addEventListener('DOMContentLoaded', () => {
    dbManager = new DatabaseManager();
});
