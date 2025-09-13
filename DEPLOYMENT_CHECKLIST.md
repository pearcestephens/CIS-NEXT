# 🛠️ DEPLOYMENT CHECKLIST - CIS 2.0

## ✅ Pre-Deployment Tasks

### **1. File Permissions & Security**
```bash
# Set proper permissions
chmod 755 /var/www/cis.dev.ecigdis.co.nz/public_html
chmod 644 /var/www/cis.dev.ecigdis.co.nz/public_html/*.php
chmod 755 /var/www/cis.dev.ecigdis.co.nz/public_html/app
chmod 755 /var/www/cis.dev.ecigdis.co.nz/public_html/cache
chmod 755 /var/www/cis.dev.ecigdis.co.nz/public_html/logs

# Create required directories
mkdir -p cache logs
```

### **2. Environment Configuration**
- [ ] Create `.env` file with production settings
- [ ] Configure database credentials
- [ ] Set session security settings
- [ ] Enable production error handling

### **3. Database Setup**
```sql
-- Create job queue table for background processing
CREATE TABLE job_queue (
    id VARCHAR(100) PRIMARY KEY,
    job_class VARCHAR(255) NOT NULL,
    data TEXT,
    priority INT DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    run_at INT NOT NULL,
    created_at INT NOT NULL,
    updated_at INT NOT NULL,
    completed_at INT NULL,
    error_message TEXT NULL,
    INDEX idx_status_priority_run_at (status, priority, run_at),
    INDEX idx_created_at (created_at)
);

-- Create security log table
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    ip_address VARCHAR(45),
    user_id INT NULL,
    message TEXT,
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at)
);

-- Create metrics table for performance tracking
CREATE TABLE metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,4) NOT NULL,
    tags JSON,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_name (metric_name),
    INDEX idx_recorded_at (recorded_at)
);
```

## 🚀 Deployment Steps

### **Step 1: Backup Current System**
```bash
# Backup database
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup files
tar -czf cis_backup_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/cis.dev.ecigdis.co.nz/
```

### **Step 2: Deploy New Files**
- [ ] Upload all new files to production
- [ ] Update `.htaccess` for clean URLs
- [ ] Configure Apache/Nginx virtual host
- [ ] Test file permissions

### **Step 3: Test Critical Paths**
```bash
# Test dashboard loading
curl -I https://staff.vapeshed.co.nz/admin/dashboard

# Test API endpoints
curl https://staff.vapeshed.co.nz/api/admin/metrics

# Test health endpoint
curl https://staff.vapeshed.co.nz/_health
```

### **Step 4: Monitor & Verify**
- [ ] Check error logs for issues
- [ ] Verify dashboard loads correctly
- [ ] Test admin authentication
- [ ] Confirm API responses
- [ ] Check performance metrics

## 🔧 Production Configuration

### **Apache .htaccess**
```apache
RewriteEngine On

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# HTTPS redirect
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Route all requests to front controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index_mvc.php [QSA,L]

# Block access to sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<FilesMatch "\.(log|cache)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### **PHP Configuration (php.ini)**
```ini
# Production settings
display_errors = Off
log_errors = On
error_log = /var/www/cis.dev.ecigdis.co.nz/logs/php_errors.log

# Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

# Session security
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1

# Performance
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
```

## 📊 Monitoring Setup

### **Log Rotation**
```bash
# Create logrotate configuration
sudo tee /etc/logrotate.d/cis << EOF
/var/www/cis.dev.ecigdis.co.nz/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
EOF
```

### **Cron Jobs**
```bash
# Add to crontab
# Queue processing every minute
* * * * * php /var/www/cis.dev.ecigdis.co.nz/public_html/queue_worker.php

# Cache cleanup every hour
0 * * * * php /var/www/cis.dev.ecigdis.co.nz/public_html/cache_cleanup.php

# Log cleanup daily at 2 AM
0 2 * * * find /var/www/cis.dev.ecigdis.co.nz/logs -name "*.log" -mtime +30 -delete
```

## 🚨 Emergency Procedures

### **Rollback Plan**
1. **Immediate**: Restore from backup
2. **Database**: Restore database backup
3. **Files**: Restore file backup
4. **DNS**: Point to backup server if needed

### **Health Checks**
```bash
# Quick system check
curl -f https://staff.vapeshed.co.nz/_health || echo "ALERT: Health check failed"

# Database connectivity
mysql -u username -p -e "SELECT 1" database_name || echo "ALERT: Database down"

# Disk space check
df -h | grep -E '(8[5-9]|9[0-9]|100)%' && echo "ALERT: Disk space low"
```

## 📞 Support Contacts

- **Primary**: Pearce Stephens (pearce.stephens@ecigdis.co.nz)
- **Hosting**: Cloudways Support
- **Database**: Internal IT Team

## ✅ Go-Live Checklist

- [ ] ✅ All files deployed and tested
- [ ] ✅ Database migrations completed
- [ ] ✅ SSL certificate valid
- [ ] ✅ Monitoring configured
- [ ] ✅ Backups verified
- [ ] ✅ Performance baselines established
- [ ] ✅ Team trained on new interface
- [ ] ✅ Emergency procedures documented

---

**🎉 Deployment Complete! CIS 2.0 is LIVE!** 🚀
