/**
 * Tap-and-Go Doorlock - Dark Mode Controller
 * COMPLETE WORKING VERSION
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================================
    // APPLY DARK MODE
    // ============================================================
    function applyDarkMode() {
        // Check localStorage first
        let isDark = localStorage.getItem('darkMode') === 'true';
        
        // If not in localStorage, check from PHP (set in header)
        if (!localStorage.getItem('darkMode')) {
            if (typeof window.__dbDarkMode !== 'undefined' && window.__dbDarkMode) {
                isDark = true;
            }
        }
        
        if (isDark) {
            document.body.classList.add('dark-mode');
            document.documentElement.classList.add('dark-mode');
            localStorage.setItem('darkMode', 'true');
        } else {
            document.body.classList.remove('dark-mode');
            document.documentElement.classList.remove('dark-mode');
            localStorage.setItem('darkMode', 'false');
        }
        
        // Update toggle if it exists
        updateToggleState();
        
        // Update window value
        window.__darkModeEnabled = isDark;
    }
    
    // ============================================================
    // UPDATE TOGGLE STATE
    // ============================================================
    function updateToggleState() {
        const toggle = document.getElementById('darkModeToggle');
        if (toggle) {
            toggle.checked = document.body.classList.contains('dark-mode');
        }
    }
    
    // ============================================================
    // SAVE TO DATABASE
    // ============================================================
    function saveDarkModeToDatabase(value) {
        const currentUrl = window.location.href.split('?')[0];
        fetch(currentUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'update_settings=1&settings[dark_mode]=' + value
        })
        .then(response => response.text())
        .then(data => {
            console.log('Dark mode saved to database:', value);
        })
        .catch(error => {
            console.error('Error saving dark mode:', error);
        });
    }
    
    // ============================================================
    // TOGGLE DARK MODE - Exposed globally
    // ============================================================
    window.toggleDarkMode = function() {
        const isDark = document.body.classList.toggle('dark-mode');
        document.documentElement.classList.toggle('dark-mode', isDark);
        localStorage.setItem('darkMode', isDark ? 'true' : 'false');
        saveDarkModeToDatabase(isDark ? 'true' : 'false');
        updateToggleState();
        window.__darkModeEnabled = isDark;
        return isDark;
    };
    
    // ============================================================
    // SETUP TOGGLE EVENT
    // ============================================================
    function setupToggle() {
        const toggle = document.getElementById('darkModeToggle');
        if (toggle) {
            toggle.addEventListener('change', function() {
                const isDark = this.checked;
                document.body.classList.toggle('dark-mode', isDark);
                document.documentElement.classList.toggle('dark-mode', isDark);
                localStorage.setItem('darkMode', isDark ? 'true' : 'false');
                saveDarkModeToDatabase(isDark ? 'true' : 'false');
                window.__darkModeEnabled = isDark;
            });
        }
    }
    
    // ============================================================
    // EXPOSE TO CONSOLE FOR DEBUGGING
    // ============================================================
    window.getDarkModeStatus = function() {
        return {
            bodyClass: document.body.classList.contains('dark-mode'),
            htmlClass: document.documentElement.classList.contains('dark-mode'),
            localStorage: localStorage.getItem('darkMode'),
            dbDarkMode: typeof window.__dbDarkMode !== 'undefined' ? window.__dbDarkMode : 'not set',
            enabled: window.__darkModeEnabled || false
        };
    };
    
    // ============================================================
    // INITIALIZE
    // ============================================================
    applyDarkMode();
    setupToggle();
    
    console.log('✅ Dark Mode initialized. Status:', window.getDarkModeStatus());
});