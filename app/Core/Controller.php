<?php
/**
 * Base Controller Class
 * 
 * Base controller for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

namespace App\Core;

use Exception;

abstract class Controller
{
    protected array $data = [];
    protected string $layout = 'app';
    protected Security $security;

    public function __construct()
    {
        $this->security = new Security();
        $this->initializeController();
    }

    /**
     * Initialize controller - override in child controllers
     */
    protected function initializeController(): void
    {
        // Override in child controllers
    }

    /**
     * Render view with layout
     */
    protected function view(string $view, array $data = []): void
    {
        $this->data = array_merge($this->data, $data);
        
        // Add global view data
        $this->data['csrf_token'] = $this->security->generateCsrfToken();
        $this->data['app_name'] = config('app.name', 'CIS MVC Platform');
        $this->data['app_version'] = config('app.version', '2.0.0');
        
        $viewPath = $this->getViewPath($view);
        $layoutPath = $this->getLayoutPath($this->layout);
        
        if (!file_exists($viewPath)) {
            throw new Exception("View not found: {$view}");
        }
        
        // Start output buffering for view content
        ob_start();
        extract($this->data);
        include $viewPath;
        $content = ob_get_clean();
        
        // If layout exists, wrap content
        if (file_exists($layoutPath)) {
            $this->data['content'] = $content;
            extract($this->data);
            include $layoutPath;
        } else {
            echo $content;
        }
    }

    /**
     * Render partial view without layout
     */
    protected function partial(string $view, array $data = []): string
    {
        $viewPath = $this->getViewPath($view);
        
        if (!file_exists($viewPath)) {
            throw new Exception("Partial view not found: {$view}");
        }
        
        ob_start();
        extract(array_merge($this->data, $data));
        include $viewPath;
        return ob_get_clean();
    }

    /**
     * Return JSON response
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => $statusCode < 400,
            'request_id' => $this->generateRequestId(),
        ];
        
        if ($statusCode < 400) {
            $response['data'] = $data;
        } else {
            $response['error'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Return successful JSON response
     */
    protected function jsonSuccess(array $data = [], string $message = null): void
    {
        $response = $data;
        if ($message) {
            $response['message'] = $message;
        }
        $this->json($response, 200);
    }

    /**
     * Return error JSON response
     */
    protected function jsonError(string $message, string $code = 'ERROR', int $statusCode = 400, array $details = []): void
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        
        if (!empty($details)) {
            $error['details'] = $details;
        }
        
        $this->json($error, $statusCode);
    }

    /**
     * Redirect to URL
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect back to previous page
     */
    protected function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    /**
     * Set flash message
     */
    protected function flash(string $type, string $message): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Get flash messages
     */
    protected function getFlash(): array
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        
        return $flash;
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrf(): bool
    {
        return $this->security->validateCsrfToken($_POST['_token'] ?? '');
    }

    /**
     * Require CSRF token
     */
    protected function requireCsrf(): void
    {
        if (!$this->validateCsrf()) {
            $this->jsonError('Invalid CSRF token', 'CSRF_TOKEN_INVALID', 403);
        }
    }

    /**
     * Get request input
     */
    protected function input(string $key = null, $default = null)
    {
        $input = array_merge($_GET, $_POST);
        
        if ($key === null) {
            return $input;
        }
        
        return $input[$key] ?? $default;
    }

    /**
     * Validate input
     */
    protected function validate(array $rules): array
    {
        $input = $this->input();
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? null;
            $ruleList = is_string($rule) ? explode('|', $rule) : $rule;
            
            foreach ($ruleList as $singleRule) {
                if ($singleRule === 'required' && empty($value)) {
                    $errors[$field][] = "The {$field} field is required.";
                } elseif ($singleRule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} field must be a valid email.";
                } elseif (str_starts_with($singleRule, 'min:')) {
                    $min = (int)substr($singleRule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "The {$field} field must be at least {$min} characters.";
                    }
                } elseif (str_starts_with($singleRule, 'max:')) {
                    $max = (int)substr($singleRule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "The {$field} field must not exceed {$max} characters.";
                    }
                }
            }
        }
        
        if (!empty($errors)) {
            $this->jsonError('Validation failed', 'VALIDATION_ERROR', 422, $errors);
        }
        
        return $input;
    }

    /**
     * Get view file path
     */
    private function getViewPath(string $view): string
    {
        $viewFile = str_replace('.', '/', $view) . '.php';
        return config('paths.views') . '/' . $viewFile;
    }

    /**
     * Get layout file path
     */
    private function getLayoutPath(string $layout): string
    {
        return config('paths.views') . '/layouts/' . $layout . '.php';
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * Get configuration value
     */
    protected function config(string $key, $default = null)
    {
        return config($key, $default);
    }
}
