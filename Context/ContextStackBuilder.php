<?php

namespace Viraloka\Core\Context;

/**
 * Context Stack Builder
 * 
 * Builds an immutable context stack from multiple context sources.
 * Handles normalization, priority ordering, and deduplication.
 * 
 * Validates: Requirements 1.4, 1.5, 2.5, 10.1, 10.5
 */
class ContextStackBuilder
{
    /**
     * Build a context stack from multiple sources
     * 
     * Orchestrates the normalization, sorting, and deduplication process
     * to create a valid, frozen context stack.
     * 
     * Process:
     * 1. Normalize sources (filter invalid)
     * 2. Sort by priority (highest first)
     * 3. Deduplicate (keep highest priority for each key)
     * 4. Create and freeze the stack
     * 
     * @param array<mixed> $sources Array of potential context sources
     * @return ContextStack The built and frozen context stack
     */
    public function build(array $sources): ContextStack
    {
        // Step 1: Normalize - filter out invalid sources
        $normalized = $this->normalize($sources);
        
        // Step 2: Sort by priority (highest first)
        $sorted = $this->sortByPriority($normalized);
        
        // Step 3: Deduplicate - keep highest priority for each key
        $deduplicated = $this->deduplicate($sorted);
        
        // Step 4: Create and freeze the context stack
        $stack = new ContextStack($deduplicated);
        $stack->freeze();
        
        return $stack;
    }
    
    /**
     * Normalize context sources
     * 
     * Filters the input array to include only valid context sources
     * that implement ContextInterface. Invalid sources are silently
     * discarded.
     * 
     * @param array<mixed> $sources Array of potential context sources
     * @return array<ContextInterface> Array of valid context sources
     */
    private function normalize(array $sources): array
    {
        $validated = [];
        
        foreach ($sources as $source) {
            // Only include sources that implement ContextInterface
            if ($source instanceof ContextInterface) {
                $validated[] = $source;
            }
        }
        
        return $validated;
    }
    
    /**
     * Sort contexts by priority
     * 
     * Orders contexts in descending priority order (highest priority first).
     * This ensures the primary context is always the highest priority context.
     * 
     * @param array<ContextInterface> $contexts Array of context sources
     * @return array<ContextInterface> Sorted array of contexts
     */
    private function sortByPriority(array $contexts): array
    {
        // Create a copy to avoid modifying the original array
        $sorted = $contexts;
        
        // Sort by priority in descending order (highest first)
        usort($sorted, function (ContextInterface $a, ContextInterface $b) {
            return $b->getPriority() - $a->getPriority();
        });
        
        return $sorted;
    }
    
    /**
     * Remove duplicate contexts
     * 
     * When multiple contexts have the same key, keeps only the
     * highest-priority instance. Assumes the input array is already
     * sorted by priority.
     * 
     * @param array<ContextInterface> $contexts Array of sorted contexts
     * @return array<ContextInterface> Deduplicated array of contexts
     */
    private function deduplicate(array $contexts): array
    {
        $seen = [];
        $result = [];
        
        foreach ($contexts as $context) {
            $key = $context->getKey();
            
            // Only add if we haven't seen this key before
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $context;
            }
        }
        
        return $result;
    }
}
