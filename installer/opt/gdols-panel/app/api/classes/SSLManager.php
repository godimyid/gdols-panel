<?php
/**
 * GDOLS Panel - SSL Manager Class
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: SSL certificate management with Let's Encrypt integration and auto-renewal
 */

// Prevent direct access
if (!defined("GDOLS_PANEL_ACCESS")) {
    die("Direct access not permitted");
}

class SSLManager
{
    private static $instance = null;
    private $conn;
    private $logger;
    private $system;
    private $certbotPath = "/usr/bin/certbot";
    private $configPath = "/etc/letsencrypt";

    public function __construct()
    {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
        $this->logger = Logger::getInstance();
        $this->system = System::getInstance();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Install SSL certificate using Let's Encrypt (Certbot)
     *
     * @param string $domain Domain name
     * @param string $email Email for Let's Encrypt notifications
     * @param bool $force Force reinstall even if certificate exists
     * @return array Result with success status and message
     */
    public function installSSLCertificate($domain, $email, $force = false)
    {
        // Validate domain
        if (!$this->validateDomain($domain)) {
            return [
                "success" => false,
                "message" => "Invalid domain format",
            ];
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                "success" => false,
                "message" => "Invalid email format",
            ];
        }

        // Check if certificate already exists
        if (!$force && $this->certificateExists($domain)) {
            return [
                "success" => false,
                "message" =>
                    "SSL certificate already exists for this domain. Use force=true to reinstall.",
            ];
        }

        // Check if Certbot is installed
        if (!$this->isCertbotInstalled()) {
            $result = $this->installCertbot();
            if (!$result["success"]) {
                return $result;
            }
        }

        // Stop OpenLiteSpeed temporarily
        $this->system->manageOLS("stop");

        // Request certificate from Let's Encrypt
        $command = sprintf(
            "%s certonly --standalone --non-interactive --agree-tos --email %s -d %s --force-renewal",
            $this->certbotPath,
            escapeshellarg($email),
            escapeshellarg($domain),
        );

        $result = $this->system->executeCommand($command, true);

        // Start OpenLiteSpeed
        $this->system->manageOLS("start");

        if (!$result["success"]) {
            $this->logger->logError(
                "SSL certificate installation failed for {$domain}: " .
                    $result["output"],
            );
            return [
                "success" => false,
                "message" =>
                    'Failed to obtain SSL certificate from Let\'s Encrypt',
                "output" => $result["output"],
            ];
        }

        // Configure OpenLiteSpeed to use the certificate
        $configResult = $this->configureSSLForDomain($domain);

        if (!$configResult["success"]) {
            return [
                "success" => false,
                "message" =>
                    "Certificate obtained but failed to configure OpenLiteSpeed",
                "details" => $configResult["message"],
            ];
        }

        // Update database
        try {
            $stmt = $this->conn->prepare("
                UPDATE virtual_hosts
                SET ssl_enabled = 1,
                    ssl_cert = ?,
                    ssl_key = ?,
                    ssl_issuer = 'Let\\'s Encrypt',
                    ssl_auto_renew = 1
                WHERE domain = ?
            ");
            $stmt->execute([
                $this->getCertificatePath($domain, "cert"),
                $this->getCertificatePath($domain, "key"),
                $domain,
            ]);
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to update SSL info in database: " . $e->getMessage(),
            );
        }

        // Set up auto-renewal cron job
        $this->setupAutoRenewal();

        $this->logger->logActivity("ssl_install", "ssl_certificate", null, [
            "domain" => $domain,
            "issuer" => 'Let\'s Encrypt',
            "auto_renew" => true,
        ]);

        return [
            "success" => true,
            "message" => "SSL certificate installed successfully for {$domain}",
            "certificate_info" => $this->getCertificateInfo($domain),
        ];
    }

