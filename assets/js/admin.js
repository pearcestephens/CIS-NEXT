/**
 * CIS Admin Panel JavaScript - Modern ES6 Module
 * File: assets/js/admin.js
 * Purpose: Bootstrap 5 + vanilla JS admin panel core
 * Updated: 2025-09-13
 */

import ui from './modules/ui.js';
import net from './modules/net.js';
import forms from './modules/forms.js';
import tables from './modules/tables.js';

class AdminPanel {
    constructor() {
        this.ui = ui;
        this.net = net;
        this.forms = forms;
        this.tables = tables;
        
        this.initialized = false;
        this.theme = localStorage.getItem('admin-theme') || 'light';
        this.sidebarCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        
        // Performance tracking
        this.performanceStart = performance.now();
    }

    /**
     * Initialize the admin panel
     */
    async init() {
        if (this.initialized) return;

        try {
            this.initTheme();
            this.initSidebar();
            this.initNavigation();
            this.initForms();
            this.initTables();
            this.initPerformanceTracking();
            this.initKeyboardShortcuts();
            this.initPageModules();
            
            // Initialize Bootstrap components
            this.ui.initializeTooltips();
            
            this.initialized = true;
            console.log('🚀 CIS Admin Panel initialized successfully');
            
            // Announce to screen readers
            this.ui.announce('Admin panel loaded and ready');
            
        } catch (error) {
            console.error('❌ Failed to initialize admin panel:', error);
            this.ui.showError('Failed to initialize admin panel');
        }
    }

    /**
     * Initialize theme system
     */
    initTheme() {
        // Apply saved theme
        document.documentElement.setAttribute('data-bs-theme', this.theme);
        
        // Update theme toggle button
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            this.updateThemeToggleIcon(themeToggle);
            
            themeToggle.addEventListener('click', () => {
                this.toggleTheme();
            });
        }
        
