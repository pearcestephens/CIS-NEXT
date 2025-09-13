# 🧩 CIS 2.0 UI Component Library Documentation

**Version:** 2.0.0  
**Last Updated:** September 13, 2025  
**Components:** 4 Core Reusable Components  

---

## 📖 **Overview**

The CIS 2.0 component library provides enterprise-grade, accessible, and reusable UI components built on Bootstrap 5. All components follow WCAG 2.1 AA accessibility standards and include proper ARIA support.

### 🎯 **Design Principles**
- **Consistent** - Uniform API across all components
- **Accessible** - WCAG 2.1 AA compliant with ARIA support
- **Flexible** - Highly configurable with sensible defaults
- **Secure** - All output properly escaped

---

## 🃏 **1. Card Component**

**File:** `app/Http/Views/admin/components/card.php`  
**Purpose:** Standardized content containers with status indicators and actions

### **Basic Usage**
```php
<?php
$card_title = 'System Status';
$card_content = 'All systems operational';
include 'app/Http/Views/admin/components/card.php';
?>
```

### **Advanced Configuration**
```php
<?php
$card_id = 'system-status-card';
$card_class = 'mb-4';
$card_title = 'System Status';
$card_subtitle = 'Real-time monitoring';
$card_status = 'success'; // success, warning, danger, info
$card_collapsible = true;
$card_loading = false;
$card_actions = [
    [
        'text' => 'Refresh',
        'class' => 'btn-outline-primary',
        'action' => 'refreshSystemStatus()',
        'icon' => 'fas fa-sync'
    ],
    [
        'text' => 'Settings',
        'class' => 'btn-outline-secondary',
        'action' => 'openSettings()',
        'icon' => 'fas fa-cog'
    ]
];
$card_content = '
    <div class="row">
        <div class="col-md-6">
            <h5 class="text-success">Operational</h5>
            <p>All systems running normally</p>
        </div>
        <div class="col-md-6">
            <div class="progress">
                <div class="progress-bar bg-success" style="width: 100%"></div>
            </div>
        </div>
    </div>
';

include 'app/Http/Views/admin/components/card.php';
?>
```

### **Parameters**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$card_id` | string | `uniqid('card_')` | Unique identifier |
| `$card_class` | string | `''` | Additional CSS classes |
| `$card_title` | string | `''` | Card header title |
| `$card_subtitle` | string | `''` | Card header subtitle |
| `$card_status` | string | `''` | Status color (success, warning, danger, info) |
| `$card_collapsible` | boolean | `false` | Enable collapse functionality |
| `$card_loading` | boolean | `false` | Show loading spinner |
| `$card_actions` | array | `[]` | Action buttons configuration |
| `$card_content` | string | `''` | Card body content |

### **CSS Classes**
- `.card` - Base card styling
- `.border-{status}` - Status border colors
- `.bg-{status}` - Status background colors
- `.card-collapsible` - Collapsible behavior

### **Accessibility Features**
- ✅ Proper heading hierarchy
- ✅ ARIA labels for actions
- ✅ Keyboard navigation support
- ✅ Screen reader announcements

---

## 🪟 **2. Modal Component**

**File:** `app/Http/Views/admin/components/modal.php`  
**Purpose:** Accessible modal dialogs with customizable content and actions

### **Basic Usage**
```php
<?php
$modal_id = 'confirmModal';
$modal_title = 'Confirm Action';
$modal_body = 'Are you sure you want to proceed?';
$modal_footer = '
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="button" class="btn btn-danger">Confirm</button>
';
include 'app/Http/Views/admin/components/modal.php';
?>
```

