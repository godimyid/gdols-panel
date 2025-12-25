```path/to/GDOLS Panel/install.php#L1-500
<?php
/**
 * GDOLS Panel - Installer Script
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: One-click installer for GDOLS Panel - OpenLiteSpeed Management Panel
 *
 * This script handles:
 * - System requirements check
 * - Database creation and initialization
 * - Admin user creation
 * - Configuration file generation
 * - Directory setup and permissions
 */
 *
 * This script handles:
 * - System requirements check
 * - Database creation and initialization
 * - Admin user creation
 * - Configuration file generation
 * - Directory setup and permissions
 */

// Set error reporting for installation
error_reporting(E_ALL);
ini_set("display_errors", "1");

// Define constants
define("GDOLS_PANEL_ROOT", dirname(__FILE__));
define("GDOLS_PANEL_VERSION", "1.0.0");
define("GDOLS_PANEL_AUTHOR", "GoDiMyID");
define("GDOLS_PANEL_WEBSITE", "https://godi.my.id");

// Installation steps
$steps = [
    "welcome" => "Welcome",
    "requirements" => "System Requirements",
    "database" => "Database Setup",
    "config" => "Configuration",
    "admin" => "Admin Account",
    "install" => "Installation",
    "complete" => "Complete",
];

$currentStep = $_GET["step"] ?? "welcome";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    switch ($action) {
        case "check_requirements":
            $result = checkRequirements();
            echo json_encode($result);
            exit();

        case "test_database":
            $result = testDatabase($_POST);
            echo json_encode($result);
            exit();

        case "install":
            $result = performInstallation($_POST);
            echo json_encode($result);
            exit();
    }
}

// Render current step
renderPage($currentStep);

/**
 * Render installation page
 */
function renderPage($step)
{
    $stepIndex = array_search($step, array_keys($steps)); ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GDOLS Panel Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .progress {
            display: flex;
            background: #f5f5f5;
            padding: 20px 40px;
            border-bottom: 1px solid #e0e0e0;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
            font-size: 12px;
            color: #999;
        }

        .progress-step.active {
            color: #667eea;
            font-weight: 600;
        }

        .progress-step.completed {
            color: #4caf50;
        }

        .progress-step .number {
            width: 30px;
            height: 30px;
            background: #e0e0e0;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
        }

        .progress-step.active .number {
            background: #667eea;
            color: white;
        }

        .progress-step.completed .number {
            background: #4caf50;
            color: white;
        }

        .content {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group .help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            margin-right: 10px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .requirements-list {
            list-style: none;
        }

        .requirements-list li {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
        }

        .requirements-list li:last-child {
            border-bottom: none;
        }

        .requirements-list .status {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .requirements-list .status.success {
            background: #d4edda;
            color: #155724;
        }

        .requirements-list .status.error {
            background: #f8d7da;
            color: #721c24;
        }

        .requirements-list .status.warning {
            background: #fff3cd;
            color: #856404;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .feature-card {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }

        .feature-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .feature-card h4 {
            margin-bottom: 5px;
            color: #333;
        }

        .feature-card p {
            font-size: 12px;
            color: #666;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .buttons {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 GDOLS Panel</h1>
            <p>OpenLiteSpeed Management Panel - Installation Wizard</p>
            <p style="margin-top: 10px; font-size: 12px;">By <?php echo GDOLS_PANEL_AUTHOR; ?> | <?php echo GDOLS_PANEL_WEBSITE; ?></p>
        </div>

        <div class="progress">
            <?php foreach ($steps as $stepKey => $stepName): ?>
                <div class="progress-step <?php echo $stepKey === $step
                    ? "active"
                    : ""; ?> <?php echo array_search(
     $stepKey,
     array_keys($steps),
 ) < $stepIndex
     ? "completed"
     : ""; ?>">
                    <div class="number"><?php echo array_search(
                        $stepKey,
                        array_keys($steps),
                    ) + 1; ?></div>
                    <?php echo $stepName; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="content">
            <?php renderStepContent($step); ?>
        </div>
    </div>

    <script>
        function checkRequirements() {
            const loading = document.querySelector('.loading');
            const content = document.querySelector('.step-content');

            loading.classList.add('active');
            content.style.opacity = '0.5';

            fetch('install.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=check_requirements'
            })
            .then(response => response.json())
            .then(data => {
                loading.classList.remove('active');
                content.style.opacity = '1';

                if (data.success) {
                    displayRequirements(data.requirements);
                } else {
                    alert('Error checking requirements: ' + data.message);
                }
            })
            .catch(error => {
                loading.classList.remove('active');
                content.style.opacity = '1';
                alert('Error: ' + error);
            });
        }

        function displayRequirements(requirements) {
            const container = document.getElementById('requirements-container');
            container.innerHTML = '<ul class="requirements-list"></ul>';
            const list = container.querySelector('ul');

            requirements.forEach(req => {
                const li = document.createElement('li');
                li.innerHTML = `
                    <div class="status ${req.status}">
                        ${req.status === 'success' ? '✓' : req.status === 'error' ? '✗' : '!'}
                    </div>
                    <div>
                        <strong>${req.name}</strong>
                        ${req.required ? '<span style="color: #dc3545; margin-left: 10px;">Required</span>' : ''}
                        ${req.current ? `<br><small>Current: ${req.current}</small>` : ''}
                        ${req.message ? `<br><small>${req.message}</small>` : ''}
                    </div>
                `;
                list.appendChild(li);
            });
        }

        function testDatabase() {
            const form = document.getElementById('database-form');
            const formData = new FormData(form);
            formData.append('action', 'test_database');

            const loading = document.querySelector('.loading');
            loading.classList.add('active');

            fetch('install.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loading.classList.remove('active');

                const alert = document.getElementById('database-alert');
                alert.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
                alert.textContent = data.message;
                alert.style.display = 'block';

                if (data.success) {
                    document.getElementById('db-tested').value = '1';
                }
            })
            .catch(error => {
                loading.classList.remove('active');
                alert('Error: ' + error);
            });
        }

        function submitInstallation() {
            const form = document.getElementById('install-form');
            const formData = new FormData(form);
            formData.append('action', 'install');

            const loading = document.querySelector('.loading');
            const content = document.querySelector('.step-content');

            loading.classList.add('active');
            content.style.opacity = '0.5';

            fetch('install.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loading.classList.remove('active');

                if (data.success) {
                    window.location.href = 'install.php?step=complete';
                } else {
                    content.style.opacity = '1';
                    alert('Installation failed: ' + data.message);
                }
            })
            .catch(error => {
                loading.classList.remove('active');
                content.style.opacity = '1';
                alert('Error: ' + error);
            });
        }
    </script>
</body>
</html>
    <?php
}

/**
 * Render step content
 */
function renderStepContent($step)
{
    switch ($step) {
        case "welcome":
            renderWelcome();
            break;
        case "requirements":
            renderRequirements();
            break;
        case "database":
            renderDatabase();
            break;
        case "config":
            renderConfig();
            break;
        case "admin":
            renderAdmin();
            break;
        case "install":
            renderInstall();
            break;
        case "complete":
            renderComplete();
            break;
    }
}

/**
 * Render welcome step
 */
function renderWelcome()
{
    ?>
    <div class="step-content">
        <h2>Welcome to GDOLS Panel Installation</h2>
        <p style="margin: 20px 0; color: #666;">
            This wizard will guide you through the installation of GDOLS Panel - a powerful management panel for OpenLiteSpeed servers.
        </p>

        <div class="feature-grid">
            <div class="feature-card">
                <div class="icon">⚡</div>
                <h4>OpenLiteSpeed</h4>
                <p>High-performance web server</p>
            </div>
            <div class="feature-card">
                <div class="icon">🐘</div>
                <h4>PHP 8.3</h4>
                <p>Latest PHP with extensions</p>
            </div>
            <div class="feature-card">
                <div class="icon">🗄️</div>
                <h4>MariaDB</h4>
                <p>Robust database server</p>
            </div>
            <div class="feature-card">
                <div class="icon">🔴</div>
                <h4>Redis</h4>
                <p>Powerful caching</p>
            </div>
            <div class="feature-card">
                <div class="icon">🔥</div>
                <h4>Firewall</h4>
                <p>UFW management</p>
            </div>
            <div class="feature-card">
                <div class="icon">📊</div>
                <h4>Monitoring</h4>
                <p>Real-time stats</p>
            </div>
        </div>

        <div class="alert alert-info" style="margin-top: 30px;">
            <strong>Before you begin:</strong>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>Ensure you have root/sudo access to the server</li>
                <li>Make sure OpenLiteSpeed is installed and running</li>
                <li>Have your database credentials ready</li>
                <li>Backup any important data before proceeding</li>
            </ul>
        </div>

        <div class="buttons">
            <button class="btn btn-primary" onclick="window.location.href='install.php?step=requirements'">
                Begin Installation →
            </button>
        </div>
    </div>
    <?php
}

/**
 * Render requirements step
 */
function renderRequirements()
{
    ?>
    <div class="step-content">
        <h2>System Requirements Check</h2>
        <p style="margin-bottom: 20px; color: #666;">
            Checking if your server meets the minimum requirements...
        </p>

        <div class="loading" id="requirements-loading">
            <div class="spinner"></div>
            <p>Checking system requirements...</p>
        </div>

        <div id="requirements-container"></div>

        <div class="buttons">
            <button class="btn btn-secondary" onclick="window.location.href='install.php?step=welcome'">
                ← Back
            </button>
            <button class="btn btn-primary" onclick="checkRequirements()">
                Check Requirements
            </button>
            <button class="btn btn-primary" onclick="window.location.href='install.php?step=database'" style="margin-left: 10px;">
                Next →
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            checkRequirements();
        });
    </script>
    <?php
}

/**
 * Render database step
 */
function renderDatabase()
{
    ?>
    <div class="step-content">
        <h2>Database Configuration</h2>
        <p style="margin-bottom: 20px; color: #666;">
            Enter your MariaDB/MySQL database connection details.
        </p>

        <div class="alert alert-danger hidden" id="database-alert" style="display: none;"></div>

        <form id="database-form">
            <div class="form-group">
                <label>Database Host</label>
                <input type="text" name="db_host" value="localhost" required>
                <div class="help">Usually 'localhost' or '127.0.0.1'</div>
            </div>

            <div class="form-group">
                <label>Database Port</label>
                <input type="number" name="db_port" value="3306" required>
            </div>

            <div class="form-group">
                <label>Database Username</label>
                <input type="text" name="db_user" value="root" required>
                <div class="help">MySQL root user or user with CREATE DATABASE privileges</div>
            </div>

            <div class="form-group">
                <label>Database Password</label>
                <input type="password" name="db_pass" required>
            </div>

            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" value="gdpanel" required>
                <div class="help">Will be created if it doesn't exist</div>
            </div>

            <input type="hidden" name="db_tested" value="0" id="db-tested">
        </form>

        <div class="buttons">
            <button class="btn btn-secondary" onclick="window.location.href='install.php?step=requirements'">
                ← Back
            </button>
            <button class="btn btn-secondary" onclick="testDatabase()">
                Test Connection
            </button>
            <button class="btn btn-primary" onclick="window.location.href='install.php?step=config'" style="margin-left: 10px;">
                Next →
            </button>
        </div>
    </div>
    <?php
}

/**
 * Render config step
 */
function renderConfig()
{
    ?>
    <div class="step-content">
        <h2>Panel Configuration</h2>
        <p style="margin-bottom: 20px; color: #666;">
            Configure your panel settings.
        </p>

        <form id="config-form">
            <div class="form-group">
                <label>Panel Title</label>
                <input type="text" name="panel_title" value="GDOLS Panel" required>
            </div>

            <div class="form-group">
                <label>Admin Username</label>
                <input type="text" name="admin_username" value="admin" required>
                <div class="help">Username for panel administrator</div>
            </div>

            <div class="form-group">
                <label>Admin Email</label>
                <input type="email" name="admin_email" value="admin@" . gethostname() . "" required>
            </div>

            <div class="form-group">
                <label>Admin Password</label>
                <input type="password" name="admin_password" required minlength="8">
                <div class="help">Minimum 8 characters, must include uppercase, lowercase, and number</div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="admin_password_confirm" required>
            </div>

            <div class="form-group">
                <label>Panel URL</label>
                <input type="text" name="panel_url" value="http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "" required>
            </div>
        </form>

        <div class="buttons">
            <button class="btn btn-secondary" onclick="window.location.href='install.php?step=database'">
                ← Back
            </button>
            <button class="btn btn-primary" onclick="window.location.href='install.php?step=admin'">
                Next →
            </button>
        </div>
    </div>
    <?php
}

/**
 * Render admin step
 */
function renderAdmin()
{
    ?>
    <div class="step-content">
        <h2>Ready to Install</h2>
        <p style="margin-bottom: 20px; color: #666;">
            Review your settings before starting the installation.
        </p>

        <div class="alert alert-info">
            <strong>The installer will:</strong>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>Create the database and tables</li>
                <li>Generate configuration files</li>
                <li>Create admin account</li>
                <li>Set up directory structure</li>
                <li>Configure security settings</li>
            </ul>
        </div>

        <div class="alert alert-warning">
            <strong>⚠️ Important:</strong>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>Make sure you have backed up any important data</li>
                <li>The installation will modify system configurations</li>
                <li>Keep your admin credentials safe</li>
                <li>Delete install.php after installation</li>
            </ul>
        </div>

        <div class="buttons">
            <button class="btn btn-secondary" onclick="window.location.href='install.php?step=config'">
                ← Back
            </button>
            <button class="btn btn-primary" onclick="window.location.href='install.php?step=install'">
                Start Installation →
            </button>
        </div>
    </div>
    <?php
}

/**
 * Render install step
 */
function renderInstall()
{
    ?>
    <div class="step-content">
        <h2>Installing GDOLS Panel</h2>
        <p style="margin-bottom: 20px; color: #666;">
            Please wait while the installer completes...
        </p>

        <div class="loading active">
            <div class="spinner"></div>
            <p>Installing GDOLS Panel...</p>
        </div>

        <form id="install-form">
            <input type="hidden" name="action" value="install">
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                submitInstallation();
            });
        </script>
    </div>
    <?php
}

/**
 * Render complete step
 */
function renderComplete()
{
    $panelUrl =
        "http://" .
        $_SERVER["HTTP_HOST"] .
        dirname($_SERVER["PHP_SELF"]) .
        "/public/"; ?>
    <div class="step-content">
        <div style="text-align: center; padding: 40px 0;">
            <div style="font-size: 64px; margin-bottom: 20px;">🎉</div>
            <h2 style="margin-bottom: 10px;">Installation Complete!</h2>
            <p style="color: #666;">GDOLS Panel has been successfully installed.</p>
        </div>

        <div class="alert alert-success">
            <strong>Next Steps:</strong>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li><a href="<?php echo $panelUrl; ?>" target="_blank">Access your panel</a></li>
                <li>Delete install.php for security</li>
                <li>Set up your first virtual host</li>
                <li>Configure PHP extensions</li>
                <li>Set up firewall rules</li>
            </ul>
        </div>

        <div class="alert alert-warning">
            <strong>⚠️ Security Reminder:</strong>
            <p style="margin-top: 10px;">
                Please delete the <code>install.php</code> file from your server to prevent unauthorized reinstallation.
            </p>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo $panelUrl; ?>" class="btn btn-primary" style="display: inline-block;">
                Launch GDOLS Panel →
            </a>
        </div>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
            <p style="font-size: 12px; color: #999;">
                GDOLS Panel v<?php echo GDOLS_PANEL_VERSION; ?> by <?php echo GDOLS_PANEL_AUTHOR; ?>
            </p>
            <p style="font-size: 12px; color: #999;">
                <a href="<?php echo GDOLS_PANEL_WEBSITE; ?>" target="_blank" style="color: #667eea;"><?php echo GDOLS_PANEL_WEBSITE; ?></a>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Check system requirements
 */
function checkRequirements()
{
    $requirements = [];

    // PHP Version
    $phpVersion = PHP_VERSION;
    $requirements[] = [
        "name" => "PHP Version",
        "required" => true,
        "current" => $phpVersion,
        "status" => version_compare($phpVersion, "8.1.0", ">=")
            ? "success"
            : "error",
        "message" => version_compare($phpVersion, "8.1.0", ">=")
            ? "PHP 8.1+ required"
            : "PHP 8.1 or higher required",
    ];

    // Required PHP extensions
    $requiredExtensions = ["pdo", "pdo_mysql", "json", "mbstring", "curl"];
    foreach ($requiredExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $requirements[] = [
            "name" => "PHP Extension: {$ext}",
            "required" => true,
            "current" => $loaded ? "Loaded" : "Not loaded",
            "status" => $loaded ? "success" : "error",
            "message" => $loaded
                ? "Extension is available"
                : "Extension is required",
        ];
    }

    // Database connection
    try {
        $mysqli = new mysqli("localhost", "root", "");
        $requirements[] = [
            "name" => "MySQL/MariaDB Connection",
            "required" => true,
            "current" => "Available",
            "status" => "success",
            "message" => "Can connect to database server",
        ];
        $mysqli->close();
    } catch (Exception $e) {
        $requirements[] = [
            "name" => "MySQL/MariaDB Connection",
            "required" => true,
            "current" => "Failed",
            "status" => "error",
            "message" => "Cannot connect to database server",
        ];
    }

    // File permissions
    $writableDirs = ["config", "logs", "sessions", "backups"];
    foreach ($writableDirs as $dir) {
        $dirPath = GDOLS_PANEL_ROOT . "/" . $dir;
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0755, true);
        }
        $writable = is_writable($dirPath);
        $requirements[] = [
            "name" => "Directory Writable: {$dir}",
            "required" => true,
            "current" => $writable ? "Writable" : "Not writable",
            "status" => $writable ? "success" : "error",
            "message" => $writable
                ? "Directory is writable"
                : "Directory must be writable",
        ];
    }

    // OpenLiteSpeed
    $olsExists = file_exists("/usr/local/lsws/bin/lswsctrl");
    $requirements[] = [
        "name" => "OpenLiteSpeed",
        "required" => true,
        "current" => $olsExists ? "Installed" : "Not found",
        "status" => $olsExists ? "success" : "error",
        "message" => $olsExists
            ? "OpenLiteSpeed is installed"
            : "OpenLiteSpeed must be installed",
    ];

    // Redis (optional)
    $redisExists =
        file_exists("/usr/bin/redis-server") ||
        file_exists("/usr/local/bin/redis-server");
    $requirements[] = [
        "name" => "Redis Server",
        "required" => false,
        "current" => $redisExists ? "Installed" : "Not found",
        "status" => $redisExists ? "success" : "warning",
        "message" => $redisExists
            ? "Redis is available"
            : "Redis is optional but recommended",
    ];

    return [
        "success" => true,
        "requirements" => $requirements,
    ];
}

/**
 * Test database connection
 */
function testDatabase($data)
{
    $host = $data["db_host"] ?? "localhost";
    $port = $data["db_port"] ?? 3306;
    $user = $data["db_user"] ?? "root";
    $pass = $data["db_pass"] ?? "";
    $name = $data["db_name"] ?? "gdpanel";

    try {
        $mysqli = new mysqli($host, $user, $pass, "", $port);

        if ($mysqli->connect_error) {
            return [
                "success" => false,
                "message" => "Connection failed: " . $mysqli->connect_error,
            ];
        }

        // Test CREATE DATABASE privilege
        $result = $mysqli->query(
            "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        );
        if (!$result) {
            return [
                "success" => false,
                "message" => "Failed to create database. Check permissions.",
            ];
        }

        $mysqli->select_db($name);

        // Test CREATE TABLE privilege
        $result = $mysqli->query("CREATE TABLE test_table (id INT)");
        if ($result) {
            $mysqli->query("DROP TABLE test_table");
        }

        $mysqli->close();

        return [
            "success" => true,
            "message" =>
                "Database connection successful! All privileges verified.",
        ];
    } catch (Exception $e) {
        return [
            "success" => false,
            "message" => "Error: " . $e->getMessage(),
        ];
    }
}

/**
 * Perform installation
 */
function performInstallation($data)
{
    $errors = [];

    // Validate input
    if (
        empty($data["admin_password"]) ||
        $data["admin_password"] !== $data["admin_password_confirm"]
    ) {
        $errors[] = "Passwords do not match";
    }

    if (strlen($data["admin_password"]) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }

    if (!empty($errors)) {
        return [
            "success" => false,
            "message" => implode(", ", $errors),
        ];
    }

    try {
        // Create database connection
        $mysqli = new mysqli(
            $data["db_host"],
            $data["db_user"],
            $data["db_pass"],
            "",
            $data["db_port"],
        );

        if ($mysqli->connect_error) {
            throw new Exception("Database connection failed");
        }

        // Create database
        $mysqli->query(
            "CREATE DATABASE IF NOT EXISTS `{$data["db_name"]}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        );
        $mysqli->select_db($data["db_name"]);

        // Create tables
        $sql = file_get_contents(GDOLS_PANEL_ROOT . "/config/schema.sql");
        if ($sql) {
            $statements = explode(";", $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    if (!$mysqli->query($statement)) {
                        throw new Exception(
                            "Failed to create tables: " . $mysqli->error,
                        );
                    }
                }
            }
        }

        // Create admin user
        $passwordHash = password_hash(
            $data["admin_password"],
            PASSWORD_DEFAULT,
        );
        $stmt = $mysqli->prepare("
            INSERT INTO users (username, password, email, role, status)
            VALUES (?, ?, ?, 'admin', 'active')
        ");
        $stmt->bind_param(
            "sss",
            $data["admin_username"],
            $passwordHash,
            $data["admin_email"],
        );
        $stmt->execute();

        // Generate encryption key
        $encryptionKey = bin2hex(random_bytes(32));

        // Create config.local.php
        $configContent = "<?php\n";
        $configContent .= "// Local configuration\n";
        $configContent .= "define('DB_HOST', '{$data["db_host"]}');\n";
        $configContent .= "define('DB_PORT', {$data["db_port"]});\n";
        $configContent .= "define('DB_NAME', '{$data["db_name"]}');\n";
        $configContent .= "define('DB_USER', '{$data["db_user"]}');\n";
        $configContent .= "define('DB_PASS', '{$data["db_pass"]}');\n";
        $configContent .= "define('ENCRYPTION_KEY', '{$encryptionKey}');\n";
        $configContent .= "define('DEBUG_MODE', false);\n";

        if (
            !file_put_contents(
                GDOLS_PANEL_ROOT . "/config/config.local.php",
                $configContent,
            )
        ) {
            throw new Exception("Failed to create configuration file");
        }

        // Create .htaccess for security
        $htaccessContent = "# Deny access to config files\n";
        $htaccessContent .= "Deny from all\n";
        file_put_contents(
            GDOLS_PANEL_ROOT . "/config/.htaccess",
            $htaccessContent,
        );

        file_put_contents(
            GDOLS_PANEL_ROOT . "/logs/.htaccess",
            $htaccessContent,
        );
        file_put_contents(
            GDOLS_PANEL_ROOT . "/sessions/.htaccess",
            $htaccessContent,
        );

        $mysqli->close();

        return [
            "success" => true,
            "message" => "Installation completed successfully!",
        ];
    } catch (Exception $e) {
        return [
            "success" => false,
            "message" => "Installation failed: " . $e->getMessage(),
        ];
    }
}
