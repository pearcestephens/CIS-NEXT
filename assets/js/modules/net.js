/**
 * Network Helper Module
 * File: assets/js/modules/net.js
 * Purpose: HTTP client with CSRF and timeout handling
 */

class NetHelper {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        this.defaultTimeout = 30000; // 30 seconds
    }

    /**
     * Make an HTTP request with CSRF protection and timeout
     */
    async request(url, options = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), options.timeout || this.defaultTimeout);

        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(this.csrfToken && { 'X-CSRF-Token': this.csrfToken })
            },
            credentials: 'same-origin',
            signal: controller.signal
        };

        const finalOptions = {
            ...defaultOptions,
            ...options,
            headers: { ...defaultOptions.headers, ...options.headers }
        };

        try {
            const response = await fetch(url, finalOptions);
            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            }
            
            return await response.text();
        } catch (error) {
            clearTimeout(timeoutId);
            
            if (error.name === 'AbortError') {
                throw new Error('Request timeout');
            }
            
            throw error;
        }
    }

    /**
     * GET request
     */
    async get(url, options = {}) {
        return this.request(url, { ...options, method: 'GET' });
    }

    /**
     * POST request
     */
    async post(url, data = null, options = {}) {
        const body = data ? JSON.stringify(data) : null;
        return this.request(url, { ...options, method: 'POST', body });
    }

    /**
     * PUT request
     */
    async put(url, data = null, options = {}) {
        const body = data ? JSON.stringify(data) : null;
        return this.request(url, { ...options, method: 'PUT', body });
    }

    /**
     * DELETE request
     */
    async delete(url, options = {}) {
        return this.request(url, { ...options, method: 'DELETE' });
    }

    /**
     * Upload file with progress tracking
     */
    async upload(url, formData, options = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), options.timeout || 60000); // 1 minute for uploads

        const uploadOptions = {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(this.csrfToken && { 'X-CSRF-Token': this.csrfToken })
            },
            credentials: 'same-origin',
            signal: controller.signal
        };

        try {
            const response = await fetch(url, uploadOptions);
            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`Upload failed: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            clearTimeout(timeoutId);
            
            if (error.name === 'AbortError') {
                throw new Error('Upload timeout');
            }
            
            throw error;
        }
    }

    /**
     * Server-sent events helper
     */
    createEventSource(url) {
        const eventSource = new EventSource(url);
        
        eventSource.onerror = (event) => {
            console.warn('EventSource error:', event);
        };

        return eventSource;
    }
}

export default new NetHelper();
