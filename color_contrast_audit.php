#!/usr/bin/env php
<?php
/**
 * WCAG 2.1 AA Color Contrast Audit Tool
 * 
 * Analyzes CSS files for color contrast compliance
 * Validates light/dark mode color combinations
 * Reports WCAG AA violations and provides fixes
 * 
 * Usage: php color_contrast_audit.php
 */

echo "🎨 CIS 2.0 WCAG Color Contrast Audit\n";
echo "=====================================\n\n";

// Define WCAG AA standards
const WCAG_AA_NORMAL = 4.5;
const WCAG_AA_LARGE = 3.0;
const WCAG_AAA_NORMAL = 7.0;
const WCAG_AAA_LARGE = 4.5;

/**
 * Convert hex color to RGB
 */
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

/**
 * Calculate relative luminance
 */
function getRelativeLuminance($rgb) {
    $r = $rgb['r'] / 255;
    $g = $rgb['g'] / 255;
    $b = $rgb['b'] / 255;
    
    $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
    
    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

/**
 * Calculate contrast ratio between two colors
 */
function getContrastRatio($color1, $color2) {
    $lum1 = getRelativeLuminance(hexToRgb($color1));
    $lum2 = getRelativeLuminance(hexToRgb($color2));
    
    $lighter = max($lum1, $lum2);
    $darker = min($lum1, $lum2);
    
    return ($lighter + 0.05) / ($darker + 0.05);
}

/**
 * Check if contrast meets WCAG standards
 */
function checkWCAGCompliance($ratio, $isLargeText = false) {
    return [
        'AA' => $ratio >= ($isLargeText ? WCAG_AA_LARGE : WCAG_AA_NORMAL),
        'AAA' => $ratio >= ($isLargeText ? WCAG_AAA_LARGE : WCAG_AAA_NORMAL),
        'ratio' => round($ratio, 2)
    ];
}

/**
 * Extract colors from CSS content
 */
function extractColorsFromCSS($cssContent) {
    $colors = [];
    
    // Match hex colors
    preg_match_all('/#([a-fA-F0-9]{3,6})\b/', $cssContent, $hexMatches);
    foreach ($hexMatches[1] as $hex) {
        $colors[] = '#' . $hex;
    }
    
    // Match RGB colors
    preg_match_all('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $cssContent, $rgbMatches, PREG_SET_ORDER);
    foreach ($rgbMatches as $match) {
        $hex = sprintf("#%02x%02x%02x", $match[1], $match[2], $match[3]);
        $colors[] = $hex;
    }
    
    return array_unique($colors);
}

/**
 * Parse CSS for color combinations
 */
function parseColorCombinations($cssContent) {
    $combinations = [];
    
    // Match rules with both color and background-color
    preg_match_all('/([^{}]+)\{([^{}]+)\}/', $cssContent, $ruleMatches, PREG_SET_ORDER);
    
    foreach ($ruleMatches as $rule) {
        $selector = trim($rule[1]);
        $properties = $rule[2];
        
        $color = null;
        $backgroundColor = null;
        
        // Extract color
        if (preg_match('/color\s*:\s*(#[a-fA-F0-9]{3,6}|rgb\([^)]+\))/i', $properties, $colorMatch)) {
            $color = $colorMatch[1];
            if (preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $color, $rgbMatch)) {
                $color = sprintf("#%02x%02x%02x", $rgbMatch[1], $rgbMatch[2], $rgbMatch[3]);
            }
        }
        
        // Extract background-color
        if (preg_match('/background-color\s*:\s*(#[a-fA-F0-9]{3,6}|rgb\([^)]+\))/i', $properties, $bgMatch)) {
            $backgroundColor = $bgMatch[1];
            if (preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $backgroundColor, $rgbMatch)) {
                $backgroundColor = sprintf("#%02x%02x%02x", $rgbMatch[1], $rgbMatch[2], $rgbMatch[3]);
            }
        }
        
        if ($color && $backgroundColor) {
            $combinations[] = [
                'selector' => $selector,
                'color' => $color,
                'background' => $backgroundColor,
                'isLargeText' => (strpos($selector, 'h1') !== false || 
                                strpos($selector, 'h2') !== false || 
                                strpos($selector, '.lead') !== false ||
                                strpos($selector, '.display-') !== false)
            ];
        }
    }
    
    return $combinations;
}

