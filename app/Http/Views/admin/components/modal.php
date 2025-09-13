<?php
/**
 * Admin Modal Component
 * File: app/Http/Views/admin/components/modal.php
 * Purpose: Standardized modal dialogs for admin interface
 */

// Default values
$modal_id = $modal_id ?? uniqid('modal_');
$modal_title = $modal_title ?? 'Modal';
$modal_body = $modal_body ?? '';
$modal_footer = $modal_footer ?? [];
$modal_size = $modal_size ?? ''; // sm, lg, xl, or empty for default
$modal_centered = $modal_centered ?? false;
$modal_scrollable = $modal_scrollable ?? false;
$modal_backdrop = $modal_backdrop ?? true; // true, false, or 'static'
$modal_keyboard = $modal_keyboard ?? true;
$modal_focus = $modal_focus ?? true;
$modal_class = $modal_class ?? '';
$modal_header_class = $modal_header_class ?? '';
$modal_body_class = $modal_body_class ?? '';
$modal_form = $modal_form ?? false; // Wrap body in form tag
$modal_form_action = $modal_form_action ?? '';
$modal_form_method = $modal_form_method ?? 'POST';
?>

<div class="modal fade <?= $modal_class ?>" 
     id="<?= htmlspecialchars($modal_id) ?>" 
     tabindex="-1" 
     aria-labelledby="<?= htmlspecialchars($modal_id) ?>Label" 
     aria-hidden="true"
     <?= $modal_backdrop === 'static' ? 'data-bs-backdrop="static"' : '' ?>
     <?= !$modal_keyboard ? 'data-bs-keyboard="false"' : '' ?>>
     
    <div class="modal-dialog <?= $modal_size ? 'modal-' . htmlspecialchars($modal_size) : '' ?> <?= $modal_centered ? 'modal-dialog-centered' : '' ?> <?= $modal_scrollable ? 'modal-dialog-scrollable' : '' ?>">
        <div class="modal-content">
            
            <div class="modal-header <?= $modal_header_class ?>">
                <h5 class="modal-title" id="<?= htmlspecialchars($modal_id) ?>Label">
                    <?= htmlspecialchars($modal_title) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <?php if ($modal_form): ?>
            <form action="<?= htmlspecialchars($modal_form_action) ?>" method="<?= htmlspecialchars($modal_form_method) ?>" id="<?= htmlspecialchars($modal_id) ?>Form">
                <?php if ($modal_form_method === 'POST'): ?>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="modal-body <?= $modal_body_class ?>">
                <?= $modal_body ?>
            </div>
            
            <?php if (!empty($modal_footer)): ?>
            <div class="modal-footer">
                <?php foreach ($modal_footer as $button): ?>
                <button type="<?= htmlspecialchars($button['type'] ?? 'button') ?>" 
                        class="btn <?= htmlspecialchars($button['class'] ?? 'btn-secondary') ?>"
                        <?= isset($button['onclick']) ? 'onclick="' . htmlspecialchars($button['onclick']) . '"' : '' ?>
                        <?= isset($button['data']) ? 'data-action="' . htmlspecialchars($button['data']) . '"' : '' ?>
                        <?= isset($button['dismiss']) && $button['dismiss'] ? 'data-bs-dismiss="modal"' : '' ?>
                        <?= isset($button['disabled']) && $button['disabled'] ? 'disabled' : '' ?>>
                    <?php if (isset($button['icon'])): ?>
                    <i class="<?= htmlspecialchars($button['icon']) ?> me-1"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($button['text']) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($modal_form): ?>
            </form>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<style>
.modal-dialog {
    margin: 1rem auto;
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.25rem;
}

.modal-title {
    font-weight: 600;
    color: #495057;
}

.modal-body {
    padding: 1.25rem;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    padding: 1rem 1.25rem;
}

.modal-footer .btn + .btn {
    margin-left: 0.5rem;
}

/* Loading state */
.modal[data-loading="true"] .modal-content {
    position: relative;
    pointer-events: none;
}

