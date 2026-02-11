<?php

namespace Viraloka\Core\Database\Migrations;

/**
 * Identity & Membership Migration
 * 
 * Creates database tables for the Identity & Membership system:
 * - viraloka_identities: Global identity entities
 * - viraloka_memberships: Workspace membership relations
 * - viraloka_grants: Temporary access grants with constraints
 * - viraloka_role_capabilities: Custom role capability mappings
 * 
 * This migration is host-agnostic and provides the schema definitions.
 * Host-specific implementations should use these definitions with their
 * respective database management tools (e.g., dbDelta for WordPress).
 */
class IdentityMembershipMigration
{
    /**
     * Get the SQL for creating the identities table
     * 
     * @param string $prefix Table prefix
     * @param string $charset_collate Charset and collation
     * @return string
     */
    public static function getIdentitiesTableSql(string $prefix, string $charset_collate): string
    {
        $table_name = $prefix . 'viraloka_identities';
        
        return "CREATE TABLE $table_name (
            identity_id CHAR(36) NOT NULL,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            metadata TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (identity_id),
            UNIQUE KEY uk_email (email),
            KEY idx_email (email),
            KEY idx_status (status)
        ) $charset_collate;";
    }
    
    /**
     * Get the SQL for creating the memberships table
     * 
     * @param string $prefix Table prefix
     * @param string $charset_collate Charset and collation
     * @return string
     */
    public static function getMembershipsTableSql(string $prefix, string $charset_collate): string
    {
        $table_name = $prefix . 'viraloka_memberships';
        
        return "CREATE TABLE $table_name (
            membership_id CHAR(36) NOT NULL,
            identity_id CHAR(36) NOT NULL,
            workspace_id CHAR(36) NOT NULL,
            role VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (membership_id),
            UNIQUE KEY uk_identity_workspace (identity_id, workspace_id),
            KEY idx_identity (identity_id),
            KEY idx_workspace (workspace_id),
            KEY idx_status (status),
            KEY idx_role (role)
        ) $charset_collate;";
    }
    
    /**
     * Get the SQL for creating the grants table
     * 
     * @param string $prefix Table prefix
     * @param string $charset_collate Charset and collation
     * @return string
     */
    public static function getGrantsTableSql(string $prefix, string $charset_collate): string
    {
        $table_name = $prefix . 'viraloka_grants';
        
        return "CREATE TABLE $table_name (
            grant_id CHAR(36) NOT NULL,
            identity_id CHAR(36) NOT NULL,
            workspace_id CHAR(36) NOT NULL,
            role VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            expires_at DATETIME NULL,
            max_usage INT NULL,
            current_usage INT NOT NULL DEFAULT 0,
            allowed_actions TEXT,
            metadata TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (grant_id),
            KEY idx_identity (identity_id),
            KEY idx_workspace (workspace_id),
            KEY idx_status (status),
            KEY idx_expires (expires_at)
        ) $charset_collate;";
    }
    
    /**
     * Get the SQL for creating the role capabilities table
     * 
     * @param string $prefix Table prefix
     * @param string $charset_collate Charset and collation
     * @return string
     */
    public static function getRoleCapabilitiesTableSql(string $prefix, string $charset_collate): string
    {
        $table_name = $prefix . 'viraloka_role_capabilities';
        
        return "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            role VARCHAR(50) NOT NULL,
            capability VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_role_capability (role, capability),
            KEY idx_role (role)
        ) $charset_collate;";
    }
    
    /**
     * Get all table names for the Identity & Membership system
     * 
     * @param string $prefix Table prefix
     * @return array
     */
    public static function getTableNames(string $prefix): array
    {
        return [
            $prefix . 'viraloka_identities',
            $prefix . 'viraloka_memberships',
            $prefix . 'viraloka_grants',
            $prefix . 'viraloka_role_capabilities',
        ];
    }
}
