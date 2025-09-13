<?php
declare(strict_types=1);

/**
 * RouteDiscovery.php - Static route parser for testing
 * 
 * Safely parses route files without executing PHP code to discover
 * all available routes for automated testing and documentation.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T14:45:00Z
 */

namespace App\Tools;

use App\Shared\Logging\Logger;

class RouteDiscovery
{
    private Logger $logger;
    private array $routes = [];
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Discover all routes from route files with static parsing
     */
    public function discoverRoutes(): array
    {
        $startTime = microtime(true);
        $this->routes = [];
        
        // Parse web routes with auth detection
        $webRoutes = $this->parseRouteFile(__DIR__ . '/../../routes/web.php');
        
        // Parse API routes with auth detection
        $apiRoutes = $this->parseRouteFile(__DIR__ . '/../../routes/api.php', '/api');
        
        $this->routes = array_merge($webRoutes, $apiRoutes);
        
        // Mark auth requirements based on patterns
        $this->detectAuthRequirements();
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info('Route discovery completed', [
            'component' => 'route_discovery',
            'action' => 'discovery_complete',
            'total_routes' => count($this->routes),
            'web_routes' => count($webRoutes),
            'api_routes' => count($apiRoutes),
            'duration_ms' => $duration
        ]);
        
        return $this->routes;
    }
    
    /**
     * Parse route file safely without execution
     */
    private function parseRouteFile(string $filename, string $prefix = ''): array
    {
        if (!file_exists($filename)) {
            return [];
        }
        
        $content = file_get_contents($filename);
        $routes = [];
        
        // Parse GET routes
        $routes = array_merge($routes, $this->extractRoutes($content, 'get', $prefix));
        
        // Parse POST routes
        $routes = array_merge($routes, $this->extractRoutes($content, 'post', $prefix));
        
        // Parse PUT routes
        $routes = array_merge($routes, $this->extractRoutes($content, 'put', $prefix));
        
        // Parse DELETE routes
        $routes = array_merge($routes, $this->extractRoutes($content, 'delete', $prefix));
        
        // Parse PATCH routes
        $routes = array_merge($routes, $this->extractRoutes($content, 'patch', $prefix));
        
        // Parse ANY routes (multiple methods)
        $anyRoutes = $this->extractRoutes($content, 'any', $prefix);
        foreach ($anyRoutes as $route) {
            foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
                $routes[] = array_merge($route, ['method' => $method]);
            }
        }
        
        // Parse grouped routes
        $routes = array_merge($routes, $this->extractGroupedRoutes($content, $prefix));
        
