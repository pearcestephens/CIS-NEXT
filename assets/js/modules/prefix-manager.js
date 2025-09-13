/**
 * Database Prefix Manager
 * File: assets/js/modules/prefix-manager.js  
 * Purpose: Centralized database table prefix resolution
 */

class PrefixManager {
    constructor() {
        this.prefix = window.DB_PREFIX || '';
        this.tableCache = new Map();
        
        // Common logical table mappings
        this.logicalTables = {
            // Core CIS tables
            'users': 'users',
            'user_sessions': 'user_sessions', 
            'user_roles': 'user_roles',
            'outlets': 'outlets',
            'outlet_config': 'outlet_config',
            'settings': 'settings',
            'audit_logs': 'audit_logs',
            
            // Analytics tables
            'analytics_events': 'analytics_events',
            'analytics_sessions': 'analytics_sessions',
            'analytics_page_views': 'analytics_page_views',
            
            // System tables
            'migrations': 'migrations',
            'cache': 'cache',
            'jobs': 'jobs',
            'failed_jobs': 'failed_jobs',
            'schedules': 'schedules',
            
            // Integration tables
            'integrations': 'integrations',
            'integration_logs': 'integration_logs',
            'vend_sync': 'vend_sync',
            'xero_sync': 'xero_sync',
            
            // Backup tables
            'backups': 'backups',
            'backup_logs': 'backup_logs'
        };
    }

    /**
     * Resolve logical table name to actual prefixed table name
     */
    table(logicalName) {
        // Check cache first
        if (this.tableCache.has(logicalName)) {
            return this.tableCache.get(logicalName);
        }

        // Get the actual table name
        const actualName = this.logicalTables[logicalName] || logicalName;
        const prefixedName = this.prefix + actualName;
        
        // Cache the result
        this.tableCache.set(logicalName, prefixedName);
        
        return prefixedName;
    }

    /**
     * Get raw table name without prefix (for display purposes)
     */
    rawTable(logicalName) {
        return this.logicalTables[logicalName] || logicalName;
    }

    /**
     * Register a new logical table mapping
     */
    register(logicalName, actualName) {
        this.logicalTables[logicalName] = actualName;
        // Clear cache for this table
        this.tableCache.delete(logicalName);
    }

    /**
     * Get all registered logical tables
     */
    getAllTables() {
        return Object.keys(this.logicalTables);
    }

    /**
     * Build SQL query with proper table resolution
     */
    query(sql, params = {}) {
        let resolvedSql = sql;
        
        // Replace {table_name} placeholders with resolved names
        resolvedSql = resolvedSql.replace(/\{([^}]+)\}/g, (match, tableName) => {
            return this.table(tableName);
        });

        return {
            sql: resolvedSql,
            params: params
        };
    }

    /**
     * Validate that SQL doesn't contain hardcoded table names
     */
    validateSql(sql) {
        const issues = [];
        
        // Check for hardcoded cis_ prefix
        const hardcodedPrefixMatches = sql.match(/\bcis_\w+/gi);
        if (hardcodedPrefixMatches) {
            issues.push({
                type: 'hardcoded_prefix',
                matches: hardcodedPrefixMatches,
                message: 'Found hardcoded cis_ table prefixes'
            });
        }

        // Check for unresolved table references in common SQL patterns
        const tablePatterns = [
            /FROM\s+([a-zA-Z_]\w*)/gi,
            /JOIN\s+([a-zA-Z_]\w*)/gi,
            /INTO\s+([a-zA-Z_]\w*)/gi,
            /UPDATE\s+([a-zA-Z_]\w*)/gi
        ];

        for (const pattern of tablePatterns) {
            const matches = [...sql.matchAll(pattern)];
            for (const match of matches) {
                const tableName = match[1];
                // Check if it looks like a table name but isn't resolved
                if (!tableName.startsWith(this.prefix) && 
                    !tableName.includes('{') && 
                    !['SELECT', 'WHERE', 'ORDER', 'GROUP', 'HAVING'].includes(tableName.toUpperCase())) {
                    issues.push({
                        type: 'unresolved_table',
                        table: tableName,
                        message: `Potential unresolved table reference: ${tableName}`
                    });
                }
            }
        }

        return issues;
    }

    /**
     * Get current prefix
     */
    getPrefix() {
        return this.prefix;
    }

    /**
     * Set prefix (for testing purposes)
     */
    setPrefix(newPrefix) {
        this.prefix = newPrefix;
        this.tableCache.clear();
    }
}

// Create singleton instance
const prefixManager = new PrefixManager();

// Create convenient shorthand
window.DB = {
    table: (name) => prefixManager.table(name),
    t: (name) => prefixManager.table(name), // Short alias
    raw: (name) => prefixManager.rawTable(name),
    query: (sql, params) => prefixManager.query(sql, params),
    validate: (sql) => prefixManager.validateSql(sql)
};

export default prefixManager;