    /**
     * Renew SSL certificate
     *
     * @param string $domain Domain name
     * @param bool $force Force renewal even if not due
     * @return array Result with success status and message
     */
    public function renewSSLCertificate($domain, $force = false)
    {
        if (!$this->certificateExists($domain)) {
            return [
                "success" => false,
                "message" => "No SSL certificate found for this domain",
            ];
        }

        $command = $this->certbotPath . " renew";

        if ($force) {
            $command .= " --force-renewal";
        }

        // Add domain-specific renewal
        $command .= " --cert-name " . escapeshellarg($domain);

        $command .= " --non-interactive --quiet";

        $result = $this->system->executeCommand($command, true);

        if (!$result["success"]) {
            $this->logger->logError(
                "SSL certificate renewal failed for {$domain}: " .
                    $result["output"],
            );
            return [
                "success" => false,
                "message" => "Failed to renew SSL certificate",
                "output" => $result["output"],
            ];
        }

        // Restart OpenLiteSpeed to apply new certificate
        $this->system->manageOLS("restart");

        $this->logger->logActivity("ssl_renew", "ssl_certificate", null, [
            "domain" => $domain,
            "force_renewed" => $force,
        ]);

        return [
            "success" => true,
            "message" => "SSL certificate renewed successfully for {$domain}",
            "certificate_info" => $this->getCertificateInfo($domain),
        ];
    }

    /**
     * Renew all due SSL certificates
     *
     * @return array Result with success status and details
     */
    public function renewAllDueCertificates()
    {
        if (!$this->isCertbotInstalled()) {
            return [
                "success" => false,
                "message" => "Certbot is not installed",
            ];
        }

        $command =
            $this->certbotPath .
            " renew --non-interactive --quiet --no-self-upgrade";

        $result = $this->system->executeCommand($command, true);

        // Check if any certificates were renewed
        $renewed = preg_match(
            "/No renewals were attempted|The following certs have been renewed/i",
            $result["output"],
        );
        $hasOutput = !empty($result["output"]);

        if ($result["success"] && $hasOutput) {
            // Restart OpenLiteSpeed to apply renewed certificates
            $this->system->manageOLS("restart");

            $this->logger->logActivity(
                "ssl_renew_all",
                "ssl_certificate",
                null,
                [
                    "output" => $result["output"],
                ],
            );

            return [
                "success" => true,
                "message" => "SSL certificates check completed",
                "output" => $result["output"],
            ];
        }

        return [
            "success" => true,
            "message" =>
                "SSL certificates check completed - no renewals needed",
            "output" => $result["output"],
        ];
    }

    /**
     * Get SSL certificate information
     *
     * @param string $domain Domain name
     * @return array Certificate information
     */
    public function getCertificateInfo($domain)
    {
        $certFile = $this->getCertificatePath($domain, "cert");

        if (!file_exists($certFile)) {
            return [
                "has_certificate" => false,
                "domain" => $domain,
            ];
        }

        $certData = file_get_contents($certFile);
        $certInfo = openssl_x509_parse($certData);

        if (!$certInfo) {
            return [
                "has_certificate" => false,
                "domain" => $domain,
                "error" => "Invalid certificate file",
            ];
        }

        $validFrom = date("Y-m-d H:i:s", $certInfo["validFrom_time_t"]);
        $validTo = date("Y-m-d H:i:s", $certInfo["validTo_time_t"]);
        $daysUntilExpiry = floor(
            ($certInfo["validTo_time_t"] - time()) / 86400,
        );
        $isExpiringSoon = $daysUntilExpiry <= 30;

        return [
            "has_certificate" => true,
            "valid" => true,
            "domain" => $domain,
            "subject" => $certInfo["subject"]["CN"] ?? "",
            "issuer" => $certInfo["issuer"]["O"] ?? "",
            "valid_from" => $validFrom,
            "valid_to" => $validTo,
            "days_until_expiry" => $daysUntilExpiry,
            "expired" => $certInfo["validTo_time_t"] < time(),
            "expiring_soon" => $isExpiringSoon,
            "auto_renew_enabled" => $this->isAutoRenewalEnabled($domain),
        ];
    }

    /**
     * Check if SSL certificate exists for domain
     *
     * @param string $domain Domain name
     * @return bool True if certificate exists
     */
    private function certificateExists($domain)
    {
        $certFile = $this->getCertificatePath($domain, "cert");
        return file_exists($certFile);
    }

    /**
     * Get certificate file path
     *
     * @param string $domain Domain name
     * @param string $type Type of file (cert, key, chain)
     * @return string Full path to certificate file
     */
    private function getCertificatePath($domain, $type = "cert")
    {
        $livePath = $this->configPath . "/live/" . $domain;

        switch ($type) {
            case "cert":
                return $livePath . "/fullchain.pem";
            case "key":
                return $livePath . "/privkey.pem";
            case "chain":
                return $livePath . "/chain.pem";
            default:
                return $livePath . "/fullchain.pem";
        }
    }

