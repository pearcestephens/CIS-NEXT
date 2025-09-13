<?php
declare(strict_types=1);

/**
 * Enhanced BrowserlessTestClient.php - HTTP client for automated testing
 * 
 * Provides comprehensive HTTP testing capabilities including cookie management,
 * CSRF token discovery, timing metrics, redirect following, and security validation.
 * 
 * @author CIS V2 System
 * @version 2.1.0-alpha.1
 * @last_modified 2025-09-11T14:45:00Z
 */

namespace App\Tools;

use App\Shared\Logging\Logger;

class BrowserlessTestClient
{
    private Logger $logger;
    private array $cookies = [];
    private string $userAgent;
    private int $timeout;
    private array $defaultHeaders;
    private ?string $csrfToken = null;
    private bool $followRedirects = true;
    private int $maxRedirects = 5;
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->userAgent = 'CIS-TestClient/2.0 (+https://staff.vapeshed.co.nz)';
        $this->timeout = 30;
        $this->defaultHeaders = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1'
        ];
    }
    
    /**
     * Perform GET request with full timing metrics
     */
    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }
    
    /**
     * Perform POST request with form data
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return $this->request('POST', $url, http_build_query($data), $headers);
    }
    
    /**
     * Perform POST request with JSON data
     */
    public function postJson(string $url, array $data = [], array $headers = []): array
    {
        $headers['Content-Type'] = 'application/json';
        return $this->request('POST', $url, json_encode($data), $headers);
    }
    
    /**
     * Login with credentials and persist session
     */
    public function login(string $email, string $password, string $loginUrl = '/login'): array
    {
        // First, get the login page to extract CSRF token
        $loginPage = $this->get($loginUrl);
        
        if ($loginPage['status_code'] !== 200) {
            return [
                'success' => false,
                'error' => 'Failed to load login page',
                'response' => $loginPage
            ];
        }
        
        // Extract CSRF token
        $csrfToken = $this->extractCSRFToken($loginPage['body']);
        
        if (!$csrfToken) {
            return [
                'success' => false,
                'error' => 'CSRF token not found on login page'
            ];
        }
        
        $this->csrfToken = $csrfToken;
        
        // Perform login
        $loginData = [
            'email' => $email,
            'password' => $password,
            'csrf_token' => $csrfToken
        ];
        
        $response = $this->post($loginUrl, $loginData);
        
        // Check for successful login (redirect or success response)
        $success = in_array($response['status_code'], [200, 302]) && 
                   !str_contains($response['body'], 'Invalid email or password');
        
        $this->logger->info('Login attempt completed', [
            'component' => 'test_client',
            'action' => 'login_attempt',
            'email' => $email,
            'success' => $success,
            'status_code' => $response['status_code'],
            'has_session_cookie' => $this->hasSessionCookie()
        ]);
        
        return [
            'success' => $success,
            'response' => $response,
            'csrf_token' => $csrfToken
        ];
    }
    
    /**
     * Perform HTTP request with comprehensive metrics
     */
    private function request(string $method, string $url, ?string $body = null, array $headers = []): array
    {
        $startTime = microtime(true);
        $ch = curl_init();
        
        // Merge headers
        $allHeaders = array_merge($this->defaultHeaders, $headers);
        $allHeaders['User-Agent'] = $this->userAgent;
        
        // Add cookies
        if (!empty($this->cookies)) {
            $allHeaders['Cookie'] = $this->buildCookieHeader();
        }
        
        // Convert headers to cURL format
        $curlHeaders = [];
        foreach ($allHeaders as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $this->followRedirects,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_COOKIEJAR => '', // Enable cookie handling
            CURLOPT_COOKIEFILE => '' // Enable cookie handling
        ]);
        
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        $totalTime = microtime(true) - $startTime;
        
        if ($response === false) {
            return [
                'success' => false,
                'error' => $error,
                'timing' => ['total' => $totalTime]
            ];
        }
        
        // Parse response
        $headerSize = $info['header_size'];
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        // Extract cookies from response
        $this->extractCookies($headers);
        
        // Parse timing information
        $timing = [
            'namelookup' => $info['namelookup_time'],
            'connect' => $info['connect_time'], 
            'appconnect' => $info['appconnect_time'],
            'pretransfer' => $info['pretransfer_time'],
            'starttransfer' => $info['starttransfer_time'],
            'total' => $info['total_time'],
            'redirect' => $info['redirect_time'],
            'size_download' => $info['size_download']
        ];
        
        return [
            'success' => true,
            'status_code' => $info['response_code'],
            'headers' => $this->parseHeaders($headers),
            'body' => $body,
            'timing' => $timing,
            'info' => $info
        ];
    }
    
    /**
     * Extract CSRF token from HTML response
     */
    public function extractCSRFToken(string $html): ?string
    {
        // Try multiple patterns for CSRF token discovery
        $patterns = [
            '/<input[^>]*name=[\'"]csrf_token[\'"][^>]*value=[\'"]([^\'"]+)[\'"][^>]*>/i',
            '/<input[^>]*value=[\'"]([^\'"]+)[\'"][^>]*name=[\'"]csrf_token[\'"][^>]*>/i',
            '/<meta[^>]*name=[\'"]csrf-token[\'"][^>]*content=[\'"]([^\'"]+)[\'"][^>]*>/i',
            '/<meta[^>]*content=[\'"]([^\'"]+)[\'"][^>]*name=[\'"]csrf-token[\'"][^>]*>/i',
            '/window\.csrfToken\s*=\s*[\'"]([^\'"]+)[\'"]/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $this->csrfToken = $matches[1];
                return $this->csrfToken;
            }
        }
        
        return null;
    }
    
    /**
     * DOM assertion using DOMDocument and XPath
     */
    public function assertElementExists(string $html, string $selector): bool
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        
        // Convert CSS selector to XPath (basic support)
        $xpathQuery = $this->cssToXPath($selector);
        $elements = $xpath->query($xpathQuery);
        
        return $elements->length > 0;
    }
    
    /**
     * Assert JSON response structure
     */
    public function assertJsonStructure(string $json, array $expectedKeys): bool
    {
        $data = json_decode($json, true);
        
        if ($data === null) {
            return false;
        }
        
        return $this->validateJsonStructure($data, $expectedKeys);
    }
    
    /**
     * Get current cookies as array
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }
    
    /**
     * Set cookies from array
     */
    public function setCookies(array $cookies): void
    {
        $this->cookies = $cookies;
    }
    
    /**
     * Check if session cookie exists
     */
    private function hasSessionCookie(): bool
    {
        return isset($this->cookies['PHPSESSID']) || isset($this->cookies['cis_session']);
    }
    
    /**
     * Extract CSRF token from HTML
     */
    public function extractCSRFToken(string $html): ?string
    {
        // Try meta tag first
        if (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }
        
        // Try input field
        if (preg_match('/<input[^>]+name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }
        
        // Try input field (alternative name)
        if (preg_match('/<input[^>]+name=["\']_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get cookie flags for security validation
     */
    public function getCookieFlags(string $cookieName): array
    {
        if (!isset($this->cookies[$cookieName])) {
            return [];
        }
        
        $cookie = $this->cookies[$cookieName];
        return [
            'httponly' => $cookie['httponly'] ?? false,
            'secure' => $cookie['secure'] ?? false,
            'samesite' => $cookie['samesite'] ?? 'none'
        ];
    }

    /**
     * Extract cookies from response headers
     */
    private function extractCookies(string $headers): void
    {
        preg_match_all('/Set-Cookie:\s*([^;]+)/i', $headers, $matches);
        
        foreach ($matches[1] as $cookie) {
            if (str_contains($cookie, '=')) {
                [$name, $value] = explode('=', $cookie, 2);
                $this->cookies[trim($name)] = trim($value);
            }
        }
    }
    
    /**
     * Build cookie header string
     */
    private function buildCookieHeader(): string
    {
        $cookies = [];
        foreach ($this->cookies as $name => $value) {
            $cookies[] = "{$name}={$value}";
        }
        return implode('; ', $cookies);
    }
    
    /**
     * Parse response headers into array
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);
        
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        
        return $headers;
    }
    
    /**
     * Convert basic CSS selector to XPath
     */
    private function cssToXPath(string $selector): string
    {
        // Basic CSS to XPath conversion
        $selector = trim($selector);
        
        // ID selector
        if (str_starts_with($selector, '#')) {
            return "//*[@id='" . substr($selector, 1) . "']";
        }
        
        // Class selector
        if (str_starts_with($selector, '.')) {
            return "//*[contains(@class, '" . substr($selector, 1) . "')]";
        }
        
        // Element selector
        return "//" . $selector;
    }
    
    /**
     * Validate JSON structure recursively
     */
    private function validateJsonStructure(array $data, array $expectedKeys): bool
    {
        foreach ($expectedKeys as $key => $value) {
            if (is_string($key)) {
                // Nested structure
                if (!isset($data[$key])) {
                    return false;
                }
                
                if (is_array($value) && !$this->validateJsonStructure($data[$key], $value)) {
                    return false;
                }
            } else {
                // Simple key check
                if (!isset($data[$value])) {
                    return false;
                }
            }
        }
        
        return true;
    }
}
