<?php
declare(strict_types=1);

/**
 * Base Middleware Interface
 * Defines contract for all middleware components
 * 
 * @package CIS\Http\Middlewares
 * @version 2.0.0
 */

namespace App\Http\Middlewares;

interface MiddlewareInterface
{
    /**
     * Handle the request through middleware
     * 
     * @param array $request The request data
     * @param callable $next Next middleware in chain
     * @return mixed Response or pass to next
     */
    public function handle(array $request, callable $next): mixed;
}
