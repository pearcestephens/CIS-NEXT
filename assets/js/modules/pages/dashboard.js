/**
 * Dashboard Page Module
 * File: assets/js/modules/pages/dashboard.js
 * Purpose: Dashboard-specific functionality
 */

class DashboardModule {
    constructor() {
        this.refreshInterval = null;
        this.charts = new Map();
    }

    /**
     * Initialize dashboard functionality
     */
    init(adminPanel) {
        this.adminPanel = adminPanel;
        
        this.initMetricsRefresh();
        this.initQuickActions();
        this.initSystemStatus();
        
        console.log('📊 Dashboard module initialized');
    }

    /**
     * Initialize auto-refresh for metrics
     */
    initMetricsRefresh() {
        const refreshToggle = document.getElementById('metricsAutoRefresh');
        const refreshButton = document.getElementById('refreshMetrics');
        
        if (refreshToggle) {
            refreshToggle.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.startAutoRefresh();
                } else {
                    this.stopAutoRefresh();
                }
            });
        }

        if (refreshButton) {
            refreshButton.addEventListener('click', () => {
                this.refreshMetrics();
            });
        }

        // Start auto-refresh if enabled
        if (refreshToggle?.checked) {
            this.startAutoRefresh();
        }
    }

    /**
     * Start auto-refresh interval
     */
    startAutoRefresh(interval = 30000) { // 30 seconds default
        this.stopAutoRefresh(); // Clear any existing interval
        
        this.refreshInterval = setInterval(() => {
            this.refreshMetrics();
        }, interval);

        this.adminPanel.ui.announce('Auto-refresh enabled');
    }

    /**
     * Stop auto-refresh interval
     */
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    /**
     * Refresh dashboard metrics
     */
    async refreshMetrics() {
        const metricsContainer = document.querySelector('.dashboard-metrics');
        if (!metricsContainer) return;

        const loadingIndicator = this.adminPanel.showLoading(metricsContainer);

        try {
            const response = await this.adminPanel.net.get('/api/dashboard/metrics');
            
            // Update metric cards
            this.updateMetricCards(response.metrics);
            
            // Update charts if present
            if (response.charts) {
                this.updateCharts(response.charts);
            }

            // Update last refresh time
            const lastRefreshEl = document.getElementById('lastRefresh');
            if (lastRefreshEl) {
                lastRefreshEl.textContent = new Date().toLocaleTimeString();
            }

        } catch (error) {
            this.adminPanel.ui.showError(`Failed to refresh metrics: ${error.message}`);
        } finally {
            loadingIndicator?.hide();
        }
    }

    /**
     * Update metric cards with new data
     */
    updateMetricCards(metrics) {
        for (const [key, value] of Object.entries(metrics)) {
            const card = document.querySelector(`[data-metric="${key}"]`);
            if (!card) continue;

            const valueEl = card.querySelector('.metric-value');
            const changeEl = card.querySelector('.metric-change');
            const trendEl = card.querySelector('.metric-trend');

            if (valueEl) {
                // Animate value change
                this.animateValue(valueEl, value.current);
            }

            if (changeEl && value.change !== undefined) {
                changeEl.textContent = this.formatChange(value.change);
                changeEl.className = `metric-change ${value.change >= 0 ? 'text-success' : 'text-danger'}`;
            }

            if (trendEl && value.trend) {
                const icon = value.trend === 'up' ? 'fa-arrow-up' : 
                           value.trend === 'down' ? 'fa-arrow-down' : 'fa-minus';
                trendEl.className = `fas ${icon}`;
            }
        }
    }

    /**
     * Animate value changes
     */
    animateValue(element, newValue) {
        const currentValue = parseFloat(element.textContent.replace(/[^\d.-]/g, '')) || 0;
        const difference = newValue - currentValue;
        const steps = 20;
        const stepValue = difference / steps;
        let currentStep = 0;

        const animation = setInterval(() => {
            currentStep++;
            const displayValue = currentValue + (stepValue * currentStep);
            
            element.textContent = this.adminPanel.formatNumber(Math.round(displayValue));
            
            if (currentStep >= steps) {
                clearInterval(animation);
                element.textContent = this.adminPanel.formatNumber(newValue);
            }
        }, 50);
    }

    /**
     * Format change percentage
     */
    formatChange(change) {
        const sign = change >= 0 ? '+' : '';
        return `${sign}${change.toFixed(1)}%`;
    }

    /**
     * Initialize quick actions
     */
    initQuickActions() {
        const quickActions = document.querySelectorAll('.quick-action');
        
        for (const action of quickActions) {
            action.addEventListener('click', async (e) => {
                e.preventDefault();
                
                const actionType = action.dataset.action;
                const requiresConfirm = action.dataset.confirm === 'true';
                
                if (requiresConfirm) {
                    const confirmed = await this.adminPanel.confirmAction(
                        `Are you sure you want to ${actionType}?`
                    );
                    if (!confirmed) return;
                }

                await this.executeQuickAction(actionType, action);
            });
        }
    }

    /**
     * Execute quick action
     */
    async executeQuickAction(actionType, element) {
        const loadingIndicator = this.adminPanel.showLoading(element);

        try {
            const response = await this.adminPanel.net.post(`/api/dashboard/action/${actionType}`);
            
            this.adminPanel.ui.showSuccess(response.message || 'Action completed successfully');
            
            // Refresh metrics after action
            setTimeout(() => {
                this.refreshMetrics();
            }, 1000);

        } catch (error) {
            this.adminPanel.ui.showError(`Failed to execute action: ${error.message}`);
        } finally {
            loadingIndicator?.hide();
        }
    }

    /**
     * Initialize system status monitoring
     */
    initSystemStatus() {
        const statusWidget = document.querySelector('.system-status-widget');
        if (!statusWidget) return;

        // Real-time status updates
        this.startStatusMonitoring();
    }

    /**
     * Start real-time status monitoring
     */
    startStatusMonitoring() {
        // Check status every 10 seconds
        setInterval(async () => {
            try {
                const response = await this.adminPanel.net.get('/api/system/status');
                this.updateSystemStatus(response);
            } catch (error) {
                console.warn('Status check failed:', error);
            }
        }, 10000);
    }

    /**
     * Update system status display
     */
    updateSystemStatus(statusData) {
        const statusElements = {
            cpu: document.querySelector('[data-status="cpu"]'),
            memory: document.querySelector('[data-status="memory"]'),
            disk: document.querySelector('[data-status="disk"]'),
            database: document.querySelector('[data-status="database"]')
        };

        for (const [key, element] of Object.entries(statusElements)) {
            if (!element || !statusData[key]) continue;

            const value = statusData[key];
            const progressBar = element.querySelector('.progress-bar');
            const statusText = element.querySelector('.status-text');
            const statusIcon = element.querySelector('.status-icon');

            if (progressBar) {
                progressBar.style.width = `${value.percentage}%`;
                progressBar.className = `progress-bar ${this.getStatusClass(value.percentage)}`;
            }

            if (statusText) {
                statusText.textContent = `${value.percentage}%`;
            }

            if (statusIcon) {
                const iconClass = value.percentage > 90 ? 'fa-exclamation-triangle text-danger' :
                                value.percentage > 70 ? 'fa-exclamation-circle text-warning' :
                                'fa-check-circle text-success';
                statusIcon.className = `fas ${iconClass}`;
            }
        }
    }

    /**
     * Get status class based on percentage
     */
    getStatusClass(percentage) {
        if (percentage > 90) return 'bg-danger';
        if (percentage > 70) return 'bg-warning';
        return 'bg-success';
    }

    /**
     * Cleanup when leaving dashboard
     */
    destroy() {
        this.stopAutoRefresh();
        
        // Clear any status monitoring intervals
        // (In a real implementation, you'd track these)
        
        console.log('📊 Dashboard module destroyed');
    }
}

export default new DashboardModule();
