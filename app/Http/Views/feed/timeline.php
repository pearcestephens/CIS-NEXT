<?php
$page_title = $page_title ?? 'AI Timeline - CIS';
$events = $events ?? [];
$outlets = $outlets ?? [];
$filters = $filters ?? [];
$total_count = $total_count ?? 0;
$user_preferences = $user_preferences ?? [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- Bootstrap 4.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Font Awesome - Latest Version with Fallback -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Custom CSS -->
    <link href="/assets/css/feed.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/admin/dashboard">
                <i class="fas fa-chart-line"></i> CIS
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/dashboard">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link" href="/admin/feed/timeline">
                            <i class="fas fa-stream"></i> Timeline
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/feed/newsfeed">
                            <i class="fas fa-newspaper"></i> Newsfeed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/feed/stores">
                            <i class="fas fa-store-alt"></i> Stores
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="/admin/profile">Profile</a>
                            <a class="dropdown-item" href="/admin/settings">Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="/auth/logout">Logout</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar Filters -->
            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter"></i> Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="timeline-filters">
                            <!-- Outlet Filter -->
                            <div class="form-group">
                                <label for="outlet-filter">Store</label>
                                <select class="form-control" id="outlet-filter" name="outlet">
                                    <option value="all" <?= ($filters['outlet'] ?? '') === 'all' ? 'selected' : '' ?>>All Stores</option>
                                    <?php foreach ($outlets as $outlet): ?>
                                    <option value="<?= $outlet['id'] ?>" <?= ($filters['outlet'] ?? '') == $outlet['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($outlet['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Severity Filter -->
                            <div class="form-group">
                                <label for="severity-filter">Severity</label>
                                <select class="form-control" id="severity-filter" name="severity">
                                    <option value="all" <?= ($filters['severity'] ?? '') === 'all' ? 'selected' : '' ?>>All Levels</option>
                                    <option value="critical" <?= ($filters['severity'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option>
                                    <option value="error" <?= ($filters['severity'] ?? '') === 'error' ? 'selected' : '' ?>>Error</option>
                                    <option value="warning" <?= ($filters['severity'] ?? '') === 'warning' ? 'selected' : '' ?>>Warning</option>
                                    <option value="info" <?= ($filters['severity'] ?? '') === 'info' ? 'selected' : '' ?>>Info</option>
                                </select>
                            </div>
                            
                            <!-- Source Filter -->
                            <div class="form-group">
                                <label for="source-filter">Source</label>
                                <select class="form-control" id="source-filter" name="source">
                                    <option value="all" <?= ($filters['source'] ?? '') === 'all' ? 'selected' : '' ?>>All Sources</option>
                                    <option value="vend" <?= ($filters['source'] ?? '') === 'vend' ? 'selected' : '' ?>>Vend POS</option>
                                    <option value="po" <?= ($filters['source'] ?? '') === 'po' ? 'selected' : '' ?>>Purchase Orders</option>
                                    <option value="qa" <?= ($filters['source'] ?? '') === 'qa' ? 'selected' : '' ?>>Quality Assurance</option>
                                    <option value="training" <?= ($filters['source'] ?? '') === 'training' ? 'selected' : '' ?>>Training</option>
                                    <option value="ciswatch" <?= ($filters['source'] ?? '') === 'ciswatch' ? 'selected' : '' ?>>CISWatch</option>
                                    <option value="system" <?= ($filters['source'] ?? '') === 'system' ? 'selected' : '' ?>>System</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                        </form>
                        
                        <hr>
                        
                        <!-- Quick Stats -->
                        <div class="text-center">
                            <h6 class="text-muted">Showing Events</h6>
                            <h4 class="text-primary" id="event-count"><?= number_format($total_count) ?></h4>
                            <small class="text-muted">total events</small>
                        </div>
                        
                        <?php if ($user_preferences['ai_suggestions_enabled'] ?? true): ?>
                        <hr>
                        <div class="alert alert-info alert-sm">
                            <i class="fas fa-robot"></i> AI insights enabled
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Timeline Content -->
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>AI Timeline</h2>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary" id="refresh-timeline">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="mark-all-read">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </div>
                </div>
                
                <!-- Events Timeline -->
                <div id="timeline-container">
                    <?php if (empty($events)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No events found</h4>
                        <p class="text-muted">Try adjusting your filters or check back later.</p>
                    </div>
                    <?php else: ?>
                    
                    <?php foreach ($events as $event): ?>
                    <div class="timeline-event card mb-3 <?= ($event['is_read'] ?? false) ? 'read' : 'unread' ?>" data-event-id="<?= $event['id'] ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <!-- Severity Badge -->
                                        <span class="badge badge-<?= $controller->getSeverityClass($event['severity']) ?> mr-2">
                                            <?= strtoupper($event['severity']) ?>
                                        </span>
                                        
                                        <!-- Source Badge -->
                                        <span class="badge badge-secondary mr-2">
                                            <?= strtoupper($event['source']) ?>
                                        </span>
                                        
                                        <!-- Outlet Badge -->
                                        <?php if (!empty($event['outlet_name'])): ?>
                                        <span class="badge badge-light mr-2">
                                            <i class="fas fa-store"></i> <?= htmlspecialchars($event['outlet_name']) ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <!-- Timestamp -->
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> 
                                            <time datetime="<?= $event['timestamp'] ?>" title="<?= date('Y-m-d H:i:s', strtotime($event['timestamp'])) ?>">
                                                <?= $this->timeAgo($event['timestamp']) ?>
                                            </time>
                                        </small>
                                    </div>
                                    
                                    <!-- Event Title -->
                                    <h5 class="card-title mb-1">
                                        <?php if ($event['entity_url']): ?>
                                        <a href="<?= htmlspecialchars($event['entity_url']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($event['title']) ?>
                                        </a>
                                        <?php else: ?>
                                        <?= htmlspecialchars($event['title']) ?>
                                        <?php endif; ?>
                                    </h5>
                                    
                                    <!-- Event Summary -->
                                    <?php if (!empty($event['summary'])): ?>
                                    <p class="card-text">
                                        <?= htmlspecialchars($event['summary']) ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <!-- AI-Generated Content -->
                                    <?php if ($event['is_ai_generated'] && !empty($event['ai_insights'])): ?>
                                    <div class="alert alert-light border-left-ai">
                                        <small class="text-muted">
                                            <i class="fas fa-robot"></i> AI Insight:
                                        </small>
                                        <div class="mt-1">
                                            <?= htmlspecialchars($event['ai_insights']) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Metadata Tags -->
                                    <?php if (!empty($event['metadata'])): ?>
                                    <div class="mt-2">
                                        <?php foreach ($event['metadata'] as $key => $value): ?>
                                        <span class="badge badge-pill badge-light mr-1">
                                            <?= htmlspecialchars("$key: $value") ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Actions -->
                                <div class="ml-3">
                                    <div class="btn-group-vertical btn-group-sm">
                                        <?php if (!$event['is_read']): ?>
                                        <button type="button" class="btn btn-outline-success mark-read" title="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-outline-secondary" title="More details" 
                                                onclick="showEventDetails(<?= $event['id'] ?>)">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                        
                                        <?php if ($event['entity_url']): ?>
                                        <a href="<?= htmlspecialchars($event['entity_url']) ?>" 
                                           class="btn btn-outline-primary" title="View details">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Load More Button -->
                    <?php if (count($events) >= ($filters['limit'] ?? 50)): ?>
                    <div class="text-center py-3">
                        <button type="button" class="btn btn-outline-primary" id="load-more-events" 
                                data-offset="<?= ($filters['offset'] ?? 0) + ($filters['limit'] ?? 50) ?>">
                            <i class="fas fa-chevron-down"></i> Load More Events
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal fade" id="event-details-modal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Event Details</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="event-details-content">
                    <div class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Timeline functionality
        $(document).ready(function() {
            // Filter form submission
            $('#timeline-filters').on('submit', function(e) {
                e.preventDefault();
                const params = new URLSearchParams();
                params.set('outlet', $('#outlet-filter').val());
                params.set('severity', $('#severity-filter').val());
                params.set('source', $('#source-filter').val());
                
                window.location.href = '/admin/feed/timeline?' + params.toString();
            });
            
            // Mark event as read
            $(document).on('click', '.mark-read', function() {
                const eventCard = $(this).closest('.timeline-event');
                const eventId = eventCard.data('event-id');
                
                $.post('/api/feed/events/mark-read', {
                    event_id: eventId
                }).done(function(response) {
                    if (response.success) {
                        eventCard.removeClass('unread').addClass('read');
                        eventCard.find('.mark-read').remove();
                    }
                }).fail(function() {
                    alert('Failed to mark event as read');
                });
            });
            
            // Load more events
            $('#load-more-events').on('click', function() {
                const offset = $(this).data('offset');
                const params = new URLSearchParams(window.location.search);
                params.set('offset', offset);
                
                window.location.href = '/admin/feed/timeline?' + params.toString();
            });
            
            // Refresh timeline
            $('#refresh-timeline').on('click', function() {
                window.location.reload();
            });
            
            // Auto-refresh every 60 seconds
            setInterval(function() {
                // Only refresh if user hasn't interacted recently
                if (Date.now() - lastUserInteraction > 30000) {
                    window.location.reload();
                }
            }, 60000);
            
            let lastUserInteraction = Date.now();
            $(document).on('click keypress scroll', function() {
                lastUserInteraction = Date.now();
            });
        });
        
        function showEventDetails(eventId) {
            $('#event-details-modal').modal('show');
            
            $.get('/api/feed/events/' + eventId)
                .done(function(response) {
                    if (response.success) {
                        $('#event-details-content').html(formatEventDetails(response.data));
                    } else {
                        $('#event-details-content').html('<div class="alert alert-danger">Failed to load event details</div>');
                    }
                })
                .fail(function() {
                    $('#event-details-content').html('<div class="alert alert-danger">Error loading event details</div>');
                });
        }
        
        function formatEventDetails(event) {
            // Format event details for modal display
            return `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Title:</strong> ${event.title}<br>
                        <strong>Severity:</strong> <span class="badge badge-${getSeverityClass(event.severity)}">${event.severity.toUpperCase()}</span><br>
                        <strong>Source:</strong> ${event.source}<br>
                        <strong>Timestamp:</strong> ${new Date(event.timestamp).toLocaleString()}<br>
                    </div>
                    <div class="col-md-6">
                        ${event.outlet_name ? '<strong>Store:</strong> ' + event.outlet_name + '<br>' : ''}
                        ${event.entity_url ? '<strong>Link:</strong> <a href="' + event.entity_url + '" target="_blank">View Details</a><br>' : ''}
                    </div>
                </div>
                ${event.summary ? '<div class="mt-3"><strong>Summary:</strong><p>' + event.summary + '</p></div>' : ''}
                ${event.metadata ? '<div class="mt-3"><strong>Metadata:</strong><pre>' + JSON.stringify(event.metadata, null, 2) + '</pre></div>' : ''}
            `;
        }
        
        function getSeverityClass(severity) {
            const classes = {
                'critical': 'danger',
                'error': 'danger', 
                'warning': 'warning',
                'info': 'info'
            };
            return classes[severity] || 'secondary';
        }
    </script>
</body>
</html>

<?php
// Helper method for severity CSS classes
function getSeverityClass(string $severity): string {
    return match($severity) {
        'critical', 'error' => 'danger',
        'warning' => 'warning', 
        'info' => 'info',
        default => 'secondary'
    };
}

// Helper method for relative time display
function timeAgo(string $timestamp): string {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    
    return date('M j, Y', $time);
}
?>
