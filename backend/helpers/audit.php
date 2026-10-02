<?php
/**
 * Audit logging helper.
 * Location: backend/helpers/audit.php
 */

function auditLog(
    mysqli $conn,
    string $action,
    string $details,
    ?string $ipOverride = null
): void {
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $ip      = $ipOverride ?? ($_SERVER['REMOTE_ADDR'] ?? null);
    $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $stmt = $conn->prepare("
        INSERT INTO audit_logs (admin_id, action, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmt) return;

    $stmt->bind_param("issss", $adminId, $action, $details, $ip, $ua);
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
        SELECT al.*, COALESCE(a.full_name, CONCAT('Admin #', al.admin_id)) AS admin_name
        FROM audit_logs al
        LEFT JOIN admin_users a ON a.admin_id = al.admin_id
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

function countAuditLogs(mysqli $conn, array $filters = []): int {
    $where = [];
    $params = [];
    $types = "";

    if (!empty($filters['action'])) {
        $where[] = "action = ?";
        $params[] = $filters['action'];
        $types .= "s";
    }
    if (!empty($filters['date_from'])) {
        $where[] = "created_at >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
        $types .= "s";
    }
    if (!empty($filters['date_to'])) {
        $where[] = "created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
        $types .= "s";
    }

    $sql = "SELECT COUNT(*) AS c FROM audit_logs";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);

    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if (!empty($types)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c;
}
