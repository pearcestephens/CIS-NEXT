/**
 * Cache Management Page Module
 * @author CIS Developer Bot
 * @created 2025-09-13
 */

import { ui } from '../modules/ui.js';
import { net } from '../modules/net.js';

let cacheData = [];
let currentPage = 1;
let searchFilter = '';

export function init() {
    const root = document.querySelector('[data-page="cache"]');
    if (!root) return;

    console.log('Initializing cache management page...');

    // Wire up action buttons
    setupEventListeners(root);
    
    // Load initial data
    loadCacheData();
    
    // Auto-refresh every 30 seconds
    setInterval(loadCacheData, 30000);
}

function setupEventListeners(root) {
    // Refresh button
    const refreshBtn = root.querySelector('[data-action="refresh"]');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            loadCacheData();
            ui.showToast('Cache data refreshed', 'success');
        });
    }
    
    // Flush all button
    const flushAllBtn = root.querySelector('[data-action="flush-all"]');
    if (flushAllBtn) {
        flushAllBtn.addEventListener('click', handleFlushAll);
    }
    
    // Flush by tags button
    const flushTagsBtn = root.querySelector('[data-action="flush-tags"]');
    if (flushTagsBtn) {
        flushTagsBtn.addEventListener('click', handleFlushTags);
    }
    
    // Search filter
    const searchInput = root.querySelector('[data-filter="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
    }
}

async function loadCacheData() {
    try {
        // Load cache statistics
        const statsResponse = await net.get('/admin/api/cache/stats');
        if (statsResponse.success) {
            updateStatistics(statsResponse.data);
        }
        
        // Load cache keys
        const keysResponse = await net.get('/admin/api/cache/keys', {
            page: currentPage,
            search: searchFilter
        });
        
        if (keysResponse.success) {
            cacheData = keysResponse.data.keys;
            updateKeysTable(keysResponse.data);
        }
        
    } catch (error) {
        console.error('Failed to load cache data:', error);
        ui.showToast('Failed to load cache data', 'error');
        
        // Show mock data for demo
        showMockData();
    }
}

function updateStatistics(stats) {
    const hitRate = document.querySelector('[data-stat="hit-rate"]');
    const memoryUsage = document.querySelector('[data-stat="memory-usage"]');
    const keyCount = document.querySelector('[data-stat="key-count"]');
    
    if (hitRate) hitRate.textContent = (stats.hit_rate || 0).toFixed(1) + '%';
    if (memoryUsage) memoryUsage.textContent = formatBytes(stats.memory_usage || 0);
    if (keyCount) keyCount.textContent = (stats.key_count || 0).toLocaleString();
}

