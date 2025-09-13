<?php
/**
 * Admin Card Component
 * File: app/Http/Views/admin/components/card.php
 * Purpose: Standardized card component for admin interface
 */

// Default values
$card_id = $card_id ?? uniqid('card_');
$card_class = $card_class ?? '';
$card_title = $card_title ?? '';
$card_subtitle = $card_subtitle ?? '';
$card_actions = $card_actions ?? [];
$card_status = $card_status ?? ''; // success, warning, danger, info
$card_collapsible = $card_collapsible ?? false;
$card_loading = $card_loading ?? false;
$card_content = $card_content ?? '';
?>

<div class="card <?= $card_class ?> <?= $card_status ? "border-{$card_status}" : '' ?>" 
     id="<?= htmlspecialchars($card_id) ?>" 
     <?= $card_loading ? 'data-loading="true"' : '' ?>>
     
    <?php if ($card_title || !empty($card_actions) || $card_collapsible): ?>
    <div class="card-header <?= $card_status ? "bg-{$card_status} bg-opacity-10" : '' ?>">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <?php if ($card_title): ?>
                <h5 class="card-title mb-0 <?= $card_status ? "text-{$card_status}" : '' ?>">
                    <?= htmlspecialchars($card_title) ?>
                </h5>
                <?php endif; ?>
                
                <?php if ($card_subtitle): ?>
                <p class="card-subtitle mb-0 text-muted small">
                    <?= htmlspecialchars($card_subtitle) ?>
                </p>
                <?php endif; ?>
            </div>
            
            <div class="d-flex align-items-center">
                <?php if ($card_loading): ?>
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <?php endif; ?>
                
                <?php foreach ($card_actions as $action): ?>
                <button type="button" 
                        class="btn <?= $action['class'] ?? 'btn-outline-secondary' ?> btn-sm me-1"
                        <?= isset($action['onclick']) ? 'onclick="' . htmlspecialchars($action['onclick']) . '"' : '' ?>
                        <?= isset($action['data']) ? 'data-action="' . htmlspecialchars($action['data']) . '"' : '' ?>
                        <?= isset($action['disabled']) && $action['disabled'] ? 'disabled' : '' ?>>
                    <?php if (isset($action['icon'])): ?>
                    <i class="<?= htmlspecialchars($action['icon']) ?>"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($action['text'] ?? '') ?>
                </button>
                <?php endforeach; ?>
                
                <?php if ($card_collapsible): ?>
                <button type="button" 
                        class="btn btn-outline-secondary btn-sm"
                        data-bs-toggle="collapse" 
                        data-bs-target="#<?= htmlspecialchars($card_id) ?>_body"
                        aria-expanded="true">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="<?= $card_collapsible ? 'collapse show' : '' ?>" 
         <?= $card_collapsible ? 'id="' . htmlspecialchars($card_id) . '_body"' : '' ?>>
        <div class="card-body">
            <?php if ($card_loading): ?>
            <div class="d-flex justify-content-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <?php else: ?>
                <?= $card_content ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card[data-loading="true"] {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

.card-header .spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

.card .btn-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.card.border-success { border-color: var(--bs-success) !important; }
.card.border-warning { border-color: var(--bs-warning) !important; }
.card.border-danger { border-color: var(--bs-danger) !important; }
.card.border-info { border-color: var(--bs-info) !important; }
</style>

<script>
// Card component JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Handle collapsible cards
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const target = document.querySelector(this.dataset.bsTarget);
            
            target.addEventListener('shown.bs.collapse', function() {
                icon.className = 'fas fa-chevron-up';
            });
            
            target.addEventListener('hidden.bs.collapse', function() {
                icon.className = 'fas fa-chevron-down';
            });
        });
    });
    
    // Handle card actions
    document.querySelectorAll('[data-action]').forEach(function(button) {
        button.addEventListener('click', function() {
            const action = this.dataset.action;
            const cardId = this.closest('.card').id;
            
            // Trigger custom event for card actions
            document.dispatchEvent(new CustomEvent('cardAction', {
                detail: { action, cardId, button: this }
            }));
        });
    });
});
</script>
