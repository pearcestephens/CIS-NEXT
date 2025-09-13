<?php
declare(strict_types=1);

/**
 * Global middleware pipeline consumed by the Router.
 * Keep this in the GLOBAL namespace.
 */
$globalMiddleware = [
    \App\Http\Middlewares\ErrorHandlerMiddleware::class,  // catch-all first
    \App\Http\Middlewares\SecurityHeaders::class,         // security headers
    \App\Http\Middlewares\RequestId::class,               // correlation IDs
    \App\Http\Middlewares\RateLimiterMiddleware::class,   // rate limiting
    \App\Http\Middlewares\CSRFMiddleware::class,          // CSRF
    \App\Http\Middlewares\ProfilerMiddleware::class,      // profiling
];