### **Advanced Configuration**
```php
<?php
$modal_id = 'userEditModal';
$modal_size = 'lg'; // sm, lg, xl
$modal_centered = true;
$modal_scrollable = true;
$modal_backdrop = 'static';
$modal_keyboard = false;
$modal_title = 'Edit User Profile';
$modal_close_button = true;
$modal_body = '
    <form id="userEditForm">
        <div class="mb-3">
            <label for="userName" class="form-label">Name</label>
            <input type="text" class="form-control" id="userName" required>
        </div>
        <div class="mb-3">
            <label for="userEmail" class="form-label">Email</label>
            <input type="email" class="form-control" id="userEmail" required>
        </div>
        <div class="mb-3">
            <label for="userRole" class="form-label">Role</label>
            <select class="form-select" id="userRole">
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="staff">Staff</option>
                <option value="viewer">Viewer</option>
            </select>
        </div>
    </form>
';
$modal_footer = '
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="fas fa-times me-2"></i>Cancel
    </button>
    <button type="button" class="btn btn-primary" onclick="saveUser()">
        <i class="fas fa-save me-2"></i>Save Changes
    </button>
';

include 'app/Http/Views/admin/components/modal.php';
?>

<!-- Trigger Button -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userEditModal">
    <i class="fas fa-edit me-2"></i>Edit User
</button>
```

### **Parameters**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$modal_id` | string | `uniqid('modal_')` | Unique modal identifier |
| `$modal_size` | string | `''` | Modal size (sm, lg, xl) |
| `$modal_centered` | boolean | `false` | Vertically center modal |
| `$modal_scrollable` | boolean | `false` | Enable scrollable body |
| `$modal_backdrop` | string | `true` | Backdrop behavior (true, false, static) |
| `$modal_keyboard` | boolean | `true` | Close on escape key |
| `$modal_title` | string | `''` | Modal header title |
| `$modal_close_button` | boolean | `true` | Show close button |
| `$modal_body` | string | `''` | Modal body content |
| `$modal_footer` | string | `''` | Modal footer content |

### **JavaScript Integration**
```javascript
// Show modal programmatically
const modal = new bootstrap.Modal(document.getElementById('userEditModal'));
modal.show();

// Listen for modal events
document.getElementById('userEditModal').addEventListener('shown.bs.modal', function() {
    document.getElementById('userName').focus();
});

// Hide modal
modal.hide();
```

### **Accessibility Features**
- ✅ Focus trap within modal
- ✅ Escape key support
- ✅ ARIA labelledby and describedby
- ✅ Focus restoration on close
- ✅ Screen reader announcements

---

## 📊 **3. Table Component**

**File:** `app/Http/Views/admin/components/table.php`  
**Purpose:** Data tables with sorting, pagination, and responsive design

### **Basic Usage**
```php
<?php
$table_headers = ['Name', 'Email', 'Role', 'Status'];
$table_data = [
    ['John Smith', 'john@example.com', 'Admin', 'Active'],
    ['Jane Doe', 'jane@example.com', 'Manager', 'Active'],
    ['Bob Wilson', 'bob@example.com', 'Staff', 'Inactive']
];
include 'app/Http/Views/admin/components/table.php';
?>
```

### **Advanced Configuration**
```php
<?php
$table_id = 'usersTable';
$table_class = 'table-hover';
$table_responsive = true;
$table_striped = true;
$table_bordered = false;
$table_small = false;
$table_sortable = true;
$table_searchable = true;
$table_pagination = true;
$table_per_page = 10;
$table_headers = [
    ['text' => 'ID', 'sortable' => true, 'width' => 80],
    ['text' => 'Name', 'sortable' => true],
    ['text' => 'Email', 'sortable' => true],
    ['text' => 'Role', 'sortable' => true, 'filterable' => true],
    ['text' => 'Created', 'sortable' => true, 'type' => 'date'],
    ['text' => 'Status', 'sortable' => true, 'filterable' => true],
    ['text' => 'Actions', 'sortable' => false, 'width' => 120]
];
$table_data = [
    [
        '1',
        'John Smith',
        'john@example.com',
        '<span class="badge bg-primary">Admin</span>',
        '2025-01-15',
        '<span class="badge bg-success">Active</span>',
        '
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary" onclick="editUser(1)">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-outline-danger" onclick="deleteUser(1)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        '
    ],
    // ... more rows
];
$table_empty_text = 'No users found';
$table_loading = false;

include 'app/Http/Views/admin/components/table.php';
?>
```

