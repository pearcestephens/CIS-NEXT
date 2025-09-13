<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Login - CIS'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome - Latest Version with Fallback -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .login-header {
            background: linear-gradient(135deg, #007bff, #17a2b8);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #007bff, #17a2b8);
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #0056b3, #138496);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header p-4 text-center">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h4 class="mb-0">CIS Login</h4>
                        <p class="mb-0"><small>Central Information System</small></p>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="close" data-dismiss="alert">
                                    <span>&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="/login">
                            <input type="hidden" name="_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                                       required autocomplete="email" autofocus>
                            </div>
                            
                            <div class="form-group">
                                <label for="password"><i class="fas fa-lock"></i> Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       required autocomplete="current-password">
                            </div>
                            
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-login btn-lg btn-block text-white">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                For support, contact IT at 
                                <a href="mailto:pearce.stephens@ecigdis.co.nz">pearce.stephens@ecigdis.co.nz</a>
                            </small>
                        </div>
                        
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6 class="mb-2">Demo Accounts:</h6>
                            <small class="d-block"><strong>Admin:</strong> admin@ecigdis.co.nz / CHANGE_ME_NOW</small>
                            <small class="d-block"><strong>Manager:</strong> manager@ecigdis.co.nz / password123</small>
                            <small class="d-block"><strong>Staff:</strong> staff@ecigdis.co.nz / password123</small>
                            <small class="d-block"><strong>Viewer:</strong> viewer@ecigdis.co.nz / password123</small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="/" class="text-white">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Debug login form submission
        $(document).ready(function() {
            console.log('Login page loaded successfully');
            
            $('form').on('submit', function(e) {
                console.log('Form submission detected');
                
                // Validate required fields
                const email = $('#email').val();
                const password = $('#password').val();
                
                console.log('Email:', email);
                console.log('Password length:', password.length);
                
                if (!email || !password) {
                    e.preventDefault();
                    alert('Please fill in both email and password');
                    return false;
                }
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true);
                submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Signing In...');
                
                // Allow form to submit normally
                console.log('Form validation passed, submitting...');
            });
            
            // Debug CSRF token
            const token = $('input[name="_token"]').val();
            console.log('CSRF token present:', !!token);
            console.log('CSRF token length:', token ? token.length : 0);
            
            // Debug form action
            const action = $('form').attr('action');
            console.log('Form action:', action);
        });
    </script>
</body>
</html>
