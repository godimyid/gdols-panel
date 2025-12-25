<?php
/**
 * GDOLS Panel - Firewall API Endpoint
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Handles UFW firewall management
 */

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'status':
            handleStatus();
            break;

        case 'list':
            handleList();
            break;

        case 'add':
            handleAdd();
            break;

        case 'delete':
            handleDelete();
            break;

        case 'enable':
            handleEnable();
            break;

        case 'disable':
            handleDisable();
            break;

        case 'toggle':
            handleToggle();
            break;

        case 'get_rule':
            handleGetRule();
            break;

        case 'update_rule':
            handleUpdateRule();
            break;

        default:
            errorResponse('Invalid action', 400);
    }
} catch (Exception $e) {
    errorResponse('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Handle get firewall status
 */
function handleStatus() {
    global $system;

    requireAdmin();

    try {
        $result = $system->manageFirewall('status');

        if ($result['success']) {
            // Parse UFW status
            $status = parseUFWStatus($result['status']);

            successResponse([
                'status' => $status,
                'enabled' => $status['active'],
                'rules_count' => count($status['rules']),
                'logging' => $status['logging']
            ], 'Firewall status retrieved');
        } else {
            errorResponse($result['message'] ?? 'Failed to get firewall status', 500);
        }

    } catch (Exception $e) {
        errorResponse('Failed to retrieve firewall status: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle list firewall rules
 */
function handleList() {
    global $conn;

    requireAdmin();

    try {
        $stmt = $conn->prepare("SELECT * FROM firewall_rules ORDER BY id DESC");
        $stmt->execute();
        $rules = $stmt->fetchAll();

        // Get actual UFW status
        $result = shell_exec("sudo ufw status numbered 2>&1");
        $ufwRules = parseUFWRules($result);

        // Merge database rules with UFW rules
        foreach ($rules as &$rule) {
            $rule['applied'] = isset($ufwRules[$rule['rule_id']]);
            $rule['ufw_number'] = $ufwRules[$rule['rule_id']]['number'] ?? null;
        }

        successResponse([
            'rules' => $rules,
            'total' => count($rules),
            'active' => count(array_filter($rules, fn($r) => $r['enabled'])),
            'applied' => count(array_filter($rules, fn($r) => $r['applied']))
        ], 'Firewall rules retrieved');

    } catch (PDOException $e) {
        errorResponse('Failed to retrieve firewall rules: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle add firewall rule
 */
function handleAdd() {
    global $conn, $system, $logger;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($input['action'])) {
        errorResponse('Action (allow/deny) is required');
    }

    if (!in_array($input['action'], ['allow', 'deny', 'limit'])) {
        errorResponse('Invalid action. Must be: allow, deny, or limit');
    }

    if (empty($input['port'])) {
        errorResponse('Port is required');
    }

    // Validate port
    if (!isValidPort($input['port'])) {
        errorResponse('Invalid port format');
    }

    $protocol = $input['protocol'] ?? 'tcp';
    if (!in_array($protocol, ['tcp', 'udp', 'both'])) {
        errorResponse('Invalid protocol. Must be: tcp, udp, or both');
    }

    $source = $input['source'] ?? 'any';
    $description = $input['description'] ?? '';
    $name = $input['name'] ?? ucfirst($input['action']) . ' ' . $input['port'];

    // Add rule to UFW
    $rule = [
        'name' => $name,
        'action' => $input['action'],
        'protocol' => $protocol,
        'port' => $input['port'],
        'source' => $source,
        'description' => $description
    ];

    $result = $system->manageFirewall($input['action'], $rule);

    if (!$result['success']) {
        errorResponse($result['message'] ?? 'Failed to add firewall rule', 500);
    }

    // Save to database
    try {
        $ruleId = 'RULE_' . strtoupper($input['action']) . '_' . strtoupper(str_replace(['.', '/'], '_', $input['port'])) . '_' . time();

        $stmt = $conn->prepare("
            INSERT INTO firewall_rules (rule_id, action, protocol, port, source, description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ruleId,
            $input['action'],
            $protocol,
            $input['port'],
            $source,
            $description,
            CURRENT_USER_ID
        ]);

        $logger->logActivity('firewall_add', 'firewall_rule', $conn->lastInsertId(), [
            'rule_id' => $ruleId,
            'action' => $input['action'],
            'port' => $input['port'],
            'protocol' => $protocol
        ]);

        successResponse([
            'rule_id' => $ruleId,
            'action' => $input['action'],
            'port' => $input['port'],
            'protocol' => $protocol,
            'source' => $source
        ], 'Firewall rule added successfully');

    } catch (PDOException $e) {
        $logger->logError("Failed to save firewall rule to database: " . $e->getMessage());
        // Rule was added to UFW but failed to save to DB
        errorResponse('Rule added but failed to save to database', 500);
    }
}

/**
 * Handle delete firewall rule
 */
function handleDelete() {
    global $conn, $system, $logger;

    requireAdmin();

    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
        errorResponse('Method not allowed', 405);
    }

    $ruleId = $_GET['rule_id'] ?? '';

    if (empty($ruleId)) {
        errorResponse('Rule ID is required');
    }

    // Get rule from database
    try {
        $stmt = $conn->prepare("SELECT * FROM firewall_rules WHERE rule_id = ?");
        $stmt->execute([$ruleId]);
        $rule = $stmt->fetch();

        if (!$rule) {
            errorResponse('Firewall rule not found', 404);
        }

        // Delete from UFW
        $result = $system->manageFirewall('delete', ['rule_id' => $ruleId]);

        if (!$result['success']) {
            errorResponse($result['message'] ?? 'Failed to delete firewall rule', 500);
        }

        // Delete from database
        $stmt = $conn->prepare("DELETE FROM firewall_rules WHERE rule_id = ?");
        $stmt->execute([$ruleId]);

        $logger->logActivity('firewall_delete', 'firewall_rule', $rule['id'], [
            'rule_id' => $ruleId,
            'action' => $rule['action'],
            'port' => $rule['port']
        ]);

        successResponse([
            'rule_id' => $ruleId
        ], 'Firewall rule deleted successfully');

    } catch (PDOException $e) {
        $logger->logError("Failed to delete firewall rule: " . $e->getMessage());
        errorResponse('Failed to delete firewall rule: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle enable firewall
 */
function handleEnable() {
    global $system, $logger;

    requireAdmin();

    try {
        $result = $system->manageFirewall('enable');

        if ($result['success']) {
            $logger->logActivity('firewall_enable', 'firewall', null);

            successResponse([
                'enabled' => true
            ], 'Firewall enabled successfully');
        } else {
            errorResponse($result['message'] ?? 'Failed to enable firewall', 500);
        }

    } catch (Exception $e) {
        $logger->logError("Failed to enable firewall: " . $e->getMessage());
        errorResponse('Failed to enable firewall: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle disable firewall
 */
function handleDisable() {
    global $system, $logger;

    requireAdmin();

    // Confirm action
    $confirm = $_GET['confirm'] ?? 'false';
    if ($confirm !== 'true') {
        errorResponse('Please confirm by adding ?confirm=true', 400);
    }

    try {
        $result = $system->manageFirewall('disable');

        if ($result['success']) {
            $logger->logActivity('firewall_disable', 'firewall', null);

            successResponse([
                'enabled' => false
            ], 'Firewall disabled successfully');
        } else {
            errorResponse($result['message'] ?? 'Failed to disable firewall', 500);
        }

    } catch (Exception $e) {
        $logger->logError("Failed to disable firewall: " . $e->getMessage());
        errorResponse('Failed to disable firewall: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle toggle firewall enable/disable
 */
function handleToggle() {
    global $system;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $enabled = $input['enabled'] ?? false;

    try {
        if ($enabled) {
            $result = $system->manageFirewall('enable');
        } else {
            $result = $system->manageFirewall('disable');
        }

        if ($result['success']) {
            successResponse([
                'enabled' => $enabled
            ], $enabled ? 'Firewall enabled' : 'Firewall disabled');
        } else {
            errorResponse($result['message'] ?? 'Failed to toggle firewall', 500);
        }

    } catch (Exception $e) {
        errorResponse('Failed to toggle firewall: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle get single rule
 */
function handleGetRule() {
    global $conn;

    requireAdmin();

    $ruleId = $_GET['rule_id'] ?? '';

    if (empty($ruleId)) {
        errorResponse('Rule ID is required');
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM firewall_rules WHERE rule_id = ?");
        $stmt->execute([$ruleId]);
        $rule = $stmt->fetch();

        if (!$rule) {
            errorResponse('Firewall rule not found', 404);
        }

        // Get UFW status for this rule
        $ufwStatus = shell_exec("sudo ufw status | grep '{$rule['port']}' 2>&1");
        $rule['applied'] = !empty($ufwStatus);

        successResponse($rule, 'Firewall rule retrieved');

    } catch (PDOException $e) {
        errorResponse('Failed to retrieve firewall rule: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle update rule
 */
function handleUpdateRule() {
    global $conn, $logger;

    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $ruleId = $_GET['rule_id'] ?? '';
    if (empty($ruleId)) {
        errorResponse('Rule ID is required');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    try {
        // Get existing rule
        $stmt = $conn->prepare("SELECT * FROM firewall_rules WHERE rule_id = ?");
        $stmt->execute([$ruleId]);
        $rule = $stmt->fetch();

        if (!$rule) {
            errorResponse('Firewall rule not found', 404);
        }

        // Update fields
        $updates = [];
        $params = [];

        if (!empty($input['description'])) {
            $updates[] = 'description = ?';
            $params[] = $input['description'];
        }

        if (!empty($input['enabled']) !== null) {
            $updates[] = 'enabled = ?';
            $params[] = $input['enabled'] ? 1 : 0;
        }

        if (empty($updates)) {
            errorResponse('No fields to update');
        }

        $params[] = $ruleId;

        $sql = "UPDATE firewall_rules SET " . implode(', ', $updates) . " WHERE rule_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $logger->logActivity('firewall_update', 'firewall_rule', $rule['id'], [
            'rule_id' => $ruleId,
            'updates' => $updates
        ]);

        successResponse([
            'rule_id' => $ruleId,
            'updated_fields' => $updates
        ], 'Firewall rule updated successfully');

    } catch (PDOException $e) {
        $logger->logError("Failed to update firewall rule: " . $e->getMessage());
        errorResponse('Failed to update firewall rule: ' . $e->getMessage(), 500);
    }
}

/**
 * Helper: Parse UFW status output
 */
function parseUFWStatus($output) {
    $status = [
        'active' => false,
        'logging' => 'off',
        'rules' => []
    ];

    $lines = explode("\n", $output);

    foreach ($lines as $line) {
        $line = trim($line);

        // Check if active
        if (preg_match('/Status:\s*active/i', $line)) {
            $status['active'] = true;
        }

        // Check logging
        if (preg_match('/Logging:\s*(on|off)/i', $line, $matches)) {
            $status['logging'] = strtolower($matches[1]);
        }

        // Parse rules
        if (preg_match('/^(\d+)\s+(.*?)\s+(.*?)\s+(.*?)$/i', $line, $matches)) {
            $status['rules'][] = [
                'number' => $matches[1],
                'action' => strtolower($matches[2]),
                'direction' => strtolower($matches[3]),
                'spec' => $matches[4]
            ];
        }
    }

    return $status;
}

/**
 * Helper: Parse UFW numbered rules
 */
function parseUFWRules($output) {
    $rules = [];
    $lines = explode("\n", $output);

    foreach ($lines as $line) {
        $line = trim($line);

        // Parse numbered rule: [ 1] 80/tcp ALLOW IN anywhere
        if (preg_match('/^\[(\d+)\]\s+(\S+)\s+(\S+)\s+(\w+)\s+(\w+)\s+(.*)$/', $line, $matches)) {
            $port = $matches[2];
            $rules[$port] = [
                'number' => $matches[1],
                'port' => $port,
                'protocol' => $matches[3],
                'action' => strtolower($matches[4]),
                'direction' => strtolower($matches[5]),
                'source' => $matches[6]
            ];
        }
    }

    return $rules;
}

/**
 * Helper: Validate port format
 */
function isValidPort($port) {
    // Single port
    if (is_numeric($port) && $port >= 1 && $port <= 65535) {
        return true;
    }

    // Port range
    if (preg_match('/^\d+:\d+$/', $port)) {
        $parts = explode(':', $port);
        return ($parts[0] >= 1 && $parts[0] <= 65535) && ($parts[1] >= 1 && $parts[1] <= 65535);
    }

    // Common service names
    $services = ['http', 'https', 'ftp', 'ssh', 'smtp', 'dns', 'mysql', 'postgresql', 'mongodb', 'redis'];
    if (in_array(strtolower($port), $services)) {
        return true;
    }

    return false;
}