    /**
     * Configure OpenLiteSpeed to use SSL certificate
     *
     * @param string $domain Domain name
     * @return array Result with success status
     */
    private function configureSSLForDomain($domain)
    {
        $certFile = $this->getCertificatePath($domain, "cert");
        $keyFile = $this->getCertificatePath($domain, "key");

        if (!file_exists($certFile) || !file_exists($keyFile)) {
            return [
                "success" => false,
                "message" =>
                    'Certificate files not found after Let\'s Encrypt request',
            ];
        }

        // Create virtual host certificate directory if it doesn't exist
        $vhCertDir = OLS_VHOSTS . "/{$domain}/cert";
        if (!is_dir($vhCertDir)) {
            mkdir($vhCertDir, 0755, true);
        }

        // Copy certificates to OpenLiteSpeed directory
        $vhCertFile = $vhCertDir . "/{$domain}.crt";
        $vhKeyFile = $vhCertDir . "/{$domain}.key";

        if (!copy($certFile, $vhCertFile) || !copy($keyFile, $vhKeyFile)) {
            return [
                "success" => false,
                "message" =>
                    "Failed to copy certificate files to virtual host directory",
            ];
        }

        // Update virtual host configuration to enable SSL
        $vhconfFile = OLS_VHOSTS . "/{$domain}/vhconf.conf";

        if (file_exists($vhconfFile)) {
            $vhconfContent = file_get_contents($vhconfFile);

            // Check if SSL is already configured
            if (strpos($vhconfContent, "vhssl") === false) {
                // Add SSL configuration
                $sslConfig = "
  vhssl 1 {
    sslCertFile                 {$vhCertFile}
    sslKeyFile                  {$vhKeyFile}
    certChain                   1
    enableSpdy                  15
    enableStapling              1
    ocspRespMaxAge              86400
  }
";
                // Insert SSL configuration before the closing brace
                $vhconfContent = str_replace("}", $sslConfig, $vhconfContent);

                file_put_contents($vhconfFile, $vhconfContent);
            }
        }

        return [
            "success" => true,
            "message" => "SSL configured successfully for OpenLiteSpeed",
        ];
    }

    /**
     * Set up auto-renewal cron job
     *
     * @return array Result with success status
     */
    private function setupAutoRenewal()
    {
        $cronCommand = $this->certbotPath . " renew --quiet --no-self-upgrade";
        $cronEntry = "0 3 * * * {$cronCommand} >> /var/log/ssl-renewal.log 2>&1\n";

        // Add to crontab if not exists
        $result = $this->system->executeCommand(
            "crontab -l 2>/dev/null | grep -q '{$certbotPath} renew' || (crontab -l 2>/dev/null; echo '{$cronEntry}') | crontab -",
            true,
        );

        return [
            "success" => $result["success"],
            "message" => $result["success"]
                ? "Auto-renewal cron job configured"
                : "Failed to configure auto-renewal",
        ];
    }

