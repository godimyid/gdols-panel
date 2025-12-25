<?php
/**
 * GDOLS Panel - Security Class
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles authentication, authorization, and security operations
 */

// Prevent direct access
if (!defined("GDOLS_PANEL_ACCESS")) {
    die("Direct access not permitted");
}

class Security
{
    private static $instance = null;
    private $conn;

    public function __construct()
    {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Authenticate user with username and password
     */
    public function authenticate($username, $password)
    {
        try {
            // Get user from database
            $stmt = $this->conn->prepare(
                "SELECT * FROM users WHERE username = ? OR email = ?",
            );
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user) {
                return [
                    "success" => false,
                    "message" => "Invalid credentials",
                ];
            }

            // Check if account is locked
            if ($user["status"] === "locked") {
                if (
                    $user["locked_until"] &&
                    strtotime($user["locked_until"]) > time()
                ) {
                    return [
                        "success" => false,
                        "message" =>
                            "Account is temporarily locked. Please try again later.",
                    ];
                } else {
                    // Unlock account
                    $this->unlockAccount($user["id"]);
                }
            }

            // Check if account is suspended
            if ($user["status"] === "suspended") {
                return [
                    "success" => false,
                    "message" => "Account is suspended",
                ];
            }

            // Verify password
            if (!password_verify($password, $user["password"])) {
                // Increment login attempts
                $this->incrementLoginAttempts($user["id"]);

                return [
                    "success" => false,
                    "message" => "Invalid credentials",
                ];
            }

            // Reset login attempts
            $this->resetLoginAttempts($user["id"]);

            // Update last login
            $this->updateLastLogin($user["id"]);

            // Set session
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            return [
                "success" => true,
                "message" => "Login successful",
                "user" => [
                    "id" => $user["id"],
                    "username" => $user["username"],
                    "email" => $user["email"],
                    "role" => $user["role"],
                ],
            ];
        } catch (PDOException $e) {
            error_log("Authentication failed: " . $e->getMessage());
            return [
                "success" => false,
                "message" => "Authentication failed",
            ];
        }
    }

