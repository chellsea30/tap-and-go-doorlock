<?php
// User Roles
define('ROLE_ADMIN', 'administrator');
define('ROLE_STAFF', 'staff');
define('ROLE_MANAGER', 'manager');

// Card Status
define('CARD_ACTIVE', 'active');
define('CARD_DEACTIVATED', 'deactivated');
define('CARD_EXPIRED', 'expired');
define('CARD_LOST', 'lost');

// Card Types
define('CARD_RESIDENT', 'resident');
define('CARD_VISITOR', 'visitor');
define('CARD_STAFF', 'staff');

// Access Status
define('ACCESS_GRANTED', 'granted');
define('ACCESS_DENIED', 'denied');

// Alert Types
define('ALERT_BUZZER', 'buzzer');
define('ALERT_SMS', 'sms');
define('ALERT_DASHBOARD', 'dashboard');

// Alert Delivery Status
define('DELIVERY_PENDING', 'pending');
define('DELIVERY_SENT', 'sent');
define('DELIVERY_FAILED', 'failed');
define('DELIVERY_DELIVERED', 'delivered');

// User Status
define('USER_ACTIVE', 'active');
define('USER_INACTIVE', 'inactive');
define('USER_SUSPENDED', 'suspended');

// Power Source
define('POWER_MAIN', 'main');
define('POWER_BATTERY', 'battery');
define('POWER_UNKNOWN', 'unknown');

// Session Keys
define('SESSION_ADMIN_ID', 'admin_id');
define('SESSION_USERNAME', 'username');
define('SESSION_FULL_NAME', 'full_name');
define('SESSION_ROLE', 'role');
define('SESSION_LOGIN_TIME', 'login_time');

// API Response Codes
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_INTERNAL_ERROR', 500);
?>