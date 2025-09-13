<?php
/**
 * Router Class
 * 
 * Core routing system for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

namespace App\Core;

use Exception;
use ReflectionClass;
use ReflectionMethod;

class Router
{
    private array $routes = [];
    private array $middlewareGroups = [];
    private array $currentGroup = [];
    private string $currentPrefix = '';
    private array $currentMiddleware = [];

    /**
     * Register GET route
     */
    public function get(string $uri, $action, string $name = null): void
    {
        $this->addRoute('GET', $uri, $action, $name);
    }

    /**
     * Register POST route
     */
    public function post(string $uri, $action, string $name = null): void
    {
        $this->addRoute('POST', $uri, $action, $name);
    }

    /**
     * Register PUT route
     */
    public function put(string $uri, $action, string $name = null): void
    {
        $this->addRoute('PUT', $uri, $action, $name);
    }

    /**
     * Register DELETE route
     */
    public function delete(string $uri, $action, string $name = null): void
    {
        $this->addRoute('DELETE', $uri, $action, $name);
    }

    /**
     * Register PATCH route
     */
    public function patch(string $uri, $action, string $name = null): void
    {
        $this->addRoute('PATCH', $uri, $action, $name);
    }

    /**
     * Register route for any HTTP method
     */
    public function any(string $uri, $action, string $name = null): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        foreach ($methods as $method) {
            $this->addRoute($method, $uri, $action, $name);
        }
    }

    /**
     * Group routes with common attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousGroup = $this->currentGroup;
        $previousPrefix = $this->currentPrefix;
        $previousMiddleware = $this->currentMiddleware;

        // Apply group attributes
        if (isset($attributes['prefix'])) {
            $this->currentPrefix = trim($previousPrefix . '/' . trim($attributes['prefix'], '/'), '/');
        }

        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
            $this->currentMiddleware = array_merge($previousMiddleware, $middleware);
        }

        $this->currentGroup = array_merge($previousGroup, $attributes);

        // Execute callback
        $callback($this);

        // Restore previous state
        $this->currentGroup = $previousGroup;
        $this->currentPrefix = $previousPrefix;
        $this->currentMiddleware = $previousMiddleware;
    }

    /**
     * Add route to collection
     */
    private function addRoute(string $method, string $uri, $action, string $name = null): void
    {
        // Apply current prefix
        if ($this->currentPrefix) {
            $uri = '/' . trim($this->currentPrefix . '/' . trim($uri, '/'), '/');
        }

        // Normalize URI
        $uri = $uri === '' ? '/' : $uri;
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        $route = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'name' => $name,
            'middleware' => $this->currentMiddleware,
            'parameters' => $this->extractParameters($uri),
        ];

        $this->routes[] = $route;
    }

    /**
     * Extract parameters from URI pattern
     */
    private function extractParameters(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Resolve current request
     */
    public function resolve(): mixed
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Normalize URI
        $uri = $uri === '' ? '/' : $uri;
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->buildPattern($route['uri']);
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match
                
                // Extract parameter values
                $parameters = [];
                foreach ($route['parameters'] as $index => $param) {
                    $parameters[$param] = $matches[$index] ?? null;
                }

                return $this->dispatch($route, $parameters);
            }
        }

        // No route found
        $this->handleNotFound();
        return null;
    }

    /**
     * Build regex pattern from URI
     */
    private function buildPattern(string $uri): string
    {
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    /**
     * Dispatch route action
     */
    private function dispatch(array $route, array $parameters): mixed
    {
        try {
            // Run middleware
            foreach ($route['middleware'] as $middleware) {
                $this->runMiddleware($middleware);
            }

            $action = $route['action'];

            // Handle closure
            if (is_callable($action)) {
                return call_user_func_array($action, array_values($parameters));
            }

            // Handle Controller@method syntax
            if (is_string($action) && str_contains($action, '@')) {
                [$controller, $method] = explode('@', $action, 2);
                
                if (!class_exists($controller)) {
                    throw new Exception("Controller class not found: {$controller}");
                }

                $instance = new $controller();
                
                if (!method_exists($instance, $method)) {
                    throw new Exception("Method not found: {$controller}@{$method}");
                }

                $reflection = new ReflectionMethod($instance, $method);
                $methodParams = $reflection->getParameters();
                $args = [];

                foreach ($methodParams as $param) {
                    $name = $param->getName();
                    if (isset($parameters[$name])) {
                        $args[] = $parameters[$name];
                    } elseif ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        $args[] = null;
                    }
                }

                return call_user_func_array([$instance, $method], $args);
            }

            throw new Exception("Invalid route action");
            
        } catch (Exception $e) {
            $this->handleError($e);
            return null;
        }
    }

    /**
     * Run middleware
     */
    private function runMiddleware(string $middleware): void
    {
        // Basic middleware implementation
        switch ($middleware) {
            case 'auth':
                if (!isset($_SESSION['user_id'])) {
                    http_response_code(401);
                    header('Location: /login');
                    exit;
                }
                break;
                
            case 'admin':
                if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
                    http_response_code(403);
                    header('Location: /error/403');
                    exit;
                }
                break;
                
            case 'api':
                header('Content-Type: application/json');
                break;
        }
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'ROUTE_NOT_FOUND',
                    'message' => 'Route not found',
                ],
                'request_id' => $this->generateRequestId(),
            ]);
        } else {
            // Try to load 404 view
            $viewPath = __DIR__ . '/../Views/errors/404.php';
            if (file_exists($viewPath)) {
                include $viewPath;
            } else {
                echo '<h1>404 - Page Not Found</h1>';
            }
        }
        exit;
    }

    /**
     * Handle errors
     */
    private function handleError(Exception $e): void
    {
        http_response_code(500);
        
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => ($_ENV['APP_DEBUG'] ?? false) ? $e->getMessage() : 'Internal server error',
                ],
                'request_id' => $this->generateRequestId(),
            ]);
        } else {
            if ($_ENV['APP_DEBUG'] ?? false) {
                echo '<h1>Error</h1><pre>' . $e->getMessage() . "\n" . $e->getTraceAsString() . '</pre>';
            } else {
                echo '<h1>500 - Internal Server Error</h1>';
            }
        }
        exit;
    }

    /**
     * Check if this is an API request
     */
    private function isApiRequest(): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return str_starts_with($uri, '/api/') || 
               (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
