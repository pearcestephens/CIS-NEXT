<?php
/**
 * Admin Alert Component
 * File: app/Http/Views/admin/components/alert.php
 * Purpose: Standardized alert/notification system for admin interface
 */

// Default values
$alert_type = $alert_type ?? 'info'; // success, warning, danger, info, primary, secondary
$alert_message = $alert_message ?? '';
$alert_title = $alert_title ?? '';
$alert_dismissible = $alert_dismissible ?? true;
$alert_icon = $alert_icon ?? true;
$alert_id = $alert_id ?? uniqid('alert_');
$alert_class = $alert_class ?? '';
$alert_timeout = $alert_timeout ?? 0; // Auto-dismiss after X seconds (0 = no auto-dismiss)
$alert_actions = $alert_actions ?? [];

// Icon mapping
$alert_icons = [
    'success' => 'fas fa-check-circle',
    'warning' => 'fas fa-exclamation-triangle', 
    'danger' => 'fas fa-exclamation-circle',
    'info' => 'fas fa-info-circle',
    'primary' => 'fas fa-bell',
    'secondary' => 'fas fa-comment'
];

$icon_class = $alert_icons[$alert_type] ?? 'fas fa-info-circle';
?>

<div class="alert alert-<?= htmlspecialchars($alert_type) ?> <?= $alert_dismissible ? 'alert-dismissible' : '' ?> <?= $alert_class ?> fade show" 
     role="alert" 
     id="<?= htmlspecialchars($alert_id) ?>"
     <?= $alert_timeout > 0 ? 'data-timeout="' . (int)$alert_timeout . '"' : '' ?>>
     
    <div class="d-flex align-items-start">
        <?php if ($alert_icon): ?>
        <div class="me-3">
            <i class="<?= htmlspecialchars($icon_class) ?> fa-lg"></i>
        </div>
        <?php endif; ?>
        
        <div class="flex-grow-1">
            <?php if ($alert_title): ?>
            <h6 class="alert-heading mb-2">
                <?= htmlspecialchars($alert_title) ?>
            </h6>
            <?php endif; ?>
            
            <div class="alert-message">
                <?= htmlspecialchars($alert_message) ?>
            </div>
            
            <?php if (!empty($alert_actions)): ?>
            <div class="alert-actions mt-3">
                <?php foreach ($alert_actions as $action): ?>
                <button type="button" 
                        class="btn <?= $action['class'] ?? 'btn-outline-' . $alert_type ?> btn-sm me-2"
                        <?= isset($action['onclick']) ? 'onclick="' . htmlspecialchars($action['onclick']) . '"' : '' ?>
                        <?= isset($action['data']) ? 'data-action="' . htmlspecialchars($action['data']) . '"' : '' ?>>
                    <?php if (isset($action['icon'])): ?>
                    <i class="<?= htmlspecialchars($action['icon']) ?> me-1"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($action['text']) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($alert_dismissible): ?>
        <button type="button" 
                class="btn-close" 
                data-bs-dismiss="alert" 
                aria-label="Close"></button>
        <?php endif; ?>
    </div>
</div>

<style>
.alert {
    border: 1px solid;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}

.alert .fa-lg {
    font-size: 1.2em;
    margin-top: 0.1rem;
}

.alert-heading {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.alert-message {
    line-height: 1.5;
}

.alert-actions .btn-sm {
    font-size: 0.8rem;
    padding: 0.25rem 0.75rem;
}

/* Custom alert variants */
.alert-success {
    background-color: #d1e7dd;
    border-color: #badbcc;
    color: #0f5132;
}

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffecb5;
    color: #664d03;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c2c7;
    color: #721c24;
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #055160;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const alert = document.getElementById('<?= htmlspecialchars($alert_id) ?>');
    
    <?php if ($alert_timeout > 0): ?>
    // Auto-dismiss after timeout
    setTimeout(function() {
        if (alert && alert.classList.contains('show')) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, <?= (int)$alert_timeout * 1000 ?>);
    <?php endif; ?>
    
    // Handle alert actions
    alert.addEventListener('click', function(e) {
        if (e.target.matches('[data-action]')) {
            const action = e.target.dataset.action;
            const alertId = alert.id;
            
            // Trigger custom event for alert actions
            document.dispatchEvent(new CustomEvent('alertAction', {
                detail: { action, alertId, button: e.target }
            }));
        }
    });
});
</script>

<?php
/**
 * Helper function to create alerts from PHP
 * Usage: AdminAlert::create('success', 'Operation completed!', 'Success', ['dismissible' => true])
 */
class AdminAlert 
{
    public static function create(string $type, string $message, string $title = '', array $options = []): string
    {
        // Set variables for the component
        $alert_type = $type;
        $alert_message = $message;
        $alert_title = $title;
        $alert_dismissible = $options['dismissible'] ?? true;
        $alert_icon = $options['icon'] ?? true;
        $alert_id = $options['id'] ?? uniqid('alert_');
        $alert_class = $options['class'] ?? '';
        $alert_timeout = $options['timeout'] ?? 0;
        $alert_actions = $options['actions'] ?? [];
        
        // Capture component output
        ob_start();
        include __DIR__ . '/alert.php';
        return ob_get_clean();
    }
    
    public static function success(string $message, string $title = 'Success', array $options = []): string
    {
        return self::create('success', $message, $title, $options);
    }
    
    public static function warning(string $message, string $title = 'Warning', array $options = []): string
    {
        return self::create('warning', $message, $title, $options);
    }
    
    public static function danger(string $message, string $title = 'Error', array $options = []): string
    {
        return self::create('danger', $message, $title, $options);
    }
    
    public static function info(string $message, string $title = 'Information', array $options = []): string
    {
        return self::create('info', $message, $title, $options);
    }
}

/**
 * Global alert display function
 */
function show_admin_alert(string $type, string $message, string $title = '', array $options = [])
{
    // Set session flash for displaying on next page load
    if (!isset($_SESSION)) {
        session_start();
    }
    
    $_SESSION['admin_alert'] = [
        'type' => $type,
        'message' => $message,
        'title' => $title,
        'options' => $options,
        'timestamp' => time()
    ];
}

/**
 * Display session flash alerts
 */
function display_session_alerts()
{
    if (!isset($_SESSION)) {
        session_start();
    }
    
    if (isset($_SESSION['admin_alert'])) {
        $alert = $_SESSION['admin_alert'];
        
        // Only show alerts from the last 30 seconds to prevent stale alerts
        if (time() - $alert['timestamp'] < 30) {
            echo AdminAlert::create(
                $alert['type'],
                $alert['message'],
                $alert['title'],
                $alert['options']
            );
        }
        
        unset($_SESSION['admin_alert']);
    }
}
?>
