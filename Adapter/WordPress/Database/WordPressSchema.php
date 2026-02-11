<?php

namespace Viraloka\Adapter\WordPress\Database;

use Viraloka\Core\Database\Migrations\IdentityMembershipMigration;

/**
 * WordPress Schema
 * 
 * WordPress-specific database schema management.
 * Manages database table creation and updates for the SaaS platform.
 * Creates tables for workspace memberships, roles, subscriptions, and usage tracking.
 * 
 * This class is WordPress-specific and uses WordPress database functions.
 */
class WordPressSchema
{
    /**
     * Create all required database tables
     * 
     * @return void
     */
    public static function createTables(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create Identity & Membership system tables
        self::createIdentitiesTable($wpdb, $charset_collate);
        self::createMembershipsTable($wpdb, $charset_collate);
        self::createGrantsTable($wpdb, $charset_collate);
        self::createRoleCapabilitiesTable($wpdb, $charset_collate);
        
        // Create workspace memberships table
        self::createWorkspaceMembershipsTable($wpdb, $charset_collate);
        
        // Create workspace roles and capabilities table
        self::createWorkspaceRolesTable($wpdb, $charset_collate);
        
        // Create subscriptions table
        self::createSubscriptionsTable($wpdb, $charset_collate);
        
        // Create usage records table
        self::createUsageRecordsTable($wpdb, $charset_collate);
    }
    
    /**
     * Create identities table
     * 
     * Stores global identity entities for the Identity & Membership system.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected static function createIdentitiesTable($wpdb, string $charset_collate): void
    {
        $sql = IdentityMembershipMigration::getIdentitiesTableSql($wpdb->prefix, $charset_collate);
        dbDelta($sql);
    }
    
    /**
     * Create memberships table
     * 
     * Stores workspace membership relations for the Identity & Membership system.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected static function createMembershipsTable($wpdb, string $charset_collate): void
    {
        $sql = IdentityMembershipMigration::getMembershipsTableSql($wpdb->prefix, $charset_collate);
        dbDelta($sql);
    }
    
    /**
     * Create grants table
     * 
     * Stores temporary access grants with constraints for the Identity & Membership system.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected static function createGrantsTable($wpdb, string $charset_collate): void
    {
        $sql = IdentityMembershipMigration::getGrantsTableSql($wpdb->prefix, $charset_collate);
        dbDelta($sql);
    }
    
    /**
     * Create role capabilities table
     * 
     * Stores custom role capability mappings for the Identity & Membership system.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected static function createRoleCapabilitiesTable($wpdb, string $charset_collate): void
    {
        $sql = IdentityMembershipMigration::getRoleCapabilitiesTableSql($wpdb->prefix, $charset_collate);
        dbDelta($sql);
    }
    
    /**
     * Create workspace memberships table
     * 
     * Stores user membership in workspaces for multi-tenancy.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected static function createWorkspaceMembershipsTable($wpdb, string $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'viraloka_workspace_memberships';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            workspace_id varchar(255) NOT NULL,
            role varchar(50) NOT NULL DEFAULT 'member',
            status varchar(20) NOT NULL DEFAULT 'active',
            joined_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY workspace_id (workspace_id),
            KEY status (status),
            UNIQUE KEY user_workspace (user_id, workspace_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create workspace roles and capabilities table
     * 
     * Stores workspace-scoped roles and capabilities for fine-grained permissions.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected static function createWorkspaceRolesTable($wpdb, string $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'viraloka_workspace_roles';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            workspace_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            role varchar(50) NOT NULL,
            capabilities text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY workspace_id (workspace_id),
            KEY user_id (user_id),
            KEY role (role),
            UNIQUE KEY workspace_user_role (workspace_id, user_id, role)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create subscriptions table
     * 
     * Stores subscription information for workspaces including tier and status.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected static function createSubscriptionsTable($wpdb, string $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'viraloka_subscriptions';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            workspace_id varchar(255) NOT NULL,
            tier varchar(50) NOT NULL DEFAULT 'free',
            status varchar(20) NOT NULL DEFAULT 'active',
            limits text,
            started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            cancelled_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY workspace_id (workspace_id),
            KEY tier (tier),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create usage records table
     * 
     * Stores credit-based usage metering for workspaces.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected static function createUsageRecordsTable($wpdb, string $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'viraloka_usage_records';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            workspace_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            resource_type varchar(100) NOT NULL,
            amount int(11) NOT NULL DEFAULT 1,
            metadata text,
            recorded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY workspace_id (workspace_id),
            KEY user_id (user_id),
            KEY resource_type (resource_type),
            KEY recorded_at (recorded_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Drop all tables (for uninstall)
     * 
     * @return void
     */
    public static function dropTables(): void
    {
        global $wpdb;
        
        $tables = [
            // Identity & Membership system tables
            $wpdb->prefix . 'viraloka_identities',
            $wpdb->prefix . 'viraloka_memberships',
            $wpdb->prefix . 'viraloka_grants',
            $wpdb->prefix . 'viraloka_role_capabilities',
            // Legacy tables
            $wpdb->prefix . 'viraloka_workspace_memberships',
            $wpdb->prefix . 'viraloka_workspace_roles',
            $wpdb->prefix . 'viraloka_subscriptions',
            $wpdb->prefix . 'viraloka_usage_records',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Check if tables exist
     * 
     * @return bool
     */
    public static function tablesExist(): bool
    {
        global $wpdb;
        
        $tables = [
            // Identity & Membership system tables
            $wpdb->prefix . 'viraloka_identities',
            $wpdb->prefix . 'viraloka_memberships',
            $wpdb->prefix . 'viraloka_grants',
            $wpdb->prefix . 'viraloka_role_capabilities',
            // Legacy tables
            $wpdb->prefix . 'viraloka_workspace_memberships',
            $wpdb->prefix . 'viraloka_workspace_roles',
            $wpdb->prefix . 'viraloka_subscriptions',
            $wpdb->prefix . 'viraloka_usage_records',
        ];
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
}