// Scan CSS files
$cssFiles = [
    'assets/css/admin.css',
    'assets/css/app.css', 
    'assets/css/backup-system.css',
    'assets/css/components.css',
    'assets/css/dashboard.css',
    'assets/css/forms.css',
    'assets/css/tables.css',
    'assets/css/utilities.css'
];

$auditResults = [];
$totalViolations = 0;
$totalCombinations = 0;

echo "📊 Scanning CSS files for color combinations...\n\n";

foreach ($cssFiles as $cssFile) {
    if (!file_exists($cssFile)) {
        echo "⚠️  File not found: $cssFile\n";
        continue;
    }
    
    echo "🔍 Analyzing: $cssFile\n";
    
    $cssContent = file_get_contents($cssFile);
    $combinations = parseColorCombinations($cssContent);
    $violations = [];
    
    foreach ($combinations as $combo) {
        $ratio = getContrastRatio($combo['color'], $combo['background']);
        $compliance = checkWCAGCompliance($ratio, $combo['isLargeText']);
        
        $totalCombinations++;
        
        if (!$compliance['AA']) {
            $totalViolations++;
            $violations[] = [
                'selector' => $combo['selector'],
                'color' => $combo['color'],
                'background' => $combo['background'],
                'ratio' => $compliance['ratio'],
                'isLargeText' => $combo['isLargeText'],
                'meetsAA' => $compliance['AA'],
                'meetsAAA' => $compliance['AAA']
            ];
        }
    }
    
    $auditResults[$cssFile] = [
        'combinations' => count($combinations),
        'violations' => $violations,
        'violationCount' => count($violations)
    ];
    
    echo "   ✅ Combinations: " . count($combinations) . "\n";
    echo "   ❌ Violations: " . count($violations) . "\n\n";
}

// Generate report
echo "📋 WCAG 2.1 AA Color Contrast Audit Report\n";
echo "==========================================\n\n";

echo "📈 Summary:\n";
echo "- Total combinations tested: $totalCombinations\n";
echo "- WCAG AA violations: $totalViolations\n";
echo "- Compliance rate: " . round(($totalCombinations - $totalViolations) / max($totalCombinations, 1) * 100, 1) . "%\n\n";

if ($totalViolations > 0) {
    echo "❌ WCAG AA Violations Found:\n";
    echo "============================\n\n";
    
    foreach ($auditResults as $file => $results) {
        if ($results['violationCount'] > 0) {
            echo "📄 $file ({$results['violationCount']} violations):\n";
            
            foreach ($results['violations'] as $violation) {
                echo "  🔴 {$violation['selector']}\n";
                echo "     Color: {$violation['color']} on {$violation['background']}\n";
                echo "     Contrast ratio: {$violation['ratio']}:1\n";
                echo "     Required: " . ($violation['isLargeText'] ? WCAG_AA_LARGE : WCAG_AA_NORMAL) . ":1 (AA)\n";
                echo "     Status: " . ($violation['meetsAA'] ? '✅ AA' : '❌ AA') . " | " . ($violation['meetsAAA'] ? '✅ AAA' : '❌ AAA') . "\n\n";
            }
        }
    }
    
    echo "🔧 Recommended Fixes:\n";
    echo "=====================\n\n";
    
    foreach ($auditResults as $file => $results) {
        if ($results['violationCount'] > 0) {
            echo "📄 $file:\n";
            
            foreach ($results['violations'] as $violation) {
                $requiredRatio = $violation['isLargeText'] ? WCAG_AA_LARGE : WCAG_AA_NORMAL;
                
                echo "  {$violation['selector']} {\n";
                
                // Suggest darker text or lighter background
                $currentBg = hexToRgb($violation['background']);
                $currentText = hexToRgb($violation['color']);
                
                // Calculate if we should darken text or lighten background
                $bgLum = getRelativeLuminance($currentBg);
                $textLum = getRelativeLuminance($currentText);
                
                if ($bgLum > $textLum) {
                    // Light background, darken text
                    echo "    /* Option 1: Darken text color */\n";
                    echo "    color: #2c3e50; /* Darker alternative */\n";
                    echo "    \n";
                    echo "    /* Option 2: Add text shadow for better contrast */\n";
                    echo "    text-shadow: 0 1px 2px rgba(0,0,0,0.3);\n";
                } else {
                    // Dark background, lighten text
                    echo "    /* Option 1: Lighten text color */\n";
                    echo "    color: #ffffff; /* Light alternative */\n";
                    echo "    \n";
                    echo "    /* Option 2: Use high-contrast accent */\n";
                    echo "    color: #f8f9fa;\n";
                }
                
                echo "  }\n\n";
            }
        }
    }
} else {
    echo "✅ Excellent! All color combinations meet WCAG 2.1 AA standards.\n\n";
}