    /**
     * Logout user
     */
    public function logout()
    {
        // Destroy session
        session_destroy();
        unset($_SESSION);

        return [
            "success" => true,
            "message" => "Logged out successfully",
        ];
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRF()
    {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRF()
    {
        $headers = getallheaders();
        $token = "";

        // Check header
        if (isset($headers["X-CSRF-Token"])) {
            $token = $headers["X-CSRF-Token"];
        }

        // Check POST data
        if (isset($_POST[CSRF_TOKEN_NAME])) {
            $token = $_POST[CSRF_TOKEN_NAME];
        }

        // Check JSON input
        if (
            isset($_SERVER["CONTENT_TYPE"]) &&
            strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false
        ) {
            $input = json_decode(file_get_contents("php://input"), true);
            if (isset($input[CSRF_TOKEN_NAME])) {
                $token = $input[CSRF_TOKEN_NAME];
            }
        }

        return isset($_SESSION[CSRF_TOKEN_NAME]) &&
            hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /**
     * Check rate limiting
     */
    public function checkRateLimit()
    {
        $ip = $_SERVER["REMOTE_ADDR"];
        $key = "rate_limit_" . $ip;

        // Use Redis if available, otherwise use session
        if (function_exists("redis_connect")) {
            try {
                $redis = new Redis();
                $redis->connect(REDIS_HOST, REDIS_PORT);

                if (defined("REDIS_PASS") && REDIS_PASS) {
                    $redis->auth(REDIS_PASS);
                }

                $current = $redis->get($key);

                if (!$current) {
                    $redis->setex($key, 60, 1);
                } else {
                    $redis->incr($key);

                    if ($current >= API_RATE_LIMIT) {
                        http_response_code(429);
                        echo json_encode([
                            "success" => false,
                            "message" =>
                                "Too many requests. Please try again later.",
                        ]);
                        exit();
                    }
                }

                $redis->close();
            } catch (Exception $e) {
                error_log("Redis rate limit check failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Increment login attempts
     */
    private function incrementLoginAttempts($userId)
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?",
            );
            $stmt->execute([$userId]);

            // Check if should lock account
            $stmt = $this->conn->prepare(
                "SELECT login_attempts FROM users WHERE id = ?",
            );
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            // Get max login attempts from settings
            $maxAttempts = $this->getSetting("max_login_attempts", 5);

            if ($user["login_attempts"] >= $maxAttempts) {
                // Lock account for specified duration
                $lockoutDuration = $this->getSetting("lockout_duration", 900); // 15 minutes default
                $lockedUntil = date("Y-m-d H:i:s", time() + $lockoutDuration);

                $stmt = $this->conn->prepare(
                    "UPDATE users SET status = 'locked', locked_until = ? WHERE id = ?",
                );
                $stmt->execute([$lockedUntil, $userId]);
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to increment login attempts: " . $e->getMessage(),
            );
        }
    }

    /**
     * Reset login attempts
     */
    private function resetLoginAttempts($userId)
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?",
            );
            $stmt->execute([$userId]);

            // Ensure account is active
            $stmt = $this->conn->prepare(
                "UPDATE users SET status = 'active' WHERE id = ? AND status = 'locked'",
            );
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Failed to reset login attempts: " . $e->getMessage());
        }
    }

    /**
     * Unlock account
     */
    private function unlockAccount($userId)
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE users SET status = 'active', login_attempts = 0, locked_until = NULL WHERE id = ?",
            );
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Failed to unlock account: " . $e->getMessage());
        }
    }

    /**
     * Update last login time
     */
    private function updateLastLogin($userId)
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
            );
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Failed to update last login: " . $e->getMessage());
        }
    }

    /**
     * Hash password
     */
    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Validate password strength
     */
    public function validatePassword($password)
    {
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            return [
                "valid" => false,
                "message" =>
                    "Password must be at least " .
                    MIN_PASSWORD_LENGTH .
                    " characters long",
            ];
        }

        // Check for at least one uppercase letter
        if (!preg_match("/[A-Z]/", $password)) {
            return [
                "valid" => false,
                "message" =>
                    "Password must contain at least one uppercase letter",
            ];
        }

        // Check for at least one lowercase letter
        if (!preg_match("/[a-z]/", $password)) {
            return [
                "valid" => false,
                "message" =>
                    "Password must contain at least one lowercase letter",
            ];
        }

        // Check for at least one number
        if (!preg_match("/[0-9]/", $password)) {
            return [
                "valid" => false,
                "message" => "Password must contain at least one number",
            ];
        }

        return [
            "valid" => true,
            "message" => "Password is valid",
        ];
    }

    /**
     * Sanitize input data
     */
    public function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([$this, "sanitize"], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, "UTF-8");
    }

    /**
     * Validate email
     */
    public function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate secure random token
     */
    public function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Encrypt data
     */
    public function encrypt($data)
    {
        $key = defined("ENCRYPTION_KEY") ? ENCRYPTION_KEY : "";
        if (empty($key)) {
            return $data;
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, "AES-256-CBC", $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     */
    public function decrypt($data)
    {
        $key = defined("ENCRYPTION_KEY") ? ENCRYPTION_KEY : "";
        if (empty($key)) {
            return $data;
        }

        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, "AES-256-CBC", $key, 0, $iv);
    }

    /**
     * Get system setting
     */
    private function getSetting($key, $default = null)
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT setting_value FROM system_settings WHERE setting_key = ?",
            );
            $stmt->execute([$key]);
            $result = $stmt->fetch();

            return $result ? $result["setting_value"] : $default;
        } catch (PDOException $e) {
            error_log("Failed to get setting: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Check if user has permission
     */
    public function hasPermission($userId, $permission)
    {
        try {
            $stmt = $this->conn->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                return false;
            }

            // Admin has all permissions
            if ($user["role"] === "admin") {
                return true;
            }

            // Implement additional permission checks here
            return false;
        } catch (PDOException $e) {
            error_log("Permission check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate API key
     */
    public function validateAPIKey($apiKey)
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM api_keys WHERE api_key = ? AND status = 'active'",
            );
            $stmt->execute([$apiKey]);
            $key = $stmt->fetch();

            if (!$key) {
                return false;
            }

            // Check if key has expired
            if ($key["expires_at"] && strtotime($key["expires_at"]) < time()) {
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("API key validation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current user IP
     */
    public function getClientIP()
    {
        $ip = "";

        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ip = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0];
        } elseif (isset($_SERVER["HTTP_X_REAL_IP"])) {
            $ip = $_SERVER["HTTP_X_REAL_IP"];
        } elseif (isset($_SERVER["REMOTE_ADDR"])) {
            $ip = $_SERVER["REMOTE_ADDR"];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : "0.0.0.0";
    }

    /**
     * Check if request is from localhost
     */
    public function isLocalhost()
    {
        $ip = $this->getClientIP();
        return in_array($ip, ["127.0.0.1", "::1", "localhost"]);
    }
}
