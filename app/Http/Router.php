<?php
declare(strict_types=1);

namespace App\Http;

use App\Shared\Logging\Logger;

/**
 * HTTP Router
 * Handles route registration and request dispatching
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private array $namedRoutes = [];
    
    public function get(string $path, callable|string $handler, ?string $name = null): self
    {
        return $this->addRoute('GET', $path, $handler, $name);
    }
    
    public function post(string $path, callable|string $handler, ?string $name = null): self
    {
        return $this->addRoute('POST', $path, $handler, $name);
    }
    
    public function put(string $path, callable|string $handler, ?string $name = null): self
    {
        return $this->addRoute('PUT', $path, $handler, $name);
    }
    
    public function patch(string $path, callable|string $handler, ?string $name = null): self
    {
        return $this->addRoute('PATCH', $path, $handler, $name);
    }
    
    public function delete(string $path, callable|string $handler, ?string $name = null): self
    {
        return $this->addRoute('DELETE', $path, $handler, $name);
    }
    
    public function any(string $path, callable|string $handler, ?string $name = null): self
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $name);
        }
        return $this;
    }
    
    private function addRoute(string $method, string $path, callable|string $handler, ?string $name = null): self
    {
        $path = $this->normalizePath($path);
        $route = [
            'method' => $method,
            'path' => $path,
            'pattern' => $this->pathToPattern($path),
            'handler' => $handler,
            'middlewares' => [],
            'name' => $name,
        ];
        
        $this->routes[] = $route;
        
        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }
        
        return $this;
    }
    
    public function middleware(array|string $middlewares): self
    {
        $middlewares = is_array($middlewares) ? $middlewares : [$middlewares];
        
        if (!empty($this->routes)) {
            $lastRoute = &$this->routes[count($this->routes) - 1];
            $lastRoute['middlewares'] = array_merge($lastRoute['middlewares'], $middlewares);
        }
        
        return $this;
    }
    
    public function group(array $attributes, callable $callback): void
    {
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];
        $name = $attributes['name'] ?? '';
        
        $originalRoutes = $this->routes;
        
        $callback($this);
        
        // Apply group attributes to new routes
        $newRoutes = array_slice($this->routes, count($originalRoutes));
        foreach ($newRoutes as &$route) {
            if ($prefix) {
                $route['path'] = $this->normalizePath($prefix . $route['path']);
                $route['pattern'] = $this->pathToPattern($route['path']);
            }
            
            if ($middleware) {
                $route['middlewares'] = array_merge($middleware, $route['middlewares']);
            }
            
            if ($name && $route['name']) {
                $route['name'] = $name . '.' . $route['name'];
                if (isset($this->namedRoutes[$route['name']])) {
                    unset($this->namedRoutes[$route['name']]);
                }
                $this->namedRoutes[$route['name']] = $route;
            }
        }
        
        // Replace routes with updated versions
        $this->routes = array_merge($originalRoutes, $newRoutes);
    }
    
    public function dispatch($request, array $globalMiddleware = []): mixed
    {
        $method = $request->method ?? 'GET';
        $path = $this->normalizePath($request->uri ?? '/');
        
        // Remove query string from path
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        $logger = Logger::getInstance();
        $logger->debug('Route dispatch', [
            'component' => 'router',
            'action' => 'dispatch_start',
            'method' => $method,
            'path' => $path,
            'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
        ]);
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $path, $matches)) {
                // Extract path parameters
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_numeric($key)) {
                        $params[$key] = $value;
                    }
                }
                
                $logger->info('Route matched', [
                    'component' => 'router',
                    'action' => 'route_matched',
                    'route' => $route['path'],
                    'handler' => is_string($route['handler']) ? $route['handler'] : 'callable',
                    'params' => $params,
                    'middlewares' => array_merge($globalMiddleware, $route['middlewares']),
                    'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
                ]);
                
                // Execute middleware pipeline then handler
                return $this->executeWithMiddleware(
                    $route, 
                    $params, 
                    $request,
                    array_merge($globalMiddleware, $route['middlewares'])
                );
            }
        }
        
        // No route found
        $logger->warning('Route not found', [
            'component' => 'router', 
            'action' => 'route_not_found',
            'method' => $method, 
            'path' => $path,
            'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
        ]);
        return $this->handleNotFound($request);
    }
    
    /**
     * Execute handler with middleware pipeline
     */
    private function executeWithMiddleware(array $route, array $params, $request, array $middlewares = []): mixed
    {
        $logger = Logger::getInstance();
        
        // Build request object with params
        $request->params = $params;
        
        // Execute "before" middleware
        foreach ($middlewares as $middlewareClass) {
            if (class_exists($middlewareClass)) {
                $middleware = new $middlewareClass($logger);
                if (method_exists($middleware, 'before')) {
                    $middleware->before($request);
                }
            }
        }
        
        try {
            // Execute the handler
            $response = $this->executeHandler($route['handler'], $params, $request);
            
            // Wrap response in standard format if not already wrapped
            if (!is_object($response) || !property_exists($response, 'statusCode')) {
                $responseObj = new \stdClass();
                $responseObj->statusCode = 200;
                $responseObj->body = $response;
                $responseObj->headers = [];
                $response = $responseObj;
            }
            
        } catch (\Throwable $e) {
            $logger->error('Handler execution failed', [
                'component' => 'router',
                'action' => 'handler_error', 
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
            ]);
            
            // Create error response
            $response = new \stdClass();
            $response->statusCode = 500;
            $response->body = [
                'success' => false,
                'error' => [
                    'code' => 'HANDLER_ERROR',
                    'message' => 'Internal server error occurred',
                    'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
                ]
            ];
            $response->headers = ['Content-Type' => 'application/json'];
        }
        
        // Execute "after" middleware in reverse order
        foreach (array_reverse($middlewares) as $middlewareClass) {
            if (class_exists($middlewareClass)) {
                $middleware = new $middlewareClass($logger);
                if (method_exists($middleware, 'after')) {
                    $middleware->after($request, $response);
                }
            }
        }
        
        return $response;
    }
    
    private function executeHandler(callable|string $handler, array $params, $request): mixed
    {
        if (is_string($handler)) {
            // Controller@method format
            if (strpos($handler, '@') !== false) {
                [$controllerClass, $method] = explode('@', $handler);
                $controllerClass = 'App\\Http\\Controllers\\' . $controllerClass;
                
                if (!class_exists($controllerClass)) {
                    throw new \RuntimeException("Controller {$controllerClass} not found");
                }
                
                $controller = new $controllerClass();
                
                if (!method_exists($controller, $method)) {
                    throw new \RuntimeException("Method {$method} not found in {$controllerClass}");
                }
                
                return $controller->{$method}($params, $request);
            }
        }
        
        if (is_callable($handler)) {
            return $handler($params, $request);
        }
        
        throw new \RuntimeException("Invalid handler type");
    }
    
    private function handleNotFound($request): object
    {
        $response = new \stdClass();
        $response->statusCode = 404;
        $response->headers = [];
        
        if (str_starts_with($request->uri ?? '', '/api/')) {
            $response->headers['Content-Type'] = 'application/json';
            $response->body = [
                'success' => false,
                'error' => [
                    'code' => 'ROUTE_NOT_FOUND',
                    'message' => 'The requested endpoint was not found',
                ],
                'meta' => [
                    'request_id' => $request->headers['X-Request-ID'] ?? 'unknown',
                ],
            ];
        } else {
            $response->headers['Content-Type'] = 'text/html';
            $response->body = $this->render404Page();
        }
        
        return $response;
    }
    
    private function render404Page(): string
    {
        ob_start();
        include __DIR__ . '/Views/errors/404.php';
        return ob_get_clean();
    }
    
    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        return '/' . $path;
    }
    
    private function pathToPattern(string $path): string
    {
        // Convert {param} to named capture groups
        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $path);
        
        // Escape forward slashes and add anchors
        $pattern = '#^' . str_replace('/', '\/', $pattern) . '$#';
        
        return $pattern;
    }
    
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Named route '{$name}' not found");
        }
        
        $route = $this->namedRoutes[$name];
        $path = $route['path'];
        
        // Replace path parameters
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }
        
        return $path;
    }
    
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
