<?php

namespace Viraloka\Core\Bootstrap;

/**
 * ScopeValidator
 * 
 * Validates that the SaaS Suite core stays within its defined scope boundaries.
 * Ensures no out-of-scope features (inventory, shipping, logistics, physical fulfillment) exist.
 * 
 * Validates: Requirements 13.2
 */
class ScopeValidator
{
    /**
     * Out-of-scope terms that should not appear in core components
     */
    private const OUT_OF_SCOPE_TERMS = [
        'inventory',
        'shipping',
        'logistics',
        'fulfillment',
        'warehouse',
        'stock',
        'sku',
        'shipment',
        'carrier',
        'tracking_number',
        'physical_product',
        'package',
        'delivery',
    ];

    /**
     * Directories to validate for scope compliance
     */
    private const CORE_DIRECTORIES = [
        'src/Core',
    ];

    /**
     * Files to exclude from scope validation (they legitimately reference out-of-scope terms)
     */
    private const EXCLUDED_FILES = [
        'src/Core/Bootstrap/ScopeValidator.php',  // Contains terms for validation
        'src/Core/Context/ContextResolver.php',   // Detects physical marketplace context
    ];

    /**
     * Validate that core components contain no out-of-scope functionality
     * 
     * @return array{valid: bool, violations: array<string>}
     */
    public function validateCoreScope(): array
    {
        $violations = [];

        foreach (self::CORE_DIRECTORIES as $directory) {
            $directoryViolations = $this->scanDirectory($directory);
            $violations = array_merge($violations, $directoryViolations);
        }

        return [
            'valid' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Scan a directory for out-of-scope terms
     * 
     * @param string $directory
     * @return array<string>
     */
    private function scanDirectory(string $directory): array
    {
        $violations = [];
        $basePath = dirname(__DIR__, 3) . '/' . $directory;

        if (!is_dir($basePath)) {
            return $violations;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $fileViolations = $this->scanFile($file->getPathname());
                $violations = array_merge($violations, $fileViolations);
            }
        }

        return $violations;
    }

    /**
     * Scan a file for out-of-scope terms
     * 
     * @param string $filePath
     * @return array<string>
     */
    private function scanFile(string $filePath): array
    {
        $violations = [];
        
        // Get relative path for exclusion check
        $relativePath = str_replace(dirname(__DIR__, 3) . '/', '', $filePath);
        $relativePath = str_replace('\\', '/', $relativePath);
        
        // Skip excluded files
        foreach (self::EXCLUDED_FILES as $excludedFile) {
            if (str_contains($relativePath, str_replace('\\', '/', $excludedFile))) {
                return $violations;
            }
        }
        
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            return $violations;
        }

        // Remove comments to avoid false positives
        $content = $this->removeComments($content);

        foreach (self::OUT_OF_SCOPE_TERMS as $term) {
            // Case-insensitive search for the term as a whole word
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $content)) {
                $violations[] = "Out-of-scope term '{$term}' found in {$relativePath}";
            }
        }

        return $violations;
    }

    /**
     * Remove comments from PHP code to avoid false positives
     * 
     * @param string $content
     * @return string
     */
    private function removeComments(string $content): string
    {
        // Remove single-line comments
        $content = preg_replace('/\/\/.*$/m', '', $content);
        
        // Remove multi-line comments
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);
        
        return $content;
    }

    /**
     * Check if a specific term is out of scope
     * 
     * @param string $term
     * @return bool
     */
    public function isOutOfScope(string $term): bool
    {
        return in_array(strtolower($term), self::OUT_OF_SCOPE_TERMS, true);
    }

    /**
     * Get all out-of-scope terms
     * 
     * @return array<string>
     */
    public function getOutOfScopeTerms(): array
    {
        return self::OUT_OF_SCOPE_TERMS;
    }
}
