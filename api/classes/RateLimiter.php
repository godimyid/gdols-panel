<?php
/**
 * GDOLS Panel - Rate Limiter Class
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Rate limiting implementation to prevent API abuse and brute force attacks
 */

class RateLimiter {
    private $redis;
    private $config;

    public function __construct($redis = null) {
        $this->config = require CONFIG_PATH . '/rate_limit.php';
        $this->redis = $redis;

        // If Redis is not available, fall back to file-based storage
        if (!$this->redis) {
            $this->initFileStorage();
        }
    }

    /**
     * Check if request is allowed
     *
     * @param string $identifier Unique identifier (IP address, user ID, etc.)
     * @param string $endpoint The endpoint being accessed
     * @return bool True if request is allowed, false otherwise
     */
    public function checkRateLimit($identifier, $endpoint = 'default') {
        $limits = $this->getLimitsForEndpoint($endpoint);

        foreach ($limits as $limit) {
            if (!$this->checkLimit($identifier, $endpoint, $limit)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get rate limit configuration for specific endpoint
     *
     * @param string $endpoint The endpoint name
     * @return array Array of limit configurations
     */
    private function getLimitsForEndpoint($endpoint) {
        $defaultLimits = $this->config['default'];

        if (isset($this->config['endpoints'][$endpoint])) {
            return $this->config['endpoints'][$endpoint];
        }

        return $defaultLimits;
    }

    /**
     * Check specific rate limit
     *
     * @param string $identifier Unique identifier
     * @param string $endpoint Endpoint name
     * @param array $limit Limit configuration
     * @return bool True if within limit, false otherwise
     */
    private function checkLimit($identifier, $endpoint, $limit) {
        $key = $this->getKey($identifier, $endpoint, $limit['window']);
        $current = $this->getCurrentCount($key);

        if ($current >= $limit['max_requests']) {
            $this->logRateLimitExceeded($identifier, $endpoint, $limit);
            return false;
        }

        $this->incrementCounter($key, $limit['window']);
        return true;
    }

    /**
     * Generate rate limit key
     *
     * @param string $identifier Unique identifier
     * @param string $endpoint Endpoint name
     * @param int $window Time window in seconds
     * @return string The generated key
     */
    private function getKey($identifier, $endpoint, $window) {
        return "rate_limit:{$endpoint}:{$identifier}:{$window}";
    }

    /**
     * Get current request count
     *
     * @param string $key Rate limit key
     * @return int Current count
     */
    private function getCurrentCount($key) {
        if ($this->redis) {
            $count = $this->redis->get($key);
            return (int)($count ?: 0);
        } else {
            return $this->getFileBasedCount($key);
        }
    }

    /**
     * Increment request counter
     *
     * @param string $key Rate limit key
     * @param int $window Time window in seconds
     */
    private function incrementCounter($key, $window) {
        if ($this->redis) {
            $this->redis->incr($key);
            $this->redis->expire($key, $window);
        } else {
            $this->incrementFileBasedCounter($key, $window);
        }
    }

    /**
     * Initialize file-based storage for rate limiting
     */
    private function initFileStorage() {
        $storageDir = LOG_PATH . '/rate_limit';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
    }

    /**
     * Get count from file-based storage
     *
     * @param string $key Rate limit key
     * @return int Current count
     */
    private function getFileBasedCount($key) {
        $filename = $this->getStorageFilename($key);

        if (!file_exists($filename)) {
            return 0;
        }

        $data = json_decode(file_get_contents($filename), true);

        // Check if window has expired
        if (time() > $data['expires']) {
            @unlink($filename);
            return 0;
        }

        return $data['count'];
    }

    /**
     * Increment file-based counter
     *
     * @param string $key Rate limit key
     * @param int $window Time window in seconds
     */
    private function incrementFileBasedCounter($key, $window) {
        $filename = $this->getStorageFilename($key);

        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true);

            // Reset if window expired
            if (time() > $data['expires']) {
                $data = [
                    'count' => 1,
                    'expires' => time() + $window
                ];
            } else {
                $data['count']++;
            }
        } else {
            $data = [
                'count' => 1,
                'expires' => time() + $window
            ];
        }

        file_put_contents($filename, json_encode($data));
    }

    /**
     * Get storage filename for key
     *
     * @param string $key Rate limit key
     * @return string Storage filename
     */
    private function getStorageFilename($key) {
        $safeKey = md5($key);
        return LOG_PATH . '/rate_limit/' . $safeKey . '.json';
    }

    /**
     * Log rate limit exceeded event
     *
     * @param string $identifier Unique identifier
     * @param string $endpoint Endpoint name
     * @param array $limit Limit configuration
     */
    private function logRateLimitExceeded($identifier, $endpoint, $limit) {
        $logMessage = sprintf(
            "Rate limit exceeded - IP: %s, Endpoint: %s, Limit: %d requests per %d seconds",
            $identifier,
            $endpoint,
            $limit['max_requests'],
            $limit['window']
        );

        error_log($logMessage);
    }

    /**
     * Get remaining requests for a limit
     *
     * @param string $identifier Unique identifier
     * @param string $endpoint Endpoint name
     * @param array $limit Limit configuration
     * @return int Remaining requests
     */
    public function getRemainingRequests($identifier, $endpoint, $limit) {
        $key = $this->getKey($identifier, $endpoint, $limit['window']);
        $current = $this->getCurrentCount($key);
        return max(0, $limit['max_requests'] - $current);
    }

    /**
     * Reset rate limit for identifier
     *
     * @param string $identifier Unique identifier
     * @param string $endpoint Endpoint name
     */
    public function resetRateLimit($identifier, $endpoint = null) {
        if ($this->redis) {
            $pattern = "rate_limit:" . ($endpoint ?: "*") . ":{$identifier}:*";
            $keys = $this->redis->keys($pattern);

            foreach ($keys as $key) {
                $this->redis->del($key);
            }
        } else {
            $pattern = LOG_PATH . '/rate_limit/' . md5("rate_limit:" . ($endpoint ?: "*") . ":{$identifier}:*") . '.json';

            if ($endpoint) {
                $filename = $this->getStorageFilename("rate_limit:{$endpoint}:{$identifier}:");
                if (file_exists($filename)) {
                    @unlink($filename);
                }
            } else {
                // Clear all rate limit files for this identifier
                foreach (glob(LOG_PATH . '/rate_limit/*.json') as $filename) {
                    @unlink($filename);
                }
            }
        }
    }

    /**
     * Get rate limit status for response headers
     *
     * @param string $identifier Unique identifier
     * @param string $endpoint Endpoint name
     * @return array Array with rate limit information
     */
    public function getRateLimitHeaders($identifier, $endpoint = 'default') {
        $limits = $this->getLimitsForEndpoint($endpoint);
        $headers = [];

        foreach ($limits as $index => $limit) {
            $remaining = $this->getRemainingRequests($identifier, $endpoint, $limit);

            $headers[] = [
                'limit' => $limit['max_requests'],
                'remaining' => $remaining,
                'reset' => time() + $limit['window'],
                'window' => $limit['window']
            ];
        }

        return $headers;
    }

    /**
     * Clean up expired rate limit records (for file-based storage)
     */
    public function cleanupExpiredRecords() {
        if ($this->redis) {
            // Redis automatically handles expiration
            return;
        }

        $storageDir = LOG_PATH . '/rate_limit';
        $files = glob($storageDir . '/*.json');

        foreach ($files as $filename) {
            if (file_exists($filename)) {
                $data = json_decode(file_get_contents($filename), true);
                if ($data && time() > $data['expires']) {
                    @unlink($filename);
                }
            }
        }
    }
}
