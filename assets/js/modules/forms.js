/**
 * Forms Helper Module
 * File: assets/js/modules/forms.js
 * Purpose: Form validation, submission, and UI enhancements
 */

class FormsHelper {
    constructor() {
        this.validators = new Map();
        this.setupDefaultValidators();
    }

    /**
     * Setup default validation rules
     */
    setupDefaultValidators() {
        this.validators.set('required', (value) => {
            return value.trim() !== '' || 'This field is required';
        });

        this.validators.set('email', (value) => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(value) || 'Please enter a valid email address';
        });

        this.validators.set('min', (value, min) => {
            return value.length >= min || `Minimum ${min} characters required`;
        });

        this.validators.set('max', (value, max) => {
            return value.length <= max || `Maximum ${max} characters allowed`;
        });

        this.validators.set('pattern', (value, pattern) => {
            const regex = new RegExp(pattern);
            return regex.test(value) || 'Please match the required format';
        });

        this.validators.set('numeric', (value) => {
            return /^[0-9]+$/.test(value) || 'Only numbers are allowed';
        });

        this.validators.set('alpha', (value) => {
            return /^[a-zA-Z]+$/.test(value) || 'Only letters are allowed';
        });

        this.validators.set('alphanumeric', (value) => {
            return /^[a-zA-Z0-9]+$/.test(value) || 'Only letters and numbers are allowed';
        });
    }

    /**
     * Validate a single field
     */
    validateField(field) {
        const value = field.value;
        const rules = field.dataset.validate?.split('|') || [];
        const errors = [];

        for (const rule of rules) {
            const [validatorName, ...params] = rule.split(':');
            const validator = this.validators.get(validatorName);
            
            if (validator) {
                const result = validator(value, ...params);
                if (result !== true) {
                    errors.push(result);
                }
            }
        }

        this.showFieldValidation(field, errors);
        return errors.length === 0;
    }

    /**
     * Show field validation state
     */
    showFieldValidation(field, errors) {
        const feedbackEl = field.parentElement.querySelector('.invalid-feedback');
        
        if (errors.length > 0) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            
            if (feedbackEl) {
                feedbackEl.textContent = errors[0];
            }
        } else if (field.value.trim() !== '') {
            field.classList.add('is-valid');
            field.classList.remove('is-invalid');
            
            if (feedbackEl) {
                feedbackEl.textContent = '';
            }
        } else {
            field.classList.remove('is-valid', 'is-invalid');
            
            if (feedbackEl) {
                feedbackEl.textContent = '';
            }
        }
    }

    /**
     * Validate entire form
     */
    validateForm(form) {
        const fields = form.querySelectorAll('input[data-validate], textarea[data-validate], select[data-validate]');
        let isValid = true;

        for (const field of fields) {
            if (!this.validateField(field)) {
                isValid = false;
            }
        }

        return isValid;
    }

    /**
     * Setup form validation
     */
    setupValidation(form) {
        const fields = form.querySelectorAll('input[data-validate], textarea[data-validate], select[data-validate]');

        for (const field of fields) {
            // Real-time validation on blur
            field.addEventListener('blur', () => {
                this.validateField(field);
            });

            // Clear validation on input
            field.addEventListener('input', () => {
                if (field.classList.contains('is-invalid')) {
                    this.validateField(field);
                }
            });

            // Add required asterisk
            if (field.dataset.validate?.includes('required')) {
                const label = form.querySelector(`label[for="${field.id}"]`);
                if (label && !label.querySelector('.required')) {
                    const asterisk = document.createElement('span');
                    asterisk.className = 'required text-danger';
                    asterisk.textContent = ' *';
                    label.appendChild(asterisk);
                }
            }
        }

        // Prevent submission if invalid
        form.addEventListener('submit', (e) => {
            if (!this.validateForm(form)) {
                e.preventDefault();
                e.stopPropagation();
                
                // Focus first invalid field
                const firstInvalid = form.querySelector('.is-invalid');
                firstInvalid?.focus();
            }
        });
    }

    /**
     * Setup AJAX form submission
     */
    setupAjaxSubmission(form, options = {}) {
        const {
            onSuccess = null,
            onError = null,
            showLoading = true,
            resetOnSuccess = false
        } = options;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!this.validateForm(form)) {
                return;
            }

            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            let loadingIndicator = null;

            if (showLoading && submitBtn) {
                loadingIndicator = window.AdminPanel.ui.showLoading(submitBtn, 'Submitting...');
                submitBtn.disabled = true;
            }

            try {
                const response = await window.AdminPanel.net.post(form.action, Object.fromEntries(formData));
                
                if (onSuccess) {
                    onSuccess(response, form);
                } else {
                    window.AdminPanel.ui.showSuccess('Form submitted successfully');
                }

                if (resetOnSuccess) {
                    form.reset();
                    this.clearValidation(form);
                }

            } catch (error) {
                if (onError) {
                    onError(error, form);
                } else {
                    window.AdminPanel.ui.showError(`Submission failed: ${error.message}`);
                }
            } finally {
                if (loadingIndicator) {
                    loadingIndicator.hide();
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            }
        });
    }

    /**
     * Clear form validation
     */
    clearValidation(form) {
        const fields = form.querySelectorAll('.is-valid, .is-invalid');
        for (const field of fields) {
            field.classList.remove('is-valid', 'is-invalid');
        }

        const feedbacks = form.querySelectorAll('.invalid-feedback');
        for (const feedback of feedbacks) {
            feedback.textContent = '';
        }
    }

    /**
     * Setup character counters
     */
    setupCharacterCounters(form) {
        const fields = form.querySelectorAll('input[maxlength], textarea[maxlength]');

        for (const field of fields) {
            const maxLength = parseInt(field.getAttribute('maxlength'));
            const counter = document.createElement('small');
            counter.className = 'form-text text-muted char-counter';
            counter.textContent = `0 / ${maxLength}`;
            
            field.parentElement.appendChild(counter);

            const updateCounter = () => {
                const currentLength = field.value.length;
                counter.textContent = `${currentLength} / ${maxLength}`;
                
                if (currentLength > maxLength * 0.9) {
                    counter.classList.add('text-warning');
                } else {
                    counter.classList.remove('text-warning');
                }
            };

            field.addEventListener('input', updateCounter);
            updateCounter(); // Initial count
        }
    }

    /**
     * Setup file upload with preview
     */
    setupFileUpload(input, options = {}) {
        const {
            maxSize = 5 * 1024 * 1024, // 5MB
            allowedTypes = [],
            showPreview = true,
            multiple = false
        } = options;

        input.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            const container = input.closest('.file-upload-container') || input.parentElement;
            let previewContainer = container.querySelector('.file-preview');

            if (showPreview && !previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.className = 'file-preview mt-2';
                container.appendChild(previewContainer);
            }

            if (previewContainer) {
                previewContainer.innerHTML = '';
            }

            for (const file of files) {
                // Validate file size
                if (file.size > maxSize) {
                    window.AdminPanel.ui.showError(`File "${file.name}" is too large. Maximum size: ${window.AdminPanel.ui.formatFileSize(maxSize)}`);
                    continue;
                }

                // Validate file type
                if (allowedTypes.length > 0 && !allowedTypes.includes(file.type)) {
                    window.AdminPanel.ui.showError(`File "${file.name}" has an invalid type. Allowed: ${allowedTypes.join(', ')}`);
                    continue;
                }

                // Show preview
                if (showPreview && previewContainer) {
                    const preview = this.createFilePreview(file);
                    previewContainer.appendChild(preview);
                }
            }
        });
    }

    /**
     * Create file preview element
     */
    createFilePreview(file) {
        const preview = document.createElement('div');
        preview.className = 'file-preview-item d-flex align-items-center p-2 border rounded mb-2';

        const icon = document.createElement('i');
        icon.className = 'fas fa-file me-2';
        
        if (file.type.startsWith('image/')) {
            icon.className = 'fas fa-image me-2 text-primary';
        } else if (file.type.includes('pdf')) {
            icon.className = 'fas fa-file-pdf me-2 text-danger';
        } else if (file.type.includes('word')) {
            icon.className = 'fas fa-file-word me-2 text-info';
        } else if (file.type.includes('excel') || file.type.includes('spreadsheet')) {
            icon.className = 'fas fa-file-excel me-2 text-success';
        }

        const info = document.createElement('div');
        info.className = 'flex-grow-1';
        info.innerHTML = `
            <div class="fw-medium">${file.name}</div>
            <small class="text-muted">${window.AdminPanel.ui.formatFileSize(file.size)}</small>
        `;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.addEventListener('click', () => {
            preview.remove();
        });

        preview.appendChild(icon);
        preview.appendChild(info);
        preview.appendChild(removeBtn);

        return preview;
    }

    /**
     * Add custom validator
     */
    addValidator(name, validator) {
        this.validators.set(name, validator);
    }

    /**
     * Initialize all form features
     */
    initialize(form) {
        this.setupValidation(form);
        this.setupCharacterCounters(form);

        // Setup file uploads
        const fileInputs = form.querySelectorAll('input[type="file"]');
        for (const input of fileInputs) {
            const options = {
                maxSize: parseInt(input.dataset.maxSize) || 5 * 1024 * 1024,
                allowedTypes: input.dataset.allowedTypes ? input.dataset.allowedTypes.split(',') : [],
                showPreview: input.dataset.showPreview !== 'false',
                multiple: input.hasAttribute('multiple')
            };
            this.setupFileUpload(input, options);
        }
    }
}

export default new FormsHelper();
