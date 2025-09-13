<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Simple Router for CIS 2.0
 * 
 * Lightweight, reliable routing without complex dependencies
 * Author: GitHub Copilot
 * Created: 2025-09-13
 */
class SimpleRouter
{
    private array $routes = [];
    private array $namedRoutes = [];
    
    /**
     * Add GET route
     */
    public function get(string $path, callable|string $handler, ?string $name = null): self
    {
        return $this->addRoute('GET', $path, $handler, $name);
    }
    
    /**
     * Add POST route
     */
    public function post(string $path, callable|string $handler, ?string $name = null): self
    {
        return $this->addRoute('POST', $path, $handler, $name);
    }
    
    /**
     * Add route for any HTTP method
     */
    public function any(string $path, callable|string $handler, ?string $name = null): self
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $name);
        }
        return $this;
    }
    
    /**
     * Add route with middleware group (simplified)
     */
    public function group(array $attributes, callable $callback): void
    {
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];
        
        // For now, we'll ignore middleware in this simple implementation
        // and just handle the prefix
        $originalPrefix = $this->currentPrefix ?? '';
        $this->currentPrefix = $originalPrefix . $prefix;
        
        call_user_func($callback, $this);
        
        $this->currentPrefix = $originalPrefix;
    }
    
    /**
     * Dispatch request to appropriate handler
     */
    public function dispatch(string $method, string $uri): void
    {
        // Clean the URI
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        $uri = rtrim($uri, '/') ?: '/';
        
        // Find matching route
        $routeKey = $method . ':' . $uri;
        
        if (isset($this->routes[$routeKey])) {
            $handler = $this->routes[$routeKey];
            $this->executeHandler($handler);
            return;
        }
        
        // Check for pattern matches (simple parameter support)
        foreach ($this->routes as $pattern => $handler) {
            if (strpos($pattern, ':') === false) {
                continue; // Skip non-parameterized routes
            }
            
            [$routeMethod, $routePath] = explode(':', $pattern, 2);
            
            if ($routeMethod !== $method) {
                continue;
            }
            
            if ($this->matchesPattern($routePath, $uri)) {
                $this->executeHandler($handler);
                return;
            }
        }
        
        // Route not found
        $this->handleNotFound();
    }
    
    /**
     * Add route to collection
     */
    private function addRoute(string $method, string $path, callable|string $handler, ?string $name = null): self
    {
        $prefix = $this->currentPrefix ?? '';
        $fullPath = $prefix . $path;
        $fullPath = rtrim($fullPath, '/') ?: '/';
        
        $routeKey = $method . ':' . $fullPath;
        $this->routes[$routeKey] = $handler;
        
        if ($name !== null) {
            $this->namedRoutes[$name] = $fullPath;
        }
        
        return $this;
    }
    
    /**
     * Execute route handler
     */
    private function executeHandler(callable|string $handler): void
    {
        try {
            if (is_callable($handler)) {
                // Execute closure
                $result = call_user_func($handler);
            } else {
                // Execute controller method
                $result = $this->executeController($handler);
            }
            
            // Handle different return types
            if (is_string($result)) {
                echo $result;
            } elseif (is_array($result)) {
                header('Content-Type: application/json');
                echo json_encode($result);
            }
            
        } catch (\Exception $e) {
            error_log("Route handler error: " . $e->getMessage());
            $this->handleError($e);
        }
    }
    
    /**
     * Execute controller method
     */
    private function executeController(string $handler): mixed
    {
        if (strpos($handler, '@') === false) {
            throw new \Exception("Invalid controller format: {$handler}");
        }
        
        [$controllerClass, $method] = explode('@', $handler);
        
        // Add namespace if not present
        if (strpos($controllerClass, '\\') === false) {
            $controllerClass = 'App\\Http\\Controllers\\' . $controllerClass;
        } else {
            // If it already has namespace parts, add the full namespace prefix
            $controllerClass = 'App\\Http\\Controllers\\' . $controllerClass;
        }
        
        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller not found: {$controllerClass}");
        }
        
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $method)) {
            throw new \Exception("Method not found: {$controllerClass}@{$method}");
        }
        
        return call_user_func([$controller, $method]);
    }
    
    /**
     * Simple pattern matching for routes with parameters
     */
    private function matchesPattern(string $pattern, string $uri): bool
    {
        // Very basic pattern matching - can be enhanced
        $patternParts = explode('/', trim($pattern, '/'));
        $uriParts = explode('/', trim($uri, '/'));
        
        if (count($patternParts) !== count($uriParts)) {
            return false;
        }
        
        for ($i = 0; $i < count($patternParts); $i++) {
            $patternPart = $patternParts[$i];
            $uriPart = $uriParts[$i];
            
            // If pattern part starts with {, it's a parameter
            if (strpos($patternPart, '{') === 0) {
                continue; // Parameter matches anything
            }
            
            if ($patternPart !== $uriPart) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Route not found'
            ],
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Handle route execution errors
     */
    private function handleError(\Exception $e): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'HANDLER_ERROR',
                'message' => 'Route handler failed'
            ],
            'timestamp' => date('c')
        ]);
    }
    
    private ?string $currentPrefix = null;
}
