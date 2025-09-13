<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

/**
 * Session Start Middleware
 * Manages secure session handling and releases locks quickly
 */
class SessionStart
{
    public function handle(array $request, callable $next): mixed
    {
        // Start session if not already active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Read user data if authenticated
        $isAuthenticated = isset($_SESSION['user_id']);
        
        if ($isAuthenticated) {
            // Store user context for request
            $_REQUEST['_user'] = [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'] ?? null,
                'role_id' => $_SESSION['role_id'] ?? null,
                'permissions' => $_SESSION['permissions'] ?? [],
            ];
        }
        
        // Close session for writing ASAP to prevent lock contention
        session_write_close();
        }
        
        return $next($request);
    }
} 
