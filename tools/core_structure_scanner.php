<?php
/**
 * Core Application Structure Scanner
 * File: tools/core_structure_scanner.php
 * 
 * Outputs clean application structure and contents for core files only.
 * Excludes development tools, logs, cache, and misc files.
 */

declare(strict_types=1);

// Configuration
$config = [
    'base_path' => '/var/www/cis.dev.ecigdis.co.nz/public_html',
    'output_dir' => '/var/www/cis.dev.ecigdis.co.nz/public_html/var/reports',
    'max_structure_size' => 100 * 1024, // 100KB limit for structure output
    
    // Scan ALL directories and files (complete document root scan)
    'scan_all_files' => true,
    'include_dirs' => [], // Will be ignored when scan_all_files is true
    'include_root_files' => [], // Will be ignored when scan_all_files is true
    
    // Extensions to include for content scanning
    'core_extensions' => ['php', 'html', 'css', 'js', 'md', 'sql'],
    
    // Directories to completely exclude (minimal exclusions for complete scan)
    'exclude_dirs' => [
        '.git/',
        'node_modules/',
        'vendor/'
    ],
    
    // File patterns to exclude
    'exclude_patterns' => [
        '/\.log$/',
        '/\.tmp$/',
        '/\.bak$/',
        '/_backup/',
        '/debug_/',
        '/test_/',
        '/temp_/'
    ]
];

class CoreStructureScanner
{
    private array $config;
    private string $scan_id;
    private array $structure = [];
    private int $structure_size = 0;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->scan_id = 'CORE_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 6);
        
