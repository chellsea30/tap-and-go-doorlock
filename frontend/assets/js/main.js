// Password visibility toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('togglePassword');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    }
    
    // Form submission loading state
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const spinner = btn.querySelector('.loading-spinner');
            const textSpan = btn.querySelector('span:not(.loading-spinner)');
            
            if (this.checkValidity()) {
                btn.disabled = true;
                textSpan.textContent = ' Signing in...';
                spinner.style.display = 'inline-block';
            }
        });
    }
    
    // Remove invalid class on focus
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('focus', function() {
            this.classList.remove('is-invalid');
        });
    });
});

// Utility function for AJAX requests
function makeRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(JSON.parse(xhr.responseText));
            } else {
                reject(new Error(xhr.statusText));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        xhr.send(data ? JSON.stringify(data) : null);
    });
}

// Format date time
function formatDateTime(timestamp) {
    const date = new Date(timestamp);
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

// Escape HTML to prevent XSS
function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
// ============================================================
// GLOBAL DARK MODE - Applied to ALL Pages
// ============================================================

(function() {
    'use strict';
    
    // Function to apply dark mode
    function applyDarkMode(enabled) {
        if (enabled) {
            document.body.classList.add('dark-mode');
            localStorage.setItem('darkMode', 'true');
        } else {
            document.body.classList.remove('dark-mode');
            localStorage.setItem('darkMode', 'false');
        }
    }
    
    // Function to load dark mode setting from database
    function loadDarkModeFromDatabase() {
        // Check if we're on a page that has the setting
        const darkModeSetting = document.querySelector('meta[name="dark-mode"]');
        if (darkModeSetting) {
            const enabled = darkModeSetting.getAttribute('content') === 'true';
            applyDarkMode(enabled);
            return;
        }
        
        // If no meta tag, check localStorage
        const saved = localStorage.getItem('darkMode');
        if (saved !== null) {
            applyDarkMode(saved === 'true');
            return;
        }
        
        // Default: off
        applyDarkMode(false);
    }
    
    // Initialize dark mode when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // First check localStorage
        const saved = localStorage.getItem('darkMode');
        if (saved !== null) {
            applyDarkMode(saved === 'true');
        } else {
            // Check if there's a meta tag
            const darkModeSetting = document.querySelector('meta[name="dark-mode"]');
            if (darkModeSetting) {
                const enabled = darkModeSetting.getAttribute('content') === 'true';
                applyDarkMode(enabled);
            } else {
                // Default off
                applyDarkMode(false);
            }
        }
    });
    
    // Expose function for other pages to use
    window.toggleDarkMode = function(enabled) {
        applyDarkMode(enabled);
        
        // If we're on settings page, also update the toggle
        const toggle = document.getElementById('darkModeToggle');
        if (toggle) {
            toggle.checked = enabled;
        }
        
        // Save to database via AJAX if on settings page
        if (window.saveDarkModeSetting) {
            window.saveDarkModeSetting(enabled ? 'true' : 'false');
        }
    };
    
})();