        return $routes;
    }
    
    /**
     * Detect authentication requirements for all routes
     */
    private function detectAuthRequirements(): void
    {
        foreach ($this->routes as &$route) {
            $route['auth_required'] = $this->requiresAuth($route['path'], $route['handler']);
        }
    }

    /**
     * Extract routes for specific HTTP method
     */
    private function extractRoutes(string $content, string $method, string $prefix = ''): array
    {
        $routes = [];
        $pattern = '/\$router->' . preg_quote($method) . '\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]?([^,\)]+)[\'"]?\s*(?:,\s*[\'"]([^\'"]*)[\'"])?\s*\)/';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $path = $prefix . $match[1];
                $handler = $match[2];
                $name = $match[3] ?? null;
                
                $routes[] = [
                    'method' => strtoupper($method),
                    'path' => $path,
                    'handler' => trim($handler, '"\''),
                    'name' => $name ? trim($name, '"\'') : null,
                    'area' => $this->determineArea($path),
                    'auth_required' => false, // Will be set by detectAuthRequirements()
                    'middleware' => $this->extractMiddleware($content, $match[0])
                ];
            }
        }
        
        return $routes;
    }
    
    /**
     * Extract grouped routes with prefixes and middleware
     */
    private function extractGroupedRoutes(string $content, string $globalPrefix = ''): array
    {
        $routes = [];
        $pattern = '/\$router->group\s*\(\s*\[([^\]]+)\]\s*,\s*function\s*\([^)]*\)\s*\{([^}]+)\}/s';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes = $this->parseGroupAttributes($match[1]);
                $groupContent = $match[2];
                
                $groupPrefix = $globalPrefix . ($attributes['prefix'] ?? '');
                $groupMiddleware = $attributes['middleware'] ?? [];
                
                // Parse routes within group
                $groupRoutes = $this->parseRouteFile('', $groupPrefix);
                
                // Apply group attributes to routes
                foreach ($groupRoutes as &$route) {
                    $route['middleware'] = array_merge($route['middleware'], $groupMiddleware);
                    $route['group'] = $attributes;
                }
                
                $routes = array_merge($routes, $groupRoutes);
            }
        }
        
        return $routes;
    }
    
    /**
     * Parse group attributes string
     */
    private function parseGroupAttributes(string $attributesString): array
    {
        $attributes = [];
        
        // Extract prefix
        if (preg_match('/[\'"]prefix[\'"]\\s*=>\\s*[\'"]([^\'"]+)[\'"]/', $attributesString, $match)) {
            $attributes['prefix'] = $match[1];
        }
        
        // Extract middleware
        if (preg_match('/[\'"]middleware[\'"]\\s*=>\\s*\\[([^\\]]+)\\]/', $attributesString, $match)) {
            $middlewareString = $match[1];
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $middlewareString, $middlewareMatches);
            $attributes['middleware'] = $middlewareMatches[1];
        }
        
        return $attributes;
    }
    
    /**
     * Extract middleware from route context
     */
    private function extractMiddleware(string $content, string $routeMatch): array
    {
        // Look for ->middleware() calls after the route
        $pattern = '/\\' . preg_quote($routeMatch) . '\\s*->middleware\\s*\\(\\s*\\[([^\\]]+)\\]\\s*\\)/';
        
        if (preg_match($pattern, $content, $match)) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $match[1], $middlewareMatches);
            return $middlewareMatches[1];
        }
        
        return [];
    }
    
    /**
     * Determine route area (public, auth, admin, api)
     */
    private function determineArea(string $path): string
    {
        if (str_starts_with($path, '/api/')) {
            return 'api';
        }
        
        if (str_starts_with($path, '/admin/')) {
            return 'admin';
        }
        
        if (in_array($path, ['/', '/login', '/register', '/_health', '/_selftest'])) {
            return 'public';
        }
        
        return 'auth';
    }
    
    /**
     * Determine if route requires authentication
     */
    private function requiresAuth(string $path, string $handler): bool
    {
        // Public routes that don't require auth
        $publicRoutes = [
            '/',
            '/login', 
            '/register',
            '/_health',
            '/_selftest',
            '/_ready'
        ];
        
        if (in_array($path, $publicRoutes)) {
            return false;
        }
        
        // API health endpoints
        if (str_starts_with($path, '/api/') && str_contains($path, 'health')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get routes filtered by criteria
     */
    public function getRoutesByArea(string $area): array
    {
        return array_filter($this->routes, fn($route) => $route['area'] === $area);
    }
    
    /**
     * Get routes by HTTP method
     */
    public function getRoutesByMethod(string $method): array
    {
        return array_filter($this->routes, fn($route) => $route['method'] === strtoupper($method));
    }
    
    /**
     * Get routes requiring authentication
     */
    public function getProtectedRoutes(): array
    {
        return array_filter($this->routes, fn($route) => $route['auth_required']);
    }
    
    /**
     * Get public routes
     */
    public function getPublicRoutes(): array
    {
        return array_filter($this->routes, fn($route) => !$route['auth_required']);
    }
    
    /**
     * Export routes as structured data
     */
    public function exportRoutes(): array
    {
        return [
            'total' => count($this->routes),
            'by_method' => [
                'GET' => count($this->getRoutesByMethod('GET')),
                'POST' => count($this->getRoutesByMethod('POST')),
                'PUT' => count($this->getRoutesByMethod('PUT')),
                'DELETE' => count($this->getRoutesByMethod('DELETE')),
                'PATCH' => count($this->getRoutesByMethod('PATCH'))
            ],
            'by_area' => [
                'public' => count($this->getRoutesByArea('public')),
                'auth' => count($this->getRoutesByArea('auth')),
                'admin' => count($this->getRoutesByArea('admin')),
                'api' => count($this->getRoutesByArea('api'))
            ],
            'auth_required' => count($this->getProtectedRoutes()),
            'public_access' => count($this->getPublicRoutes()),
            'routes' => $this->routes
        ];
    }
}
