<?php
/**
 * Tap-and-Go Doorlock - Chart Report
 * PIE CHART FOR COURSE AND YEAR LEVEL
 * PURE DARK MODE
 * WITH SHOW ENTRIES
 */

session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// ============================================================
// GET COURSE DATA FOR PIE CHART
// ============================================================
$courseData = [];
$courseQuery = "
    SELECT 
        rp.course,
        COUNT(*) as count
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status != 'deleted'
    AND rp.course IS NOT NULL
    AND rp.course != ''
    GROUP BY rp.course
    ORDER BY count DESC
";

$result = $conn->query($courseQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courseData[] = [
            'label' => $row['course'],
            'count' => (int)$row['count']
        ];
    }
}

// ============================================================
// GET YEAR LEVEL DATA FOR PIE CHART
// ============================================================
$yearData = [];
$yearQuery = "
    SELECT 
        rp.year_level,
        COUNT(*) as count
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status != 'deleted'
    AND rp.year_level IS NOT NULL
    AND rp.year_level > 0
    GROUP BY rp.year_level
    ORDER BY rp.year_level ASC
";

$result = $conn->query($yearQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yearLabels = ['', '1st Year', '2nd Year', '3rd Year', '4th Year'];
        $yearData[] = [
            'label' => $yearLabels[$row['year_level']] ?? $row['year_level'] . 'th Year',
            'year' => (int)$row['year_level'],
            'count' => (int)$row['count']
        ];
    }
}

// ============================================================
// GET GENDER DATA FOR PIE CHART
// ============================================================
$genderData = [];
$genderQuery = "
    SELECT 
        rp.gender,
        COUNT(*) as count
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status != 'deleted'
    AND rp.gender IS NOT NULL
    AND rp.gender != ''
    GROUP BY rp.gender
    ORDER BY count DESC
";

$result = $conn->query($genderQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $genderData[] = [
            'label' => $row['gender'],
            'count' => (int)$row['count']
        ];
    }
}

