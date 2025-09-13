# Admin UI Technical Notes

## Overview

The CIS Admin UI has been modernized to Bootstrap 5 with vanilla JavaScript ES modules, removing jQuery dependencies while maintaining security, accessibility, and performance.

## Architecture

### CSS Structure
- **CSS Custom Properties**: Theme variables in `:root` for light/dark themes
- **Bootstrap 5.3.2**: Modern framework with built-in dark mode support  
- **Responsive Design**: Mobile-first with collapsible sidebar
- **No Preprocessors**: Plain CSS with variables for maintainability

### JavaScript Modules
```javascript
// Core modules
import ui from './modules/ui.js';        // Toasts, modals, focus traps
import net from './modules/net.js';      // HTTP client with CSRF
import forms from './modules/forms.js';  // Validation, submission
import tables from './modules/tables.js'; // Sorting, filtering, bulk ops
```

### Page-Specific Modules
Place in `assets/js/modules/pages/{page-name}.js`:
```javascript
// Example: dashboard.js
class DashboardModule {
    init(adminPanel) {
        this.adminPanel = adminPanel;
        // Initialize page-specific functionality
    }
}
export default new DashboardModule();
```

## Adding a New Page

1. **Create the PHP view** in `app/Http/Views/admin/`
2. **Add data-page attribute** to body: `<body data-page="my-page">`
3. **Create page module** (optional): `assets/js/modules/pages/my-page.js`
4. **Add navigation** to sidebar partial

### Example Page Structure
```php
<?php
// Set page metadata
$page_title = 'My Page';
$page_icon = 'fas fa-example';
$page_module = 'my-page'; // For JS module loading
$page_header = true;

// Include layout
include __DIR__ . '/../layout.php';
?>

<div class="admin-content">
    <!-- Page content here -->
</div>
```

## Security & Compliance

### Content Security Policy (CSP)
- **All scripts** must use `type="module"` with PHP nonce
- **No inline handlers** - use dataset attributes and event listeners
- **No wildcard CDNs** - specific integrity hashes only

### CSRF Protection
```javascript
// Automatic CSRF injection
const response = await AdminPanel.net.post('/api/endpoint', data);

// Manual form tokens
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
```

### Database Prefix Safety
```javascript
// Always use prefix resolution
const query = `SELECT * FROM ${DB.t('users')} WHERE active = 1`;

// Never hardcode table names
const bad = `SELECT * FROM cis_users`; // ❌ Will fail prefix linting
```

## Component Guidelines

### Forms
```html
<!-- Add data-admin-form for auto-initialization -->
<form data-admin-form data-ajax-submit="true" action="/api/save">
    <input type="text" name="name" data-validate="required|min:2" class="form-control">
    <div class="invalid-feedback"></div>
    
    <button type="submit" class="btn btn-primary">Save</button>
</form>
```

### Tables
```html
<!-- Enhanced table with sorting and filtering -->
<table class="table admin-table" data-admin-table data-sortable="true" data-filterable="true">
    <thead>
        <tr>
            <th data-sortable="name">Name</th>
            <th data-sortable="created_date">Created</th>
        </tr>
    </thead>
    <tbody>
        <!-- Table rows -->
    </tbody>
</table>
```

### Notifications
```javascript
// Show notifications
AdminPanel.ui.showSuccess('Operation completed');
AdminPanel.ui.showError('Something went wrong');
AdminPanel.ui.showWarning('Please review this');

// Confirmation dialogs
const confirmed = await AdminPanel.ui.showConfirm(
    'Delete Item',
    'This action cannot be undone',
    { confirmText: 'Delete', confirmClass: 'btn-danger' }
);
```

## Accessibility Features

- **Keyboard Navigation**: Tab order, Enter/Space activation
- **Screen Reader Support**: ARIA labels, live regions, semantic markup  
- **Focus Management**: Trap focus in modals, visible focus indicators
- **Color Contrast**: WCAG 2.2 AA compliance in both themes
- **Reduced Motion**: Respects `prefers-reduced-motion` setting

## Performance Optimizations

- **Module Preloading**: Critical modules loaded via `<link rel="modulepreload">`
- **Lazy Loading**: Page modules loaded on-demand
- **Debounced Inputs**: Search, filter, and validation delays
- **Efficient DOM**: Minimal queries, event delegation, virtual scrolling

## Theme System

### CSS Variables
```css
:root {
    --admin-primary: #0d6efd;
    --admin-surface: #ffffff;
    --admin-text: #212529;
}

[data-bs-theme="dark"] {
    --admin-surface: #1e1e1e;  
    --admin-text: #ffffff;
}
```

### Theme Toggle
```javascript
// Programmatically set theme
AdminPanel.setTheme('dark');

// User preference stored in localStorage
// System preference detected automatically
```

## Error Handling

### Global Error Handler
```javascript
// Automatic error capture and user-friendly messages
window.addEventListener('error', (event) => {
    AdminPanel.handleError(event.error, 'Global Error');
});
```

### Network Errors
```javascript
try {
    const data = await AdminPanel.net.get('/api/data');
} catch (error) {
    // Automatic user notification + console logging
    AdminPanel.handleError(error, 'API Request');
}
```

## Development Workflow

### Adding Features
1. Use existing modules where possible
2. Create focused, single-responsibility modules
3. Follow naming conventions: kebab-case for files, PascalCase for classes
4. Add JSDoc comments for public methods
5. Test with both themes and reduced motion

### Code Style
- **Modern ES6+**: Classes, modules, async/await, destructuring
- **No jQuery**: Use native DOM APIs and Fetch
- **Type Safety**: JSDoc annotations for IDE support
- **Error Safety**: Try/catch blocks, null checks, graceful degradation

## Migration Notes

### From jQuery to Vanilla JS
```javascript
// Old jQuery patterns
$('.selector').on('click', handler);
$('.selector').addClass('active');
$.ajax({url: '/api'});

// New vanilla patterns  
document.querySelectorAll('.selector').forEach(el => 
    el.addEventListener('click', handler)
);
element.classList.add('active');
await AdminPanel.net.get('/api');
```

### Bootstrap 4 to 5 Changes
- `data-toggle` → `data-bs-toggle`
- `data-target` → `data-bs-target` 
- `close` button uses `btn-close` class
- `dropdown-menu-right` → `dropdown-menu-end`
- `ml-*` / `mr-*` → `ms-*` / `me-*`

This architecture provides a solid foundation for building maintainable, accessible, and performant admin interfaces while keeping security and developer experience at the forefront.
