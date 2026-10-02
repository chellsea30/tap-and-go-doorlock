<?php
/**
 * Audit Logs viewer.
 * Location: frontend/pages/audit-logs.php
 */
session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';
require_once '../../backend/helpers/audit.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

include '../includes/header.php';

$conn = getDBConnection();

$filters = [
    'action'    => $_GET['action'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to'   => $_GET['date_to'] ?? '',
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$logs  = getAuditLogs($conn, $filters, $perPage, $offset);
$total = countAuditLogs($conn, $filters);
$totalPages = max(1, (int)ceil($total / $perPage));

// Distinct actions for filter
$actions = [];
$r = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
if ($r) while ($row = $r->fetch_assoc()) $actions[] = $row['action'];

function fmtAction($a) {
    $lower = strtolower($a);
    if (strpos($lower, 'approve') !== false) return ['badge-success', $a];
    if (strpos($lower, 'reject') !== false)  return ['badge-danger',  $a];
    if (strpos($lower, 'delete') !== false)  return ['badge-danger',  $a];
    if (strpos($lower, 'photo') !== false)   return ['badge-info',    $a];
    return ['badge-info', $a];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        html, body { font-family: 'Inter', sans-serif; background: #0a0e1a !important; color: #e0e0e0 !important; margin: 0; }
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important; top: 0; left: 0; right: 0;
            z-index: 1050 !important; height: 56px !important;
        }
        .sidebar {
            position: fixed !important; top: 56px; left: 0; bottom: 0;
            width: 220px !important; background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            overflow-y: auto !important; z-index: 1040 !important;
        }
        .main-content { margin-left: 220px !important; margin-top: 56px !important; padding: 15px 25px !important; min-height: calc(100vh - 56px) !important; }
        .footer { margin-left: 220px !important; padding: 10px 25px !important; background: #0d1528 !important; border-top: 1px solid #1a2a4a !important; color: #606070 !important; font-size: 12px !important; text-align: center !important; }
        .page-header { padding-bottom: 10px; margin-bottom: 15px; border-bottom: 1px solid #1a2a4a; }
        .page-header h1 { font-size: 20px; font-weight: 600; color: #e0e0e0; }
        .page-header h1 i { color: #1a3a6a; }

        .card { background: #111827 !important; border: 1px solid #1a2a4a !important; border-radius: 12px !important; padding: 16px; }
        .table { color: #e0e0e0 !important; margin: 0; }
        .table thead th { background: #0d1528 !important; border-color: #1a2a4a !important; color: #9090a0 !important; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .table td { border-color: #1a2a4a !important; font-size: 12px; vertical-align: middle; }
        .table tbody tr:hover { background: rgba(255,255,255,0.03); }

        .badge-status { padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; display: inline-block; }
        .badge-success { background: #065f46 !important; color: #6ee7b7 !important; }
        .badge-danger  { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-info    { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }

        .form-control, .form-select { background: #1a1a2e !important; border: 1px solid #2a2a4a !important; color: #e0e0e0 !important; font-size: 13px; height: 36px; border-radius: 8px; }
        .form-control:focus, .form-select:focus { border-color: #2a5a9a !important; box-shadow: 0 0 0 3px rgba(26,58,106,0.3); }
        .form-label { color: #9090a0; font-size: 12px; margin-bottom: 4px; }

        .btn-primary { background: #1a3a6a !important; border-color: #1a3a6a !important; color: white !important; font-size: 12px !important; padding: 6px 14px !important; border-radius: 8px !important; }
        .btn-primary:hover { background: #2a5a9a !important; }
        .btn-outline-secondary { border-color: #2a2a4a !important; color: #808090 !important; font-size: 12px !important; padding: 6px 12px !important; border-radius: 8px !important; background: transparent !important; }
        .btn-outline-secondary:hover { background: #2a2a4a !important; color: #e0e0e0 !important; }

        .pagination .page-link { color: #9090a0 !important; background: transparent !important; border: none; font-size: 12px; padding: 5px 10px; border-radius: 6px; margin: 0 2px; }
        .pagination .page-link:hover { background: #2a2a4a !important; color: #e0e0e0 !important; }
        .pagination .page-item.active .page-link { background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important; color: white !important; }
        .pagination .page-item.disabled .page-link { color: #4a4a5a !important; }

        .details-cell { color: #b0b0c0; max-width: 400px; }

        @media (max-width: 992px) {
            .sidebar { left: -260px !important; width: 240px !important; }
            .sidebar.show { left: 0 !important; }
            .main-content { margin-left: 0 !important; padding: 12px 15px !important; }
            .footer { margin-left: 0 !important; padding: 8px 15px !important; }
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1e2a3a; border-radius: 4px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header d-flex justify-content-between flex-wrap align-items-center gap-2">
            <h1><i class="fas fa-history me-2"></i>Audit Logs</h1>
            <a href="residents.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Residents
            </a>
        </div>

        <div class="card mb-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <select name="action" class="form-select">
                        <option value="">All actions</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $filters['action'] === $a ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="audit-logs.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:140px;">When</th>
                            <th style="width:150px;">Admin</th>
                            <th style="width:170px;">Action</th>
                            <th>Details</th>
                            <th style="width:120px;">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4" style="color:#808090;">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    No audit logs found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log):
                                [$badge, $label] = fmtAction($log['action']);
                            ?>
                                <tr>
                                    <td style="color:#9090a0;">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                        <br>
                                        <small style="color:#606070;"><?php echo date('g:i:s A', strtotime($log['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <i class="fas fa-user-shield me-1" style="color:#93c5fd;"></i>
                                        <?php echo htmlspecialchars($log['admin_name'] ?? 'Admin #' . $log['admin_id']); ?>
                                    </td>
                                    <td>
                                        <span class="badge-status <?php echo $badge; ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                        </span>
                                    </td>
                                    <td class="details-cell">
                                        <?php echo htmlspecialchars($log['details'] ?? '—'); ?>
                                    </td>
                                    <td style="color:#808090;font-family:monospace;font-size:11px;">
                                        <?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <div style="color:#808090;font-size:12px;">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $total); ?> of <?php echo $total; ?> entries
                    </div>
                    <ul class="pagination mb-0">
                        <?php $qs = $_GET; $qs['page'] = max(1, $page - 1); ?>
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query($qs); ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++):
                            $qs['page'] = $i;
                        ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query($qs); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php $qs['page'] = min($totalPages, $page + 1); ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query($qs); ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer">
        &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System - ISU-Echague Dormitory. All rights reserved.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