        // Respect system preference for initial load
        if (!localStorage.getItem('admin-theme')) {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (prefersDark) {
                this.setTheme('dark');
            }
        }
    }

    /**
     * Toggle between light and dark themes
     */
    toggleTheme() {
        const newTheme = this.theme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    }

    /**
     * Set theme
     */
    setTheme(theme) {
        this.theme = theme;
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('admin-theme', theme);
        
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            this.updateThemeToggleIcon(themeToggle);
        }
        
        this.ui.announce(`Switched to ${theme} theme`);
    }

    /**
     * Update theme toggle icon
     */
    updateThemeToggleIcon(toggle) {
        const lightIcon = toggle.querySelector('[data-theme="light"]');
        const darkIcon = toggle.querySelector('[data-theme="dark"]');
        
        if (this.theme === 'light') {
            lightIcon.style.display = 'inline';
            darkIcon.style.display = 'none';
        } else {
            lightIcon.style.display = 'none';
            darkIcon.style.display = 'inline';
        }
    }

    /**
     * Initialize sidebar functionality
     */
    initSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const collapseToggle = document.querySelector('.sidebar-collapse-toggle');
        
        if (!sidebar) return;

        // Apply saved collapse state
        if (this.sidebarCollapsed) {
            document.body.classList.add('sidebar-collapsed');
        }

        // Mobile sidebar toggle
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-open');
            });
        }

        // Desktop sidebar collapse
        if (collapseToggle) {
            collapseToggle.addEventListener('click', () => {
                this.toggleSidebarCollapse();
            });
        }

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && 
                document.body.classList.contains('sidebar-open') &&
                !sidebar.contains(e.target) && 
                !sidebarToggle?.contains(e.target)) {
                document.body.classList.remove('sidebar-open');
            }
        });

        // Handle responsive behavior
        this.handleResponsiveSidebar();
        window.addEventListener('resize', () => {
            this.handleResponsiveSidebar();
        });
    }

    /**
     * Toggle sidebar collapse state
     */
    toggleSidebarCollapse() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        document.body.classList.toggle('sidebar-collapsed', this.sidebarCollapsed);
        localStorage.setItem('sidebar-collapsed', this.sidebarCollapsed.toString());
        
        // Update collapse toggle icon
        const icon = document.querySelector('.sidebar-collapse-toggle i');
        if (icon) {
            icon.className = this.sidebarCollapsed ? 'fas fa-angle-right' : 'fas fa-angle-left';
        }
    }

    /**
     * Handle responsive sidebar behavior
     */
    handleResponsiveSidebar() {
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            document.body.classList.remove('sidebar-collapsed');
            document.body.classList.remove('sidebar-open');
        } else {
            document.body.classList.toggle('sidebar-collapsed', this.sidebarCollapsed);
        }
    }

    /**
     * Initialize navigation features
     */
    initNavigation() {
        // Update active navigation items
        this.updateActiveNavigation();
        
        // Handle navigation clicks with loading states
        const navLinks = document.querySelectorAll('.nav-link[href]');
        for (const link of navLinks) {
            link.addEventListener('click', (e) => {
                // Add loading state for navigation
                const loadingIndicator = this.ui.showLoading(link, 'Loading...');
                
                // Remove loading after a short delay (page should load by then)
                setTimeout(() => {
                    loadingIndicator?.hide();
                }, 1000);
            });
        }
    }

    /**
     * Update active navigation state
     */
    updateActiveNavigation() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
        
        for (const link of navLinks) {
            const href = link.getAttribute('href');
            if (href && (currentPath === href || currentPath.startsWith(href + '/'))) {
                link.classList.add('active');
                
                // Expand parent submenu if needed
                const parentSubmenu = link.closest('.submenu');
                if (parentSubmenu) {
                    const parentToggle = parentSubmenu.previousElementSibling;
                    if (parentToggle) {
                        parentToggle.setAttribute('aria-expanded', 'true');
                        parentSubmenu.classList.add('show');
                    }
                }
            } else {
                link.classList.remove('active');
            }
        }
    }

    /**
     * Initialize forms with validation and enhancements
     */
    initForms() {
        const forms = document.querySelectorAll('form[data-admin-form]');
        for (const form of forms) {
            this.forms.initialize(form);
            
            // Setup AJAX submission if specified
            if (form.dataset.ajaxSubmit === 'true') {
                this.forms.setupAjaxSubmission(form, {
                    onSuccess: (response, form) => {
                        this.ui.showSuccess('Form submitted successfully');
                        
                        // Handle redirect if specified
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        }
                    },
                    onError: (error, form) => {
                        this.ui.showError(`Submission failed: ${error.message}`);
                    }
                });
            }
        }
    }

    /**
     * Initialize tables with enhanced functionality
     */
    initTables() {
        const tables = document.querySelectorAll('table[data-admin-table]');
        for (const table of tables) {
            const options = {
                sortable: table.dataset.sortable !== 'false',
                filterable: table.dataset.filterable !== 'false',
                stickyHeader: table.dataset.stickyHeader !== 'false',
                bulkSelect: table.dataset.bulkSelect === 'true',
                pagination: table.dataset.pagination === 'true'
            };
            
            this.tables.initialize(table, options);
        }
    }

    /**
     * Initialize performance tracking
     */
    initPerformanceTracking() {
        // Track page load time
        if (window.performance && window.performance.timing) {
            const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
            const loadTimeEl = document.getElementById('pageLoadTime');
            if (loadTimeEl) {
                loadTimeEl.textContent = loadTime;
            }
        }
        
        // Track session duration
        this.updateSessionTime();
        setInterval(() => {
            this.updateSessionTime();
        }, 60000); // Update every minute
    }

    /**
     * Update session time display
     */
    updateSessionTime() {
        const sessionTimeEl = document.getElementById('sessionTime');
        if (!sessionTimeEl) return;

        // Get session start time from element or fallback to page load
        const sessionStart = new Date(sessionTimeEl.dataset.sessionStart || Date.now());
        const now = new Date();
        const elapsed = Math.floor((now - sessionStart) / 1000 / 60); // minutes

        if (elapsed < 60) {
            sessionTimeEl.textContent = `${elapsed}m`;
        } else {
            const hours = Math.floor(elapsed / 60);
            const minutes = elapsed % 60;
            sessionTimeEl.textContent = `${hours}h ${minutes}m`;
        }
    }

    /**
     * Initialize keyboard shortcuts
     */
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Only handle shortcuts when not in an input field
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            // Ctrl/Cmd + Shift + shortcuts
            if ((e.ctrlKey || e.metaKey) && e.shiftKey) {
                switch (e.key) {
                    case 'S':
                        e.preventDefault();
                        this.toggleSidebarCollapse();
                        break;
                    case 'T':
                        e.preventDefault();
                        this.toggleTheme();
                        break;
                    case '/':
                        e.preventDefault();
                        this.focusGlobalSearch();
                        break;
                }
            }

            // Escape key - close modals, clear selections, etc.
            if (e.key === 'Escape') {
                // Close any open modals
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    const bsModal = window.bootstrap.Modal.getInstance(openModal);
                    bsModal?.hide();
                    return;
                }

                // Clear table selections
                const selectedRows = document.querySelectorAll('.row-select:checked');
                if (selectedRows.length > 0) {
                    for (const checkbox of selectedRows) {
                        checkbox.checked = false;
                    }
                    // Trigger change event to update bulk action bar
                    selectedRows[0]?.dispatchEvent(new Event('change'));
                    return;
                }

                // Close sidebar on mobile
                if (window.innerWidth <= 768 && document.body.classList.contains('sidebar-open')) {
                    document.body.classList.remove('sidebar-open');
                }
            }
        });
    }

    /**
     * Focus global search (if available)
     */
    focusGlobalSearch() {
        const searchInput = document.querySelector('.global-search, .table-filter input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    /**
     * Initialize page-specific modules based on data-page attribute
     */
    async initPageModules() {
        const pageAttr = document.body.dataset.page;
        if (!pageAttr) return;

        try {
            // Dynamic module loading based on page
            const modulePath = `./modules/pages/${pageAttr}.js`;
            const pageModule = await import(modulePath);
            
            if (pageModule.default && typeof pageModule.default.init === 'function') {
                pageModule.default.init(this);
            }
        } catch (error) {
            // Page module not found or failed to load - not necessarily an error
            console.debug(`Page module not found for: ${pageAttr}`);
        }
    }

    /**
     * Utility: Show confirmation dialog
     */
    async confirmAction(message, options = {}) {
        return await this.ui.showConfirm('Confirm Action', message, options);
    }

    /**
     * Utility: Format numbers with locale-specific formatting
     */
    formatNumber(num) {
        return new Intl.NumberFormat('en-NZ').format(num);
    }

    /**
     * Utility: Show loading state for async operations
     */
    showLoading(element, text = 'Loading...') {
        return this.ui.showLoading(element, text);
    }

    /**
     * Utility: Copy text to clipboard with feedback
     */
    async copyToClipboard(text) {
        return await this.ui.copyToClipboard(text);
    }

    /**
     * Global error handler
     */
    handleError(error, context = 'Unknown') {
        console.error(`Admin Panel Error [${context}]:`, error);
        
        let message = 'An unexpected error occurred';
        if (error.message) {
            message = error.message;
        }
        
        this.ui.showError(message);
    }

    /**
     * Cleanup and destroy
     */
    destroy() {
        // Remove event listeners and cleanup
        this.initialized = false;
        console.log('Admin Panel destroyed');
    }
}

// Initialize the admin panel
const adminPanel = new AdminPanel();

// Make AdminPanel available globally for backward compatibility
window.AdminPanel = adminPanel;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        adminPanel.init();
    });
} else {
    // DOM is already ready
    adminPanel.init();
}

// Global error handling
window.addEventListener('error', (event) => {
    adminPanel.handleError(event.error, 'Global Error');
});

window.addEventListener('unhandledrejection', (event) => {
    adminPanel.handleError(event.reason, 'Unhandled Promise Rejection');
});

// Export for module usage
export default adminPanel;