function updateKeysTable(data) {
    const tbody = document.querySelector('[data-cache-keys]');
    if (!tbody) return;
    
    if (!data.keys || data.keys.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    No cache keys found
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = data.keys.map(key => `
        <tr data-key="${key.name}">
            <td>
                <code class="text-primary">${escapeHtml(key.name)}</code>
                ${key.tags?.length ? `<br><small class="text-muted">${key.tags.join(', ')}</small>` : ''}
            </td>
            <td><span class="badge bg-secondary">${key.type}</span></td>
            <td>${formatBytes(key.size)}</td>
            <td>${formatTTL(key.ttl)}</td>
            <td>
                ${key.tags?.map(tag => `<span class="badge bg-info me-1">${tag}</span>`).join('') || '—'}
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" data-action="view" data-key="${key.name}">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" data-action="delete" data-key="${key.name}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    // Wire up row actions
    tbody.addEventListener('click', handleRowAction);
    
    // Update pagination
    updatePagination(data.pagination);
}

async function handleFlushAll() {
    const confirmed = await ui.confirm(
        'Flush All Cache',
        'Are you sure you want to flush all cache keys? This action cannot be undone.',
        'danger'
    );
    
    if (!confirmed) return;
    
    try {
        const response = await net.post('/admin/api/cache/flush');
        
        if (response.success) {
            ui.showToast('All cache keys flushed successfully', 'success');
            loadCacheData();
        } else {
            throw new Error(response.error?.message || 'Failed to flush cache');
        }
    } catch (error) {
        console.error('Flush failed:', error);
        ui.showToast('Failed to flush cache: ' + error.message, 'error');
    }
}

async function handleFlushTags() {
    const tags = await ui.prompt('Flush by Tags', 'Enter comma-separated tags to flush:');
    
    if (!tags) return;
    
    try {
        const response = await net.post('/admin/api/cache/flush-tags', {
            tags: tags.split(',').map(tag => tag.trim())
        });
        
        if (response.success) {
            ui.showToast(`Flushed cache for tags: ${tags}`, 'success');
            loadCacheData();
        } else {
            throw new Error(response.error?.message || 'Failed to flush cache by tags');
        }
    } catch (error) {
        console.error('Tag flush failed:', error);
        ui.showToast('Failed to flush cache by tags: ' + error.message, 'error');
    }
}

function handleSearch(event) {
    searchFilter = event.target.value;
    currentPage = 1;
    
    // Debounced search
    clearTimeout(handleSearch.timeout);
    handleSearch.timeout = setTimeout(loadCacheData, 500);
}

async function handleRowAction(event) {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    
    const action = button.dataset.action;
    const key = button.dataset.key;
    
    switch (action) {
        case 'view':
            await viewCacheKey(key);
            break;
        case 'delete':
            await deleteCacheKey(key);
            break;
    }
}

async function viewCacheKey(key) {
    try {
        const response = await net.get(`/admin/api/cache/key/${encodeURIComponent(key)}`);
        
        if (response.success) {
            const data = response.data;
            
            ui.showModal('Cache Key Details', `
                <div class="row">
                    <div class="col-sm-3 font-weight-bold">Key:</div>
                    <div class="col-sm-9"><code>${escapeHtml(data.key)}</code></div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-3 font-weight-bold">Type:</div>
                    <div class="col-sm-9">${data.type}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-3 font-weight-bold">Size:</div>
                    <div class="col-sm-9">${formatBytes(data.size)}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-3 font-weight-bold">TTL:</div>
                    <div class="col-sm-9">${formatTTL(data.ttl)}</div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-3 font-weight-bold">Value:</div>
                    <div class="col-sm-9"><pre class="bg-light p-2 small">${escapeHtml(JSON.stringify(data.value, null, 2))}</pre></div>
                </div>
            `);
        }
    } catch (error) {
        console.error('Failed to view cache key:', error);
        ui.showToast('Failed to load cache key details', 'error');
    }
}

async function deleteCacheKey(key) {
    const confirmed = await ui.confirm(
        'Delete Cache Key',
        `Are you sure you want to delete the cache key "${key}"?`,
        'danger'
    );
    
    if (!confirmed) return;
    
    try {
        const response = await net.delete(`/admin/api/cache/key/${encodeURIComponent(key)}`);
        
        if (response.success) {
            ui.showToast(`Cache key "${key}" deleted`, 'success');
            loadCacheData();
        } else {
            throw new Error(response.error?.message || 'Failed to delete cache key');
        }
    } catch (error) {
        console.error('Delete failed:', error);
        ui.showToast('Failed to delete cache key: ' + error.message, 'error');
    }
}

function updatePagination(pagination) {
    const container = document.querySelector('[data-pagination]');
    if (!container || !pagination) return;
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    container.innerHTML = `
        <ul class="pagination pagination-sm mb-0">
            ${pagination.current_page > 1 ? `
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${pagination.current_page - 1}">Previous</a>
                </li>
            ` : ''}
            
            ${Array.from({length: pagination.total_pages}, (_, i) => i + 1)
                .filter(page => Math.abs(page - pagination.current_page) <= 2 || page === 1 || page === pagination.total_pages)
                .map(page => `
                    <li class="page-item ${page === pagination.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${page}">${page}</a>
                    </li>
                `).join('')}
            
            ${pagination.current_page < pagination.total_pages ? `
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${pagination.current_page + 1}">Next</a>
                </li>
            ` : ''}
        </ul>
    `;
    
    // Wire up pagination
    container.addEventListener('click', (event) => {
        event.preventDefault();
        const link = event.target.closest('[data-page]');
        if (link) {
            currentPage = parseInt(link.dataset.page);
            loadCacheData();
        }
    });
}

function showMockData() {
    // Show mock data for demonstration
    updateStatistics({
        hit_rate: 87.3,
        memory_usage: 134217728, // 128MB
        key_count: 1247
    });
    
    updateKeysTable({
        keys: [
            {
                name: 'users:1:profile',
                type: 'string',
                size: 1024,
                ttl: 3600,
                tags: ['users', 'profiles']
            },
            {
                name: 'settings:cache_driver',
                type: 'string',
                size: 512,
                ttl: -1,
                tags: ['settings']
            },
            {
                name: 'analytics:daily:2025-09-13',
                type: 'hash',
                size: 2048,
                ttl: 86400,
                tags: ['analytics', 'daily']
            }
        ],
        pagination: {
            current_page: 1,
            total_pages: 1,
            total_items: 3
        }
    });
}

// Utility functions
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatTTL(ttl) {
    if (ttl === -1) return '∞ (no expiry)';
    if (ttl < 60) return `${ttl}s`;
    if (ttl < 3600) return `${Math.floor(ttl / 60)}m`;
    if (ttl < 86400) return `${Math.floor(ttl / 3600)}h`;
    return `${Math.floor(ttl / 86400)}d`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
