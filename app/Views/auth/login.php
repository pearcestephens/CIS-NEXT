<?php
/**
 * Login View
 * 
 * User login form for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login to CIS MVC Platform
                    </h4>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/login" id="loginForm">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        
                        <div class="form-group mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope mr-1"></i>Email Address
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   required 
                                   placeholder="Enter your email address"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="form-group mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock mr-1"></i>Password
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       required 
                                       placeholder="Enter your password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <a href="/forgot-password" class="text-decoration-none">
                            <i class="fas fa-question-circle mr-1"></i>Forgot your password?
                        </a>
                    </div>
                    
                    <!-- Demo Credentials -->
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle mr-2"></i>Demo Credentials
                            </h6>
                            <hr>
                            <strong>Administrator:</strong><br>
                            Email: <code>admin@ecigdis.co.nz</code><br>
                            Password: <code>admin123</code>
                            <hr>
                            <strong>Regular User:</strong><br>
                            Email: <code>user@ecigdis.co.nz</code><br>
                            Password: <code>user123</code>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer text-center text-muted">
                    <small>
                        <i class="fas fa-shield-alt mr-1"></i>
                        Secured with CSRF protection and rate limiting
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordField.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

// Form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    if (!email || !password) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (password.length < 3) {
        e.preventDefault();
        alert('Password must be at least 3 characters long.');
        return false;
    }
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Logging in...';
    submitBtn.disabled = true;
});

// Auto-focus first empty field
document.addEventListener('DOMContentLoaded', function() {
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');
    
    if (!emailField.value) {
        emailField.focus();
    } else if (!passwordField.value) {
        passwordField.focus();
    }
});
</script>
