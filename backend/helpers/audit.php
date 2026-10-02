<?php
/**
 * Audit logging helper — adapted to your actual schema.
 * Location: backend/helpers/audit.php
 *
 * Your audit_logs table columns:
 *   log_id, admin_id, action, details, ip_address, user_agent, created_at
 * PLUS the ones we added:
 *   target_type, target_id, old_values, new_values
 */

function auditLog(
    mysqli $conn,
    string $action,
    string $targetType,
    int $targetId,
    ?array $old = null,
    ?array $new = null,
    string $details = ''
): void {
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $ip      = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $oldJson = $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
    $newJson = $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;

    // Try the full INSERT (if new columns exist)
    $stmt = $conn->prepare("
        INSERT INTO audit_logs
          (admin_id, action, target_type, target_id, old_values, new_values, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if ($stmt) {
        $stmt->bind_param(
            "ississsss",
            $adminId, $action, $targetType, $targetId,
            $oldJson, $newJson, $details, $ip, $ua
        );
        $stmt->execute();
        $stmt->close();
        return;
    }

    // Fallback: old schema (no target columns yet)
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (admin_id, action, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmt) return;
    $fallbackDetails = trim($details . ' | ' . $targetType . '#' . $targetId);
    $stmt->bind_param("issss", $adminId, $action, $fallbackDetails, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}

function getAuditLogs(mysqli $conn, array $filters = [], int $limit = 25, int $offset = 0): array {
    $where = [];
    $params = [];
    $types = "";

    if (!empty($filters['action'])) {
        $where[] = "al.action = ?";
        $params[] = $filters['action'];
        $types .= "s";
    }
    if (!empty($filters['admin_id'])) {
        $where[] = "al.admin_id = ?";
        $params[] = (int)$filters['admin_id'];
        $types .= "i";
    }
    if (!empty($filters['date_from'])) {
        $where[] = "al.created_at >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
        $types .= "s";
    }
    if (!empty($filters['date_to'])) {
        $where[] = "al.created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
        $types .= "s";
    }

    $sql = "
        SELECT al.*, COALESCE(au.full_name, CONCAT('Admin #', al.admin_id)) AS admin_name
        FROM audit_logs al
        LEFT JOIN admin_users au ON au.admin_id = al.admin_id
    ";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}
