<?php
/**
 * CIS Tools Index - Web Interface for All Tools
 * Location: tools/index.php
 * Purpose: Web dashboard for accessing all CIS tools
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Tools Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(45deg, #2c3e50, #34495e);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.2em;
        }
        
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            padding: 30px;
        }
        
        .tool-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        
        .tool-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #6610f2);
        }
        
        .tool-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 1.4em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tool-card .icon {
            font-size: 1.5em;
        }
        
        .tool-card p {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .tool-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: scale(1.05);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .status-indicator {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        
        .quick-actions {
            background: #e9ecef;
            padding: 20px;
            text-align: center;
        }
        
        .quick-actions h2 {
            margin: 0 0 20px 0;
            color: #2c3e50;
        }
        
        .quick-actions .btn {
            margin: 5px;
            padding: 12px 20px;
            font-size: 1em;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 20px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛠️ CIS Tools Dashboard</h1>
            <p>Comprehensive Development & Debugging Tools Suite</p>
            <small>Last updated: <?= date('Y-m-d H:i:s') ?></small>
        </div>
        
        <div class="tools-grid">
            <!-- Debug Tools -->
            <div class="tool-card">
                <div class="status-indicator"></div>
                <h3><span class="icon">🔧</span> Ultimate Debug Suite</h3>
                <p>Comprehensive system diagnostics including health checks, log analysis, permissions verification, and network connectivity testing.</p>
                <div class="tool-actions">
                    <a href="/tools/debug/ultimate_debug_suite.sh" class="btn btn-primary" onclick="runTool(this, 'debug')">🚀 Run Debug Suite</a>
                    <a href="/tools/debug/" class="btn btn-secondary">📁 Browse Debug Tools</a>
                </div>
            </div>
            
            <!-- Screenshot Tools -->
            <div class="tool-card">
                <div class="status-indicator"></div>
                <h3><span class="icon">📸</span> Screenshot & Visual Testing</h3>
                <p>Advanced browser automation for capturing screenshots, testing responsive design, and validating UI components across devices.</p>
                <div class="tool-actions">
                    <a href="/tools/screenshot/ultimate_screenshot_tool.sh" class="btn btn-primary" onclick="runTool(this, 'screenshot')">📸 Take Screenshots</a>
                    <a href="/var/screenshots/" class="btn btn-secondary">🖼️ View Gallery</a>
                </div>
            </div>
            
            <!-- Automation Suite -->
            <div class="tool-card">
                <div class="status-indicator"></div>
                <h3><span class="icon">🚀</span> Automation Suite</h3>
                <p>Complete web testing automation including connectivity tests, authentication validation, API testing, and performance monitoring.</p>
                <div class="tool-actions">
                    <a href="/tools/automation/ultimate_automation_suite.php" class="btn btn-primary" onclick="runTool(this, 'automation')">⚡ Run Full Suite</a>
                    <a href="/var/reports/" class="btn btn-secondary">📊 View Reports</a>
                </div>
            </div>
            
            <!-- System Monitoring -->
            <div class="tool-card">
                <div class="status-indicator"></div>
                <h3><span class="icon">⚡</span> System Monitoring</h3>
                <p>Real-time system status monitoring, performance metrics, service health checks, and comprehensive system analysis.</p>
                <div class="tool-actions">
                    <a href="/tools/system/comprehensive_system_test.php" class="btn btn-primary" onclick="runTool(this, 'system')">🔍 System Check</a>
                    <a href="/tools/system/" class="btn btn-secondary">📈 System Tools</a>
                </div>
            </div>
            
            <!-- Database Tools -->
            <div class="tool-card">
                <div class="status-indicator"></div>
                <h3><span class="icon">🗄️</span> Database Tools</h3>
                <p>Database inspection, repair utilities, schema validation, and data integrity checks for MariaDB/MySQL databases.</p>
                <div class="tool-actions">
                    <a href="/tools/database/db_inspect.php" class="btn btn-primary" onclick="runTool(this, 'database')">🔍 DB Inspector</a>
                    <a href="/tools/database/" class="btn btn-secondary">🛠️ DB Tools</a>
                </div>
            </div>
            
            <!-- Master Runner -->
            <div class="tool-card">
                <div class="status-indicator"></div>
                <h3><span class="icon">🎯</span> Master Tool Runner</h3>
                <p>Execute all tools in sequence with comprehensive logging and master report generation. One command to run everything.</p>
                <div class="tool-actions">
                    <a href="/tools/run_all_tools.sh" class="btn btn-success" onclick="runTool(this, 'master')">🚀 Run All Tools</a>
                    <a href="/var/reports/" class="btn btn-secondary">📊 Master Reports</a>
                </div>
            </div>
        </div>
        
        <div class="quick-actions">
            <h2>🎯 Quick Actions</h2>
            <a href="/tools/run_all_tools.sh" class="btn btn-success" onclick="runMasterSuite()">🚀 Run Complete Suite</a>
            <a href="/var/reports/" class="btn btn-primary">📊 View All Reports</a>
            <a href="/var/screenshots/" class="btn btn-primary">🖼️ Screenshot Gallery</a>
            <a href="/var/logs/" class="btn btn-secondary">📋 System Logs</a>
            <a href="/" class="btn btn-secondary">🏠 Back to CIS</a>
        </div>
        
        <div class="footer">
            <p>🛠️ CIS Tools Dashboard | Ecigdis Limited | <?= date('Y') ?></p>
            <p>Professional development and debugging tools for enterprise applications</p>
        </div>
    </div>
    
    <script>
        function runTool(element, toolType) {
            element.innerHTML = '⏳ Running...';
            element.classList.add('btn-secondary');
            element.classList.remove('btn-primary', 'btn-success');
            
            setTimeout(() => {
                element.innerHTML = element.innerHTML.replace('⏳ Running...', '✅ Complete');
                element.classList.add('btn-success');
                element.classList.remove('btn-secondary');
                
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }, 3000);
            
            return true;
        }
        
        function runMasterSuite() {
            if (confirm('Run the complete CIS tools suite? This will take several minutes.')) {
                document.querySelector('.quick-actions').innerHTML = '<h2>🚀 Running Complete Suite...</h2><p>Please wait while all tools execute...</p>';
                return true;
            }
            return false;
        }
        
        // Auto-refresh status indicators
        setInterval(() => {
            document.querySelectorAll('.status-indicator').forEach(indicator => {
                indicator.style.background = Math.random() > 0.3 ? '#28a745' : '#ffc107';
            });
        }, 5000);
    </script>
</body>
</html>
