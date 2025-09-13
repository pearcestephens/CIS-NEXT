<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

/**
 * Body Parser Middleware
 * Parses request body for different content types
 */
class BodyParser
{
    public function handle(array $request, callable $next): mixed
    {
        $method = $request['REQUEST_METHOD'] ?? 'GET';
        
        // Only parse body for requests that can have one
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }
        
        $contentType = $request['CONTENT_TYPE'] ?? '';
        
        // Parse JSON requests
        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input');
            if ($input) {
                $data = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $_POST = array_merge($_POST, $data);
                }
            }
        }
        
        // Parse XML requests (if needed)
        if (str_contains($contentType, 'application/xml') || str_contains($contentType, 'text/xml')) {
            $input = file_get_contents('php://input');
            if ($input) {
                // Simple XML parsing - extend as needed
                $xml = simplexml_load_string($input);
                if ($xml) {
                    $_POST['_xml'] = $xml;
                }
            }
        }
        
        return $next($request);
    }
}
