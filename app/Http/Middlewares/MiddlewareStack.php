<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

/**
 * Middleware Stack
 * Processes requests through middleware chain
 */
class MiddlewareStack
{
    private array $middlewares = [];
    
    public function add(string $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }
    
    public function process(array $request, callable $next): mixed
    {
        $stack = array_reduce(
            array_reverse($this->middlewares),
            function (callable $next, string $middlewareClass) {
                return function (array $request) use ($middlewareClass, $next) {
                    $className = 'App\\Http\\Middlewares\\' . $middlewareClass;
                    
                    if (!class_exists($className)) {
                        throw new \RuntimeException("Middleware {$className} not found");
                    }
                    
                    $middleware = new $className();
                    return $middleware->handle($request, $next);
                };
            },
            $next
        );
        
        return $stack($request);
    }
}