.modal[data-loading="true"] .modal-body::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal[data-loading="true"] .modal-body::before {
    content: '';
    width: 2rem;
    height: 2rem;
    border: 0.25rem solid #f3f3f3;
    border-top: 0.25rem solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1001;
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Responsive sizes */
@media (max-width: 576px) {
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('<?= htmlspecialchars($modal_id) ?>');
    const modalInstance = new bootstrap.Modal(modal, {
        backdrop: <?= json_encode($modal_backdrop) ?>,
        keyboard: <?= json_encode($modal_keyboard) ?>,
        focus: <?= json_encode($modal_focus) ?>
    });
    
    // Handle modal events
    modal.addEventListener('show.bs.modal', function() {
        // Trigger custom event
        document.dispatchEvent(new CustomEvent('modalShow', {
            detail: { modalId: modal.id, modal: modal }
        }));
    });
    
    modal.addEventListener('shown.bs.modal', function() {
        // Focus on first input if exists
        const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            firstInput.focus();
        }
        
        // Trigger custom event
        document.dispatchEvent(new CustomEvent('modalShown', {
            detail: { modalId: modal.id, modal: modal }
        }));
    });
    
    modal.addEventListener('hide.bs.modal', function() {
        // Trigger custom event
        document.dispatchEvent(new CustomEvent('modalHide', {
            detail: { modalId: modal.id, modal: modal }
        }));
    });
    
    modal.addEventListener('hidden.bs.modal', function() {
        // Reset form if exists
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
        
        // Clear any validation states
        modal.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
            el.classList.remove('is-invalid', 'is-valid');
        });
        
        modal.querySelectorAll('.invalid-feedback, .valid-feedback').forEach(el => {
            el.remove();
        });
        
        // Remove loading state
        modal.removeAttribute('data-loading');
        
        // Trigger custom event
        document.dispatchEvent(new CustomEvent('modalHidden', {
            detail: { modalId: modal.id, modal: modal }
        }));
    });
    
    // Handle modal actions
    modal.addEventListener('click', function(e) {
        if (e.target.matches('[data-action]')) {
            const action = e.target.dataset.action;
            const modalId = modal.id;
            
            // Trigger custom event for modal actions
            document.dispatchEvent(new CustomEvent('modalAction', {
                detail: { action, modalId, button: e.target, modal: modal }
            }));
        }
    });
    
    <?php if ($modal_form): ?>
    // Handle form submission
    const form = document.getElementById('<?= htmlspecialchars($modal_id) ?>Form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Trigger custom event for form submission
            const event = new CustomEvent('modalFormSubmit', {
                detail: { form: form, modal: modal, modalId: modal.id },
                cancelable: true
            });
            
            document.dispatchEvent(event);
            
            // If event was prevented, stop form submission
            if (event.defaultPrevented) {
                e.preventDefault();
            }
        });
    }
    <?php endif; ?>
    
    // Store modal instance for external access
    modal.modalInstance = modalInstance;
});

// Modal helper functions
window.AdminModal = window.AdminModal || {};

AdminModal.show = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal && modal.modalInstance) {
        modal.modalInstance.show();
    }
};

AdminModal.hide = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal && modal.modalInstance) {
        modal.modalInstance.hide();
    }
};

AdminModal.setLoading = function(modalId, loading = true) {
    const modal = document.getElementById(modalId);
    if (modal) {
        if (loading) {
            modal.setAttribute('data-loading', 'true');
        } else {
            modal.removeAttribute('data-loading');
        }
    }
};

AdminModal.setTitle = function(modalId, title) {
    const modal = document.getElementById(modalId);
    const titleEl = modal?.querySelector('.modal-title');
    if (titleEl) {
        titleEl.textContent = title;
    }
};

AdminModal.setBody = function(modalId, html) {
    const modal = document.getElementById(modalId);
    const bodyEl = modal?.querySelector('.modal-body');
    if (bodyEl) {
        bodyEl.innerHTML = html;
    }
};
</script>

<?php
/**
 * Helper class for creating modals from PHP
 */
class AdminModal
{
    public static function create(string $id, string $title, string $body, array $options = []): string
    {
        // Set variables for the component
        $modal_id = $id;
        $modal_title = $title;
        $modal_body = $body;
        $modal_footer = $options['footer'] ?? [];
        $modal_size = $options['size'] ?? '';
        $modal_centered = $options['centered'] ?? false;
        $modal_scrollable = $options['scrollable'] ?? false;
        $modal_backdrop = $options['backdrop'] ?? true;
        $modal_keyboard = $options['keyboard'] ?? true;
        $modal_focus = $options['focus'] ?? true;
        $modal_class = $options['class'] ?? '';
        $modal_header_class = $options['header_class'] ?? '';
        $modal_body_class = $options['body_class'] ?? '';
        $modal_form = $options['form'] ?? false;
        $modal_form_action = $options['form_action'] ?? '';
        $modal_form_method = $options['form_method'] ?? 'POST';
        
        // Capture component output
        ob_start();
        include __DIR__ . '/modal.php';
        return ob_get_clean();
    }
    
    public static function confirm(string $id, string $title, string $message, array $options = []): string
    {
        $footer = $options['footer'] ?? [
            [
                'text' => 'Cancel',
                'class' => 'btn-secondary',
                'dismiss' => true
            ],
            [
                'text' => 'Confirm',
                'class' => 'btn-danger',
                'data' => 'confirm'
            ]
        ];
        
        return self::create($id, $title, $message, array_merge($options, ['footer' => $footer]));
    }
    
    public static function form(string $id, string $title, string $body, string $action, array $options = []): string
    {
        $footer = $options['footer'] ?? [
            [
                'text' => 'Cancel',
                'class' => 'btn-secondary',
                'dismiss' => true
            ],
            [
                'text' => 'Save',
                'class' => 'btn-primary',
                'type' => 'submit'
            ]
        ];
        
        return self::create($id, $title, $body, array_merge($options, [
            'form' => true,
            'form_action' => $action,
            'footer' => $footer
        ]));
    }
}
?>