### **Parameters**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$table_id` | string | `uniqid('table_')` | Unique table identifier |
| `$table_class` | string | `''` | Additional CSS classes |
| `$table_responsive` | boolean | `true` | Responsive wrapper |
| `$table_striped` | boolean | `false` | Striped rows |
| `$table_bordered` | boolean | `false` | Table borders |
| `$table_small` | boolean | `false` | Compact sizing |
| `$table_sortable` | boolean | `false` | Enable sorting |
| `$table_searchable` | boolean | `false` | Enable search |
| `$table_pagination` | boolean | `false` | Enable pagination |
| `$table_per_page` | integer | `10` | Rows per page |
| `$table_headers` | array | `[]` | Column headers |
| `$table_data` | array | `[]` | Table data rows |
| `$table_empty_text` | string | `'No data available'` | Empty state text |
| `$table_loading` | boolean | `false` | Loading state |

### **Header Configuration**
```php
$table_headers = [
    [
        'text' => 'Column Name',      // Display text
        'sortable' => true,           // Enable sorting
        'filterable' => true,         // Enable filtering
        'searchable' => true,         // Include in search
        'width' => 120,               // Column width
        'type' => 'date',             // Data type for sorting
        'class' => 'text-center'      // Additional CSS classes
    ]
];
```

### **JavaScript Features**
```javascript
// Initialize DataTable (if enabled)
$('#usersTable').DataTable({
    responsive: true,
    pageLength: 10,
    order: [[0, 'asc']]
});

// Custom search
function searchTable(query) {
    $('#usersTable').DataTable().search(query).draw();
}

// Export functions
function exportTableCSV() {
    // Export logic
}
```

### **Accessibility Features**
- ✅ Sortable column headers with ARIA
- ✅ Table caption and summary
- ✅ Keyboard navigation
- ✅ Screen reader table structure
- ✅ Skip links for large tables

---

## 🔔 **4. Alert Component**

**File:** `app/Http/Views/admin/components/alert.php`  
**Purpose:** Toast notifications and alert messages with auto-dismiss

### **Basic Usage**
```php
<?php
$alert_message = 'Settings saved successfully!';
$alert_type = 'success';
include 'app/Http/Views/admin/components/alert.php';
?>
```

### **Advanced Configuration**
```php
<?php
$alert_id = 'systemAlert';
$alert_type = 'warning'; // success, info, warning, danger
$alert_dismissible = true;
$alert_auto_dismiss = true;
$alert_dismiss_delay = 5000; // milliseconds
$alert_icon = 'fas fa-exclamation-triangle';
$alert_title = 'System Warning';
$alert_message = 'The system will undergo maintenance in 30 minutes. Please save your work.';
$alert_actions = [
    [
        'text' => 'Dismiss',
        'class' => 'btn-outline-warning btn-sm',
        'action' => 'dismissAlert()',
        'icon' => 'fas fa-times'
    ],
    [
        'text' => 'Learn More',
        'class' => 'btn-warning btn-sm',
        'action' => 'showMaintenanceInfo()',
        'icon' => 'fas fa-info-circle'
    ]
];
$alert_compact = false;

include 'app/Http/Views/admin/components/alert.php';
?>
```

### **Toast Notifications**
```php
<?php
$alert_toast = true;
$alert_position = 'top-end'; // top-start, top-center, top-end, bottom-start, etc.
$alert_type = 'success';
$alert_title = 'Success!';
$alert_message = 'User profile updated successfully.';
$alert_auto_dismiss = true;
$alert_dismiss_delay = 3000;

include 'app/Http/Views/admin/components/alert.php';
?>
```

### **Parameters**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$alert_id` | string | `uniqid('alert_')` | Unique alert identifier |
| `$alert_type` | string | `'info'` | Alert type (success, info, warning, danger) |
| `$alert_dismissible` | boolean | `true` | Show dismiss button |
| `$alert_auto_dismiss` | boolean | `false` | Auto-dismiss after delay |
| `$alert_dismiss_delay` | integer | `5000` | Auto-dismiss delay (ms) |
| `$alert_toast` | boolean | `false` | Render as toast notification |
| `$alert_position` | string | `'top-end'` | Toast position |
| `$alert_icon` | string | `''` | Alert icon class |
| `$alert_title` | string | `''` | Alert title |
| `$alert_message` | string | `''` | Alert message |
| `$alert_actions` | array | `[]` | Action buttons |
| `$alert_compact` | boolean | `false` | Compact styling |