    /**
     * Check if auto-renewal is enabled for domain
     *
     * @param string $domain Domain name
     * @return bool True if auto-renewal is enabled
     */
    private function isAutoRenewalEnabled($domain)
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT ssl_auto_renew FROM virtual_hosts WHERE domain = ?",
            );
            $stmt->execute([$domain]);
            $result = $stmt->fetch();

            return $result && $result["ssl_auto_renew"] == 1;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Check if Certbot is installed
     *
     * @return bool True if Certbot is installed
     */
    private function isCertbotInstalled()
    {
        return file_exists($this->certbotPath) &&
            is_executable($this->certbotPath);
    }

    /**
     * Install Certbot
     *
     * @return array Result with success status
     */
    private function installCertbot()
    {
        $this->logger->logActivity("certbot_install", "system", null, [
            "action" => "Installing Certbot",
        ]);

        $result = $this->system->executeCommand(
            "apt update && apt install -y certbot",
            true,
        );

        return [
            "success" => $result["success"],
            "message" => $result["success"]
                ? "Certbot installed successfully"
                : "Failed to install Certbot",
            "output" => $result["output"],
        ];
    }

    /**
     * Validate domain format
     *
     * @param string $domain Domain to validate
     * @return bool True if domain is valid
     */
    private function validateDomain($domain)
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN) !== false;
    }

    /**
     * Get all SSL certificates with their status
     *
     * @return array List of certificates with information
     */
    public function getAllCertificates()
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT domain, ssl_enabled, ssl_issuer, ssl_auto_renew
                FROM virtual_hosts
                WHERE ssl_enabled = 1
                ORDER BY domain ASC
            ");
            $stmt->execute();
            $vhosts = $stmt->fetchAll();

            $certificates = [];
            foreach ($vhosts as $vhost) {
                $certInfo = $this->getCertificateInfo($vhost["domain"]);
                $certificates[] = array_merge($certInfo, [
                    "issuer" => $vhost["ssl_issuer"],
                    "auto_renew" => $vhost["ssl_auto_renew"] == 1,
                ]);
            }

            return $certificates;
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to get SSL certificates: " . $e->getMessage(),
            );
            return [];
        }
    }

    /**
     * Remove SSL certificate
     *
     * @param string $domain Domain name
     * @return array Result with success status
     */
    public function removeSSLCertificate($domain)
    {
        // Remove Let's Encrypt certificate
        $command =
            $this->certbotPath .
            " delete --cert-name " .
            escapeshellarg($domain) .
            " --non-interactive";
        $result = $this->system->executeCommand($command, true);

        // Update database
        try {
            $stmt = $this->conn->prepare("
                UPDATE virtual_hosts
                SET ssl_enabled = 0,
                    ssl_cert = NULL,
                    ssl_key = NULL,
                    ssl_issuer = NULL,
                    ssl_auto_renew = 0
                WHERE domain = ?
            ");
            $stmt->execute([$domain]);
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to update SSL info in database: " . $e->getMessage(),
            );
        }

        $this->logger->logActivity("ssl_remove", "ssl_certificate", null, [
            "domain" => $domain,
        ]);

        return [
            "success" => $result["success"],
            "message" => $result["success"]
                ? "SSL certificate removed for {$domain}"
                : "Failed to remove SSL certificate",
        ];
    }

    /**
     * Enable or disable auto-renewal for a domain
     *
     * @param string $domain Domain name
     * @param bool $enable Enable or disable auto-renewal
     * @return array Result with success status
     */
    public function setAutoRenewal($domain, $enable = true)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE virtual_hosts
                SET ssl_auto_renew = ?
                WHERE domain = ?
            ");
            $stmt->execute([$enable ? 1 : 0, $domain]);

            $this->logger->logActivity(
                "ssl_autorenew_toggle",
                "ssl_certificate",
                null,
                [
                    "domain" => $domain,
                    "enabled" => $enable,
                ],
            );

            return [
                "success" => true,
                "message" => $enable
                    ? "Auto-renewal enabled for {$domain}"
                    : "Auto-renewal disabled for {$domain}",
            ];
        } catch (PDOException $e) {
            $this->logger->logError(
                "Failed to update auto-renewal setting: " . $e->getMessage(),
            );
            return [
                "success" => false,
                "message" => "Failed to update auto-renewal setting",
            ];
        }
    }

    /**
     * Get SSL statistics
     *
     * @return array SSL statistics
     */
    public function getSSLStatistics()
    {
        $certificates = $this->getAllCertificates();

        $total = count($certificates);
        $expired = 0;
        $expiringSoon = 0;
        $valid = 0;
        $letsEncrypt = 0;

        foreach ($certificates as $cert) {
            if ($cert["expired"]) {
                $expired++;
            } elseif ($cert["expiring_soon"]) {
                $expiringSoon++;
            } else {
                $valid++;
            }

            if (stripos($cert["issuer"], "Let's Encrypt") !== false) {
                $letsEncrypt++;
            }
        }

        return [
            "total" => $total,
            "valid" => $valid,
            "expired" => $expired,
            "expiring_soon" => $expiringSoon,
            "lets_encrypt" => $letsEncrypt,
            "certbot_installed" => $this->isCertbotInstalled(),
            "auto_renewal_enabled" => $letsEncrypt > 0, // Assume enabled if Let's Encrypt certs exist
        ];
    }
}
