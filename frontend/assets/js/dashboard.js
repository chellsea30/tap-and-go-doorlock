/**
 * Tap-and-Go Doorlock - Dashboard JavaScript
 * COMPLETE FIXED VERSION
 */

document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadAccessLogs();
    loadOccupancy();
    loadPowerStatus();
    loadAlertCount();
    
    // Refresh data periodically
    setInterval(loadAccessLogs, 10000); // Every 10 seconds
    setInterval(loadOccupancy, 15000);  // Every 15 seconds
    setInterval(loadPowerStatus, 30000); // Every 30 seconds
    setInterval(loadAlertCount, 5000);   // Every 5 seconds
});

function loadAccessLogs() {
    fetch('/backend/api/v1/access.php?action=recent&limit=10')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var tbody = document.getElementById('accessLogsTable');
                if (!tbody) return;
                tbody.innerHTML = '';
                
                data.logs.forEach(function(log) {
                    var row = document.createElement('tr');
                    var statusBadge = log.access_status === 'granted' 
                        ? '<span class="badge bg-success">Granted</span>'
                        : '<span class="badge bg-danger">Denied</span>';
                    
                    var typeBadge = log.access_type === 'entry'
                        ? '<span class="badge bg-info">Entry</span>'
                        : '<span class="badge bg-secondary">Exit</span>';
                    
                    var powerBadge = log.power_source === 'main'
                        ? '<span class="badge bg-success">Main</span>'
                        : '<span class="badge bg-warning">Battery</span>';
                    
                    row.innerHTML = `
                        <td>${formatDateTime(log.timestamp)}</td>
                        <td><code>${escapeHTML(log.card_uid || 'Unknown')}</code></td>
                        <td>${escapeHTML(log.user_name || 'Unknown')}</td>
                        <td>${typeBadge}</td>
                        <td>${statusBadge}</td>
                        <td>${powerBadge}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
        })
        .catch(function(error) { console.error('Error loading access logs:', error); });
}

function loadOccupancy() {
    fetch('/backend/api/v1/access.php?action=occupancy')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var countEl = document.getElementById('occupancyCount');
                var progressEl = document.getElementById('occupancyProgress');
                if (countEl) countEl.textContent = data.count || 0;
                if (progressEl) {
                    var percentage = Math.min((data.count / data.capacity) * 100, 100);
                    progressEl.style.width = percentage + '%';
                }
            }
        })
        .catch(function(error) { console.error('Error loading occupancy:', error); });
}

function loadPowerStatus() {
    fetch('/backend/api/v1/system.php?action=power-status')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var source = document.getElementById('powerSource');
                var battery = document.getElementById('batteryLevel');
                var progress = document.getElementById('batteryProgress');
                
                if (source) {
                    if (data.power_source === 'main') {
                        source.className = 'badge bg-success';
                        source.textContent = 'Main Power';
                    } else {
                        source.className = 'badge bg-warning';
                        source.textContent = 'Battery Power';
                    }
                }
                
                if (battery) battery.textContent = data.battery_level + '%';
                if (progress) {
                    progress.style.width = data.battery_level + '%';
                    if (data.battery_level < 20) {
                        progress.className = 'progress-bar bg-danger';
                    } else if (data.battery_level < 50) {
                        progress.className = 'progress-bar bg-warning';
                    } else {
                        progress.className = 'progress-bar bg-success';
                    }
                }
            }
        })
        .catch(function(error) { console.error('Error loading power status:', error); });
}

function loadAlertCount() {
    fetch('/backend/api/v1/alerts.php?action=count&status=pending')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var badge = document.getElementById('alertCount');
                if (badge) {
                    badge.textContent = data.count || 0;
                    badge.style.display = data.count > 0 ? 'inline-block' : 'none';
                }
            }
        })
        .catch(function(error) { console.error('Error loading alert count:', error); });
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

function formatDateTime(timestamp) {
    if (!timestamp) return 'N/A';
    var date = new Date(timestamp);
    return date.toLocaleString('en-PH', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
}

function escapeHTML(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}