        if (!is_dir($config['output_dir'])) {
            mkdir($config['output_dir'], 0755, true);
        }
    }
    
    /**
     * Scan and generate both structure and contents
     */
    public function scanAll(): array
    {
        echo "🔍 CORE APPLICATION STRUCTURE SCANNER\n";
        echo "Scan ID: {$this->scan_id}\n";
        echo "Base Path: {$this->config['base_path']}\n";
        echo "=" . str_repeat("=", 50) . "\n\n";
        
        $start_time = microtime(true);
        
        // Scan structure
        $this->scanStructure();
        
        // Generate structure report (minified)
        $structure_report = $this->generateStructureReport();
        
        // Generate contents report
        $contents_report = $this->generateContentsReport();
        
        $execution_time = round((microtime(true) - $start_time) * 1000);
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "✅ SCAN COMPLETE\n";
        echo "Structure Size: " . $this->formatBytes($this->structure_size) . "\n";
        echo "Files Scanned: " . $this->countFiles() . "\n";
        echo "Execution Time: {$execution_time}ms\n";
        
        return [
            'scan_id' => $this->scan_id,
            'structure_file' => $structure_report,
            'contents_file' => $contents_report,
            'execution_time_ms' => $execution_time
        ];
    }
    
    /**
     * Scan complete document root structure
     */
    private function scanStructure(): void
    {
        echo "📁 Scanning COMPLETE document root structure...\n";
        echo "   Including ALL files and folders with timestamps and sizes\n\n";
        
        $base_path = rtrim($this->config['base_path'], '/');
        
        // Scan everything in document root
        $this->scanDirectory($base_path, '', 0, true);
    }
    
    /**
     * Recursively scan directory
     */
    private function scanDirectory(string $full_path, string $relative_path, int $depth = 0): void
    {
        if ($depth > 8) return; // Prevent deep recursion
        
        $items = scandir($full_path);
        if (!$items) return;
        
        $relative_path = rtrim($relative_path, '/');
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $item_full_path = $full_path . '/' . $item;
            $item_relative_path = $relative_path . '/' . $item;
            
            // Check exclusions
            if ($this->shouldExclude($item_relative_path)) {
                continue;
            }
            
            if (is_dir($item_full_path)) {
                $this->addToStructure($item_relative_path . '/', 'dir', 0);
                echo str_repeat("  ", $depth + 2) . "📁 {$item}/\n";
                $this->scanDirectory($item_full_path, $item_relative_path, $depth + 1);
            } else {
                $size = filesize($item_full_path);
                $this->addToStructure($item_relative_path, 'file', $size);
                echo str_repeat("  ", $depth + 2) . "📄 {$item} (" . $this->formatBytes($size) . ")\n";
            }
        }
    }
    
    /**
     * Check if path should be excluded
     */
    private function shouldExclude(string $path): bool
    {
        // Check exclude directories
        foreach ($this->config['exclude_dirs'] as $exclude_dir) {
            if (strpos($path, $exclude_dir) === 0) {
                return true;
            }
        }
        
        // Check exclude patterns
        foreach ($this->config['exclude_patterns'] as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add item to structure
     */
    private function addToStructure(string $path, string $type, int $size): void
    {
        $entry = "{$path}";
        if ($type === 'file') {
            $entry .= " ({$size})";
        }
        $entry .= "\n";
        
        $this->structure[] = $entry;
        $this->structure_size += strlen($entry);
        
        // Only warn if approaching 100KB limit, but don't truncate
        if ($this->structure_size > $this->config['max_structure_size'] * 0.9) {
            // Just track but don't truncate - let it continue
        }
    }
    
    /**
     * Generate minified structure report
     */
    private function generateStructureReport(): string
    {
        $filename = "core_structure_{$this->scan_id}.txt";
        $filepath = $this->config['output_dir'] . '/' . $filename;
        
        $content = "CIS CORE APPLICATION STRUCTURE\n";
        $content .= "Scan ID: {$this->scan_id}\n";
        $content .= "Generated: " . date('Y-m-d H:i:s T') . "\n";
        $content .= str_repeat("=", 40) . "\n\n";
        
        $content .= implode('', $this->structure);
        
        // No truncation - allow full structure up to 100KB
        // Just add size info for reference
        if (strlen($content) > 50 * 1024) {
            $size_info = "\n[Structure size: " . $this->formatBytes(strlen($content)) . "]\n";
            $content .= $size_info;
        }
        
        file_put_contents($filepath, $content);
        
        echo "📄 Structure report: {$filename} (" . $this->formatBytes(strlen($content)) . ")\n";
        
        return $filename;
    }
    
    /**
     * Generate contents report
     */
    private function generateContentsReport(): string
    {
        echo "📖 Generating contents report...\n";
        
        $filename = "core_contents_{$this->scan_id}.txt";
        $filepath = $this->config['output_dir'] . '/' . $filename;
        
        $content = "CIS CORE APPLICATION CONTENTS\n";
        $content .= "Scan ID: {$this->scan_id}\n";
        $content .= "Generated: " . date('Y-m-d H:i:s T') . "\n";
        $content .= str_repeat("=", 50) . "\n\n";
        
        $base_path = rtrim($this->config['base_path'], '/');
        $files_processed = 0;
        
        // Process root files
        foreach ($this->config['include_root_files'] as $file) {
            $full_path = $base_path . '/' . $file;
            if (file_exists($full_path) && $this->isCoreFile($file)) {
                $content .= $this->getFileContent($full_path, $file);
                $files_processed++;
            }
        }
        
        // Process directory files
        foreach ($this->config['include_dirs'] as $dir) {
            $full_path = $base_path . '/' . rtrim($dir, '/');
            if (is_dir($full_path)) {
                $files_processed += $this->processDirectoryContents($full_path, $dir, $content);
            }
        }
        
        file_put_contents($filepath, $content);
        
        echo "📄 Contents report: {$filename} (" . $this->formatBytes(strlen($content)) . ")\n";
        echo "📄 Files processed: {$files_processed}\n";
        
        return $filename;
    }
    
    /**
     * Process directory contents recursively
     */
    private function processDirectoryContents(string $full_path, string $relative_path, string &$content, int $depth = 0): int
    {
        if ($depth > 6) return 0;
        
        $files_processed = 0;
        $items = scandir($full_path);
        if (!$items) return 0;
        
        $relative_path = rtrim($relative_path, '/');
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $item_full_path = $full_path . '/' . $item;
            $item_relative_path = $relative_path . '/' . $item;
            
            if ($this->shouldExclude($item_relative_path)) {
                continue;
            }
            
            if (is_dir($item_full_path)) {
                $files_processed += $this->processDirectoryContents($item_full_path, $item_relative_path, $content, $depth + 1);
            } elseif ($this->isCoreFile($item)) {
                $content .= $this->getFileContent($item_full_path, $item_relative_path);
                $files_processed++;
            }
        }
        
        return $files_processed;
    }
    
    /**
     * Check if file is a core application file
     */
    private function isCoreFile(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $this->config['core_extensions']);
    }
    
    /**
     * Get formatted file content
     */
    private function getFileContent(string $full_path, string $relative_path): string
    {
        $content = "\n" . str_repeat("=", 60) . "\n";
        $content .= "FILE: {$relative_path}\n";
        $content .= "SIZE: " . $this->formatBytes(filesize($full_path)) . "\n";
        $content .= str_repeat("-", 60) . "\n";
        
        $file_content = file_get_contents($full_path);
        if ($file_content !== false) {
            // Limit file content size to prevent huge outputs
            if (strlen($file_content) > 50000) {
                $file_content = substr($file_content, 0, 50000) . "\n... (truncated - file too large)\n";
            }
            $content .= $file_content;
        } else {
            $content .= "(Unable to read file content)\n";
        }
        
        $content .= "\n";
        
        return $content;
    }
    
    /**
     * Count total files in structure
     */
    private function countFiles(): int
    {
        $count = 0;
        foreach ($this->structure as $entry) {
            if (strpos($entry, '/') === false || strpos($entry, '(') !== false) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Format bytes for human reading
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB'];
        $factor = floor(log($bytes, 1024));
        return sprintf("%.1f %s", $bytes / (1024 ** $factor), $units[$factor] ?? 'GB');
    }
}

// Command line execution
if (php_sapi_name() === 'cli') {
    try {
        $scanner = new CoreStructureScanner($config);
        $results = $scanner->scanAll();
        
        echo "\n🎯 CORE APPLICATION REPORTS GENERATED:\n";
        echo "- Structure: var/reports/{$results['structure_file']}\n";
        echo "- Contents: var/reports/{$results['contents_file']}\n";
        echo "\n✅ CORE STRUCTURE SCAN COMPLETE\n";
        
    } catch (Exception $e) {
        echo "❌ Scan failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Web execution
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    
    try {
        $scanner = new CoreStructureScanner($config);
        $results = $scanner->scanAll();
        
        echo json_encode([
            'success' => true,
            'scan_id' => $results['scan_id'],
            'reports' => [
                'structure' => "var/reports/{$results['structure_file']}",
                'contents' => "var/reports/{$results['contents_file']}"
            ],
            'execution_time_ms' => $results['execution_time_ms']
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

?>