### **JavaScript API**
```javascript
// Show alert programmatically
window.showAlert = function(message, type = 'info', title = '', actions = []) {
    // Implementation
};

// Examples
showAlert('Operation completed!', 'success');
showAlert('Please check your input', 'warning', 'Validation Error');
showAlert('Server error occurred', 'danger', 'Error', [
    { text: 'Retry', action: 'retryOperation()' }
]);

// Toast notifications
window.showToast = function(message, type = 'info', delay = 3000) {
    // Implementation
};

showToast('Changes saved!', 'success');
showToast('Connection lost', 'warning', 5000);
```

### **Accessibility Features**
- ✅ ARIA live regions for announcements
- ✅ Proper role attributes
- ✅ Focus management for actions
- ✅ Screen reader friendly
- ✅ Keyboard dismiss support

---

## 🎨 **Styling & Theming**

### **CSS Custom Properties**
```css
:root {
    --component-border-radius: 0.375rem;
    --component-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    --component-transition: all 0.15s ease-in-out;
}
```

### **Dark Mode Support**
All components automatically adapt to Bootstrap's dark mode:
```html
<html data-bs-theme="dark">
```

### **Customization**
Override component styles:
```css
.custom-card {
    --bs-card-border-radius: 1rem;
    --bs-card-box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
```

---

## 🧪 **Testing & Quality Assurance**

### **Manual Testing Checklist**
- [ ] All parameters work correctly
- [ ] Responsive design on mobile/tablet/desktop
- [ ] Keyboard navigation functions
- [ ] Screen reader compatibility
- [ ] Color contrast meets WCAG AA
- [ ] JavaScript functionality
- [ ] Cross-browser compatibility

### **Automated Testing**
```javascript
// Component unit tests
describe('Card Component', () => {
    test('renders with default parameters', () => {
        // Test implementation
    });
    
    test('handles status colors correctly', () => {
        // Test implementation
    });
});
```

---

## 📚 **Best Practices**

### **Do's**
- ✅ Always escape user input with `htmlspecialchars()`
- ✅ Use semantic HTML elements
- ✅ Include ARIA labels for interactive elements
- ✅ Test with keyboard navigation
- ✅ Verify color contrast ratios
- ✅ Use consistent parameter naming

### **Don'ts**
- ❌ Don't mix HTML in PHP variables without escaping
- ❌ Don't skip accessibility attributes
- ❌ Don't use generic IDs that might conflict
- ❌ Don't ignore responsive design
- ❌ Don't hardcode colors in components

---

## 🔧 **Development Guidelines**

### **Adding New Components**
1. Create PHP component file in `app/Http/Views/admin/components/`
2. Follow existing parameter naming conventions
3. Include comprehensive documentation
4. Add accessibility features
5. Test responsive behavior
6. Update this documentation

### **Component API Standards**
- Use `$component_` prefix for all parameters
- Provide sensible defaults
- Support `$component_id` for unique identification
- Include `$component_class` for custom styling
- Escape all output with `htmlspecialchars()`

---

## 📖 **Quick Reference**

### **Component Files**
```
app/Http/Views/admin/components/
├── card.php     # Content containers with status
├── modal.php    # Accessible modal dialogs  
├── table.php    # Data tables with features
└── alert.php    # Notifications and toasts
```

### **Common Parameters**
- `{component}_id` - Unique identifier
- `{component}_class` - Additional CSS classes
- `{component}_loading` - Loading state
- `{component}_actions` - Action buttons array

### **Bootstrap Integration**
All components use Bootstrap 5 classes and JavaScript:
- Cards use `.card`, `.card-header`, `.card-body`
- Modals use Bootstrap modal JavaScript API
- Tables use `.table` with responsive wrappers
- Alerts use `.alert` with dismissible functionality

---

**📝 For questions or contributions, refer to the CIS 2.0 development team.**
