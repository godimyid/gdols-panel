```path/to/GDOLS Panel/api/auth.php#L1-200
<?php
/**
 * GDOLS Panel - Authentication API Endpoint
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles user authentication, registration, and session management
 */

// Load bootstrap
require_once dirname(__DIR__) . '/api/bootstrap.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            handleLogin();
            break;

        case 'logout':
            handleLogout();
            break;

        case 'check':
            handleCheck();
            break;

        case 'register':
            handleRegister();
            break;

        case 'forgot_password':
            handleForgotPassword();
            break;

        case 'reset_password':
            handleResetPassword();
            break;

        default:
            errorResponse('Invalid action', 400);
    }
} catch (Exception $e) {
    errorResponse('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Handle user login
 */
function handleLogin() {
    global $security, $logger;

    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    // Get input
    $input = json_decode(file_get_contents('php://input'), true);

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $remember = $input['remember'] ?? false;

    // Validate input
    if (empty($username) || empty($password)) {
        errorResponse('Username and password are required');
    }

    // Sanitize input
    $username = $security->sanitize($username);

    // Authenticate
    $result = $security->authenticate($username, $password);

    if ($result['success']) {
        // Set remember me cookie if requested
        if ($remember) {
            $token = $security->generateToken();
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days

            setcookie(
                'remember_token',
                $token,
                $expiry,
                '/',
                '',
                false, // Set to true if using HTTPS
                true
            );

            // Store token in database (implement this)
            // For now, we'll just set the cookie
        }

        // Log successful login
        $logger->logActivity('user_login', 'user', $result['user']['id'], [
            'username' => $result['user']['username']
        ]);

        successResponse([
            'user' => $result['user'],
            'csrf_token' => $_SESSION[CSRF_TOKEN_NAME]
        ], 'Login successful');
    } else {
        // Log failed login attempt
        $logger->logActivity('login_failed', 'user', null, [
            'username' => $username,
            'reason' => $result['message']
        ]);

        errorResponse($result['message'], 401);
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    global $security, $logger;

    $userId = $_SESSION['user_id'] ?? null;

    // Logout
    $result = $security->logout();

    if ($result['success']) {
        // Clear remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
            unset($_COOKIE['remember_token']);
        }

        // Log logout
        if ($userId) {
            $logger->logActivity('user_logout', 'user', $userId);
        }

        successResponse([], 'Logged out successfully');
    } else {
        errorResponse('Logout failed', 500);
    }
}

/**
 * Handle authentication check
 */
function handleCheck() {
    global $user;

    if ($user) {
        successResponse([
            'authenticated' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'last_login' => $user['last_login']
            ],
            'csrf_token' => $_SESSION[CSRF_TOKEN_NAME]
        ], 'Authenticated');
    } else {
        successResponse([
            'authenticated' => false
        ], 'Not authenticated');
    }
}

/**
 * Handle user registration
 */
function handleRegister() {
    global $conn, $security, $logger;

    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    // Check if registration is allowed (optional setting)
    // For now, we'll require admin approval

    // Get input
    $input = json_decode(file_get_contents('php://input'), true);

    $username = $input['username'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        errorResponse('All fields are required');
    }

    // Sanitize input
    $username = $security->sanitize($username);
    $email = $security->sanitize($email);

    // Validate email
    if (!$security->validateEmail($email)) {
        errorResponse('Invalid email address');
    }

    // Validate password strength
    $passwordValidation = $security->validatePassword($password);
    if (!$passwordValidation['valid']) {
        errorResponse($passwordValidation['message']);
    }

    // Check if passwords match
    if ($password !== $confirmPassword) {
        errorResponse('Passwords do not match');
    }

    // Check if username already exists
    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            errorResponse('Username already exists');
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            errorResponse('Email already exists');
        }

        // Hash password
        $hashedPassword = $security->hashPassword($password);

        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, email, role, status)
            VALUES (?, ?, ?, 'user', 'active')
        ");
        $stmt->execute([$username, $hashedPassword, $email]);

        $userId = $conn->lastInsertId();

        // Log registration
        $logger->logActivity('user_register', 'user', $userId, [
            'username' => $username,
            'email' => $email
        ]);

        successResponse([
            'user_id' => $userId
        ], 'Registration successful');

    } catch (PDOException $e) {
        $logger->logError("Registration failed: " . $e->getMessage());
        errorResponse('Registration failed. Please try again.', 500);
    }
}

/**
 * Handle forgot password request
 */
function handleForgotPassword() {
    global $conn, $security, $logger;

    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';

    if (empty($email)) {
        errorResponse('Email is required');
    }

    $email = $security->sanitize($email);

    try {
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate reset token
            $token = $security->generateToken();
            $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            // Store token in database (you'll need to create a password_resets table)
            // For now, we'll just log it
            $logger->logActivity('password_reset_request', 'user', $user['id'], [
                'email' => $email,
                'token' => $token
            ]);

            // In production, send email with reset link
            // For now, just return success
            successResponse([
                'message' => 'If the email exists, a password reset link has been sent'
            ], 'Password reset initiated');
        } else {
            // Don't reveal if email exists or not
            successResponse([
                'message' => 'If the email exists, a password reset link has been sent'
            ], 'Password reset initiated');
        }

    } catch (PDOException $e) {
        $logger->logError("Forgot password failed: " . $e->getMessage());
        errorResponse('An error occurred. Please try again.', 500);
    }
}

/**
 * Handle password reset
 */
function handleResetPassword() {
    global $conn, $security, $logger;

    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $token = $input['token'] ?? '';
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if (empty($token) || empty($password)) {
        errorResponse('Token and password are required');
    }

    // Validate password strength
    $passwordValidation = $security->validatePassword($password);
    if (!$passwordValidation['valid']) {
        errorResponse($passwordValidation['message']);
    }

    // Check if passwords match
    if ($password !== $confirmPassword) {
        errorResponse('Passwords do not match');
    }

    // Verify token and update password
    // This requires a password_resets table
    // For now, we'll return an error
    errorResponse('Password reset functionality requires database setup', 501);
}
