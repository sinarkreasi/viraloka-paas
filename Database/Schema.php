<?php

namespace Viraloka\Core\Database;

use Viraloka\Adapter\WordPress\Database\WordPressSchema;

/**
 * Schema
 * 
 * @deprecated Use Viraloka\Adapter\WordPress\Database\WordPressSchema instead
 * 
 * This class is deprecated and maintained only for backward compatibility.
 * Database schema management is now handled by host-specific adapters.
 * 
 * For WordPress: Use WordPressSchema
 * For other hosts: Implement your own schema management
 */
class Schema
{
    /**
     * Create all required database tables
     * 
     * @deprecated Use WordPressSchema::createTables() instead
     * @return void
     */
    public static function createTables(): void
    {
        // Delegate to WordPress implementation for backward compatibility
        WordPressSchema::createTables();
    }
    
    /**
     * Drop all tables (for uninstall)
     * 
     * @deprecated Use WordPressSchema::dropTables() instead
     * @return void
     */
    public static function dropTables(): void
    {
        // Delegate to WordPress implementation for backward compatibility
        WordPressSchema::dropTables();
    }
    
    /**
     * Check if tables exist
     * 
     * @deprecated Use WordPressSchema::tablesExist() instead
     * @return bool
     */
    public static function tablesExist(): bool
    {
        // Delegate to WordPress implementation for backward compatibility
        return WordPressSchema::tablesExist();
    }
}