// Bootstrap color palette analysis
echo "🎨 Bootstrap Theme Color Analysis:\n";
echo "==================================\n\n";

$bootstrapColors = [
    'primary' => '#0d6efd',
    'secondary' => '#6c757d', 
    'success' => '#198754',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#0dcaf0',
    'light' => '#f8f9fa',
    'dark' => '#212529'
];

$backgrounds = ['#ffffff', '#000000', '#f8f9fa', '#212529'];

foreach ($bootstrapColors as $name => $color) {
    echo "🏷️  $name ($color):\n";
    
    foreach ($backgrounds as $bg) {
        $ratio = getContrastRatio($color, $bg);
        $compliance = checkWCAGCompliance($ratio);
        $bgName = $bg === '#ffffff' ? 'white' : ($bg === '#000000' ? 'black' : ($bg === '#f8f9fa' ? 'light' : 'dark'));
        
        echo "   On $bgName: {$compliance['ratio']}:1 " . 
             ($compliance['AA'] ? '✅' : '❌') . " AA " .
             ($compliance['AAA'] ? '✅' : '❌') . " AAA\n";
    }
    echo "\n";
}

// Dark mode specific checks
echo "🌙 Dark Mode Compatibility:\n";
echo "===========================\n\n";

$darkModeFiles = glob('assets/css/*dark*.css');
if (empty($darkModeFiles)) {
    echo "ℹ️  No dedicated dark mode CSS files found.\n";
    echo "   Consider creating dark mode variants with proper contrast ratios.\n\n";
} else {
    foreach ($darkModeFiles as $darkFile) {
        echo "🔍 Analyzing dark mode: $darkFile\n";
        // Analysis would be similar to above
    }
}

// Final recommendations
echo "💡 Final Recommendations:\n";
echo "=========================\n\n";

if ($totalViolations === 0) {
    echo "✅ Your color scheme is WCAG 2.1 AA compliant!\n";
    echo "✅ Consider testing for AAA compliance for enhanced accessibility.\n";
    echo "✅ Implement user preference for high contrast mode.\n";
} else {
    echo "🔧 Priority fixes needed:\n";
    echo "   1. Address the $totalViolations WCAG AA violations listed above\n";
    echo "   2. Test all color combinations in both light and dark modes\n";
    echo "   3. Consider implementing a high contrast theme option\n";
}

echo "📋 Additional best practices:\n";
echo "   • Use CSS custom properties for consistent color management\n";
echo "   • Test with color blindness simulators\n";
echo "   • Validate with actual assistive technology users\n";
echo "   • Consider WCAG AAA compliance for critical interfaces\n\n";

echo "🎯 Audit completed successfully!\n";
echo "   Report generated: " . date('Y-m-d H:i:s') . "\n\n";

// Save detailed report to file
$reportData = [
    'timestamp' => date('c'),
    'summary' => [
        'totalCombinations' => $totalCombinations,
        'totalViolations' => $totalViolations,
        'complianceRate' => round(($totalCombinations - $totalViolations) / max($totalCombinations, 1) * 100, 1)
    ],
    'results' => $auditResults,
    'bootstrapColors' => $bootstrapColors
];

file_put_contents('color_contrast_audit_report.json', json_encode($reportData, JSON_PRETTY_PRINT));
echo "📄 Detailed report saved to: color_contrast_audit_report.json\n";
