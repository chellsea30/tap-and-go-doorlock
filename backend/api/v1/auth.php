<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/config.php';
require_once '../../helpers/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => ''];

if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'login':
            if (!isset($data['username']) || !isset($data['password'])) {
                $response['message'] = 'Username and password required';
                echo json_encode($response);
                exit();
            }
            
            $result = authenticateUser($data['username'], $data['password']);
            
            if ($result['success']) {
                $response['success'] = true;
                $response['message'] = 'Login successful';
                $response['user'] = [
                    'id' => $result['admin_id'],
                    'username' => $result['username'],
                    'full_name' => $result['full_name'],
                    'role' => $result['role']
                ];
                $response['token'] = generateJWT($result);
            } else {
                $response['message'] = $result['message'];
            }
            break;
            
        case 'logout':
            session_start();
            session_unset();
            session_destroy();
            $response['success'] = true;
            $response['message'] = 'Logout successful';
            break;
            
        case 'validate':
            session_start();
            if (isAuthenticated()) {
                $response['success'] = true;
                $response['user'] = getCurrentUser();
            } else {
                $response['message'] = 'Session expired';
            }
            break;
            
        default:
            $response['message'] = 'Invalid action';
            break;
    }
} else {
    $response['message'] = 'Method not allowed';
}

echo json_encode($response);
exit();

/**
 * Generate JWT token
 * 
 * @param array<string, mixed> $user User data
 * @return string JWT token
 */
function generateJWT(array $user): string {
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'user_id' => $user['admin_id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'exp' => time() + 3600
    ]));
    $signature = hash_hmac('sha256', $header . '.' . $payload, 'your-secret-key');
    
    return $header . '.' . $payload . '.' . $signature;
}

/**
 * Check if user is authenticated
 * 
 * @return bool True if authenticated
 */
function isAuthenticated(): bool {
    if (!isset($_SESSION[SESSION_ADMIN_ID]) || !isSessionValid()) {
        return false;
    }
    return true;
}

/**
 * Get current user
 * 
 * @return array<string, mixed>|null
 */
function getCurrentUser(): ?array {
    if (!isAuthenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION[SESSION_ADMIN_ID],
        'username' => $_SESSION[SESSION_USERNAME],
        'full_name' => $_SESSION[SESSION_FULL_NAME],
        'role' => $_SESSION[SESSION_ROLE]
    ];
}
?>