// ============================================================
// GET TOTAL STATS
// ============================================================
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'archived' => 0,
    'with_card' => 0,
    'no_card' => 0,
    'male' => 0,
    'female' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status != 'deleted'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['total'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['active'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['inactive'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'archived'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['archived'] = (int)$row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM users u 
    INNER JOIN rfid_cards rf ON u.user_id = rf.user_id 
    WHERE rf.status = 'active' AND u.status != 'deleted'
");
if ($result && $row = $result->fetch_assoc()) {
    $stats['with_card'] = (int)$row['count'];
}
$stats['no_card'] = $stats['total'] - $stats['with_card'];

foreach ($genderData as $g) {
    if (strtolower($g['label']) == 'male') {
        $stats['male'] = $g['count'];
    } elseif (strtolower($g['label']) == 'female') {
        $stats['female'] = $g['count'];
    }
}

// Get dark mode
$darkModeClass = '';
$darkModeFromDb = 'false';
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE admin_id = ? AND setting_key = 'dark_mode'");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $darkModeFromDb = $row['setting_value'];
            if ($darkModeFromDb == 'true') {
                $darkModeClass = 'dark-mode';
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Silently fail
    }
}

// Generate colors for charts
function generateColors($count) {
    $colors = [
        'rgba(255, 215, 0, 0.8)',   // Gold
        'rgba(52, 211, 153, 0.8)',  // Emerald
        'rgba(96, 165, 250, 0.8)',  // Blue
        'rgba(251, 191, 36, 0.8)',  // Amber
        'rgba(248, 113, 113, 0.8)', // Red
        'rgba(196, 181, 253, 0.8)', // Violet
        'rgba(52, 211, 153, 0.8)',  // Emerald
        'rgba(251, 146, 60, 0.8)',  // Orange
        'rgba(167, 139, 250, 0.8)', // Purple
        'rgba(56, 189, 248, 0.8)',  // Sky
    ];
    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $result[] = $colors[$i % count($colors)];
    }
    return $result;
}

$courseColors = generateColors(count($courseData));
$yearColors = generateColors(count($yearData));
$genderColors = ['rgba(52, 211, 153, 0.8)', 'rgba(248, 113, 113, 0.8)'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart Report - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ============================================================
           GLOBAL DARK THEME
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
            padding-top: 70px !important;
        }
        
        .container-fluid {
            padding-top: 10px !important;
        }
        
        main {
            padding-top: 10px !important;
            margin-top: 0 !important;
        }
        
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1050 !important;
            height: 70px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            padding-top: 80px !important;
            min-height: calc(100vh - 70px) !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar-footer { border-top-color: #1a2a4a !important; }
        .sidebar-footer .text-muted { color: #606070 !important; }
        
        /* ============================================================
           DARK STAT CARDS
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 18px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white; flex-shrink: 0;
        }
        .stat-number { font-size: 24px; font-weight: 700; color: #e0e0e0; margin: 0; }
        .stat-label { font-size: 12px; color: #808090; margin: 0; }
        .stat-number.text-danger { color: #f87171 !important; }
        .stat-number.text-warning { color: #fbbf24 !important; }
        .stat-number.text-success { color: #34d399 !important; }
        
        /* ============================================================
           DARK CHART CARDS
           ============================================================ */
        .chart-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            height: 100%;
        }
        .chart-card .chart-title {
            color: #e0e0e0 !important;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 15px;
            border-bottom: 1px solid #1a2a4a;
            padding-bottom: 10px;
        }
        .chart-card .chart-title i {
            color: #ffd700;
            margin-right: 8px;
        }
        .chart-card .chart-wrapper {
            position: relative;
            height: 300px;
        }
        .chart-card .chart-wrapper canvas {
            max-height: 300px;
            max-width: 100%;
        }
        .chart-card .chart-legend {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }
        .chart-card .chart-legend .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #b0b0c0;
        }
        .chart-card .chart-legend .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        .chart-card .chart-total {
            text-align: center;
            margin-top: 10px;
            color: #808090;
            font-size: 13px;
        }
        .chart-card .chart-total strong {
            color: #ffd700;
        }
        
        /* ============================================================
           DARK CARDS
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-bottom: 20px;
        }
        .card-header {
            background: #111827 !important;
            border-bottom: 1px solid #1a2a4a !important;
            border-radius: 16px 16px 0 0 !important;
            padding: 14px 20px;
        }
        .card-header h5 { margin: 0; font-weight: 600; color: #e0e0e0; font-size: 16px; }
        .card-body { padding: 20px; background: #111827 !important; }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-danger { color: #f87171 !important; }
        .text-primary { color: #93c5fd !important; }
        
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
            margin-right: 4px;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            body {
                padding-top: 60px !important;
            }
            
            .navbar {
                height: 60px !important;
            }
            
            .sidebar {
                padding-top: 70px !important;
                position: fixed;
                top: 60px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
                min-height: calc(100vh - 60px) !important;
            }
            .sidebar.show { left: 0; }
            
            .stat-card { padding: 15px; }
            .stat-number { font-size: 20px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            .chart-card .chart-wrapper {
                height: 250px;
            }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- ============================================================
                HEADER
                ============================================================ -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-pie me-2" style="color: #1a3a6a;"></i>
                        Chart Report
                        <span class="badge bg-primary ms-2"><?php echo $stats['total']; ?> residents</span>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator me-1"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- ============================================================
                STATS CARDS
                ============================================================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">Total Residents</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number text-success"><?php echo $stats['active']; ?></div>
                                <div class="stat-label">Active</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="stat-number text-warning"><?php echo $stats['inactive']; ?></div>
                                <div class="stat-label">Inactive</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #6b7280;"><i class="fas fa-archive"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['archived']; ?></div>
                                <div class="stat-label">Archived</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #3b82f6;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $stats['with_card']; ?></div>
                                <div class="stat-label">With Card</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-2">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: <?php echo ($stats['male'] > 0 && $stats['female'] > 0) ? '#8b5cf6' : '#6b7280'; ?>;">
                                <i class="fas fa-venus-mars"></i>
                            </div>
                            <div>
                                <div class="stat-number"><?php echo $stats['male'] + $stats['female']; ?></div>
                                <div class="stat-label">Male/Female</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                CHART CARDS - PIE CHARTS
                ============================================================ -->
                <div class="row g-4 mb-4">
                    <!-- Course Pie Chart -->
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-graduation-cap"></i>
                                Course Distribution
                                <span class="badge bg-info ms-2"><?php echo count($courseData); ?> courses</span>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="courseChart"></canvas>
                            </div>
                            <div class="chart-total">
                                Total: <strong><?php echo array_sum(array_column($courseData, 'count')); ?></strong> students
                            </div>
                        </div>
                    </div>
                    
                    <!-- Year Level Pie Chart -->
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-layer-group"></i>
                                Year Level Distribution
                                <span class="badge bg-info ms-2"><?php echo count($yearData); ?> levels</span>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="yearChart"></canvas>
                            </div>
                            <div class="chart-total">
                                Total: <strong><?php echo array_sum(array_column($yearData, 'count')); ?></strong> students
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gender Pie Chart -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-venus-mars"></i>
                                Gender Distribution
                                <span class="badge bg-info ms-2"><?php echo count($genderData); ?> genders</span>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="genderChart"></canvas>
                            </div>
                            <div class="chart-total">
                                Total: <strong><?php echo array_sum(array_column($genderData, 'count')); ?></strong> students
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary Card -->
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-simple"></i>
                                Summary
                                <span class="badge bg-success ms-2"><?php echo $stats['total']; ?> total</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="p-3" style="background: #1a1a2e; border-radius: 10px; border: 1px solid #1a2a4a;">
                                        <div style="font-size: 24px; font-weight: 700; color: #34d399;"><?php echo $stats['active']; ?></div>
                                        <div style="font-size: 11px; color: #808090;">Active Residents</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3" style="background: #1a1a2e; border-radius: 10px; border: 1px solid #1a2a4a;">
                                        <div style="font-size: 24px; font-weight: 700; color: #fbbf24;"><?php echo $stats['inactive']; ?></div>
                                        <div style="font-size: 11px; color: #808090;">Inactive Residents</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3" style="background: #1a1a2e; border-radius: 10px; border: 1px solid #1a2a4a;">
                                        <div style="font-size: 24px; font-weight: 700; color: #93c5fd;"><?php echo $stats['with_card']; ?></div>
                                        <div style="font-size: 11px; color: #808090;">With RFID Card</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3" style="background: #1a1a2e; border-radius: 10px; border: 1px solid #1a2a4a;">
                                        <div style="font-size: 24px; font-weight: 700; color: #f87171;"><?php echo $stats['no_card']; ?></div>
                                        <div style="font-size: 11px; color: #808090;">No RFID Card</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3" style="background: #1a1a2e; border-radius: 10px; border: 1px solid #1a2a4a;">
                                        <div style="font-size: 24px; font-weight: 700; color: #a78bfa;"><?php echo $stats['male']; ?></div>
                                        <div style="font-size: 11px; color: #808090;">Male</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3" style="background: #1a1a2e; border-radius: 10px; border: 1px solid #1a2a4a;">
                                        <div style="font-size: 24px; font-weight: 700; color: #f472b6;"><?php echo $stats['female']; ?></div>
                                        <div style="font-size: 11px; color: #808090;">Female</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                TABLE VIEW - COURSE DATA
                ============================================================ -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Course Data</h5>
                            <span class="text-muted small">Total: <?php echo array_sum(array_column($courseData, 'count')); ?> students</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Course</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($courseData)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                No course data available
                                            </td>
                                        </tr>
                                    <?php else: 
                                        $totalCourse = array_sum(array_column($courseData, 'count'));
                                        $counter = 1;
                                        foreach ($courseData as $course): 
                                            $percentage = $totalCourse > 0 ? round(($course['count'] / $totalCourse) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($course['label']); ?></strong></td>
                                            <td><?php echo $course['count']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress" style="width: 120px; height: 6px; background: #1a2a4a;">
                                                        <div class="progress-bar" style="width: <?php echo $percentage; ?>%; background: linear-gradient(135deg, #ffd700, #f59e0b);"></div>
                                                    </div>
                                                    <span style="font-size: 12px; color: #808090;"><?php echo $percentage; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                TABLE VIEW - YEAR DATA
                ============================================================ -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Year Level Data</h5>
                            <span class="text-muted small">Total: <?php echo array_sum(array_column($yearData, 'count')); ?> students</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Year Level</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($yearData)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                No year level data available
                                            </td>
                                        </tr>
                                    <?php else: 
                                        $totalYear = array_sum(array_column($yearData, 'count'));
                                        $counter = 1;
                                        foreach ($yearData as $year): 
                                            $percentage = $totalYear > 0 ? round(($year['count'] / $totalYear) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($year['label']); ?></strong></td>
                                            <td><?php echo $year['count']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress" style="width: 120px; height: 6px; background: #1a2a4a;">
                                                        <div class="progress-bar" style="width: <?php echo $percentage; ?>%; background: linear-gradient(135deg, #10b981, #34d399);"></div>
                                                    </div>
                                                    <span style="font-size: 12px; color: #808090;"><?php echo $percentage; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                FOOTER
                ============================================================ -->
                <div class="text-center text-muted small mt-2">
                    <i class="fas fa-chart-pie me-1"></i>
                    Chart Report - Course & Year Level Distribution
                    <span class="mx-1">|</span>
                    <i class="fas fa-users me-1"></i>
                    Total: <?php echo $stats['total']; ?> residents
                    <span class="mx-1">|</span>
                    <i class="fas fa-graduation-cap me-1"></i>
                    <?php echo count($courseData); ?> courses
                    <span class="mx-1">|</span>
                    <i class="fas fa-layer-group me-1"></i>
                    <?php echo count($yearData); ?> year levels
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // UPDATE TIME
        // ============================================================
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            const updateElement = document.getElementById('lastUpdate');
            if (updateElement) {
                updateElement.textContent = 'Updated: ' + timeString;
            }
        }

        setInterval(updateLastUpdateTime, 10000);
        document.addEventListener('DOMContentLoaded', updateLastUpdateTime);
        
        // ============================================================
        // SIDEBAR TOGGLE
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }

        // ============================================================
        // CHART.JS - DARK THEME CONFIG
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            // Default dark theme config
            Chart.defaults.color = '#b0b0c0';
            Chart.defaults.borderColor = '#1a2a4a';
            
            // ============================================================
            // COURSE PIE CHART
            // ============================================================
            const courseCtx = document.getElementById('courseChart').getContext('2d');
            const courseLabels = <?php echo json_encode(array_column($courseData, 'label')); ?>;
            const courseCounts = <?php echo json_encode(array_column($courseData, 'count')); ?>;
            const courseColors = <?php echo json_encode($courseColors); ?>;
            
            new Chart(courseCtx, {
                type: 'pie',
                data: {
                    labels: courseLabels,
                    datasets: [{
                        data: courseCounts,
                        backgroundColor: courseColors,
                        borderColor: '#0a0e1a',
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#b0b0c0',
                                padding: 10,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // ============================================================
            // YEAR LEVEL PIE CHART
            // ============================================================
            const yearCtx = document.getElementById('yearChart').getContext('2d');
            const yearLabels = <?php echo json_encode(array_column($yearData, 'label')); ?>;
            const yearCounts = <?php echo json_encode(array_column($yearData, 'count')); ?>;
            const yearColors = <?php echo json_encode($yearColors); ?>;
            
            new Chart(yearCtx, {
                type: 'pie',
                data: {
                    labels: yearLabels,
                    datasets: [{
                        data: yearCounts,
                        backgroundColor: yearColors,
                        borderColor: '#0a0e1a',
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#b0b0c0',
                                padding: 10,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // ============================================================
            // GENDER PIE CHART
            // ============================================================
            const genderCtx = document.getElementById('genderChart').getContext('2d');
            const genderLabels = <?php echo json_encode(array_column($genderData, 'label')); ?>;
            const genderCounts = <?php echo json_encode(array_column($genderData, 'count')); ?>;
            const genderColors = <?php echo json_encode($genderColors); ?>;
            
            new Chart(genderCtx, {
                type: 'pie',
                data: {
                    labels: genderLabels,
                    datasets: [{
                        data: genderCounts,
                        backgroundColor: genderColors,
                        borderColor: '#0a0e1a',
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#b0b0c0',
                                padding: 10,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
