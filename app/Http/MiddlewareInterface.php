<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Middleware Interface
 * 
 * Defines the contract for all HTTP middleware
 */
interface MiddlewareInterface
{
    /**
     * Handle the request through middleware
     *
     * @param array $request Request data
     * @param callable $next Next middleware in chain
     * @return mixed
     */
    public function handle(array $request, callable $next): mixed;
}
