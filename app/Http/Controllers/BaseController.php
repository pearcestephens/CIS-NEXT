<?php
declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * Base Controller
 * Provides common functionality for all controllers
 */
abstract class BaseController
{
    protected function view(string $template, array $data = []): array
    {
        return [
            'view' => $this->render($template, $data)
        ];
    }
    
    protected function json(array $data, int $statusCode = 200): array
    {
        http_response_code($statusCode);
        
        return [
            'json' => [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'data' => $statusCode >= 200 && $statusCode < 300 ? $data : null,
                'error' => $statusCode >= 400 ? $data : null,
                'meta' => [
                    'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? 'unknown',
                    'timestamp' => date('c'),
                ],
            ]
        ];
    }
    
    protected function redirect(string $url): array
    {
        return ['redirect' => $url];
    }
    
    protected function render(string $template, array $data = []): string
    {
        $templatePath = __DIR__ . '/../Views/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        
        // Extract data to variables
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include template
        include $templatePath;
        
        // Return buffered content
        return ob_get_clean();
    }
    
    protected function getCurrentUser(): ?array
    {
        return $_REQUEST['_user'] ?? null;
    }
    
    protected function requireUser(): array
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            throw new \RuntimeException('User authentication required');
        }
        
        return $user;
    }
}
