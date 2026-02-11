<?php

namespace Viraloka\Adapter\WordPress\Database;

/**
 * WordPress Workspace Tenant Schema
 * 
 * WordPress-specific database schema management for workspace and tenant system.
 * Manages database table creation and updates for tenants, workspaces, tenant users,
 * workspace user roles, and domain verifications.
 * 
 * This class is WordPress-specific and uses WordPress database functions.
 */
class WordPressWorkspaceTenantSchema
{
    /**
     * Create all required database tables
     * 
     * @return void
     */
    public function createTables(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables in dependency order
        $this->createTenantsTable($wpdb, $charset_collate);
        $this->createWorkspacesTable($wpdb, $charset_collate);
        $this->createTenantUsersTable($wpdb, $charset_collate);
        $this->createWorkspaceUserRolesTable($wpdb, $charset_collate);
        $this->createDomainVerificationsTable($wpdb, $charset_collate);
    }
    
    /**
     * Create tenants table
     * 
     * Stores tenant entities representing ownership boundaries.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected function createTenantsTable($wpdb, string $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'viraloka_tenants';
        
        $sql = "CREATE TABLE $table_name (
            tenant_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            owner_user_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (tenant_id),
            KEY owner_user_id (owner_user_id),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create workspaces table
     * 
     * Stores workspace entities representing isolated operational units.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected function createWorkspacesTable($wpdb, string $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'viraloka_workspaces';
        
        $sql = "CREATE TABLE $table_name (
            workspace_id CHAR(36) NOT NULL,
            tenant_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            active_context VARCHAR(100) NOT NULL DEFAULT 'default',
            custom_domain VARCHAR(255) DEFAULT NULL,
            subdomain VARCHAR(100) DEFAULT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (workspace_id),
            UNIQUE KEY slug (slug),
            UNIQUE KEY custom_domain (custom_domain),
            UNIQUE KEY subdomain (subdomain),
            KEY tenant_id (tenant_id),
            KEY status (status),
            KEY is_default (is_default),
            CONSTRAINT fk_workspace_tenant FOREIGN KEY (tenant_id) 
                REFERENCES {$wpdb->prefix}viraloka_tenants(tenant_id) ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create tenant users table
     * 
     * Stores user memberships in tenants with roles.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected function createTenantUsersTable($wpdb, string $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'viraloka_tenant_users';
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id CHAR(36) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'member',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_user (tenant_id, user_id),
            KEY user_id (user_id),
            KEY role (role),
            CONSTRAINT fk_tenant_user_tenant FOREIGN KEY (tenant_id) 
                REFERENCES {$wpdb->prefix}viraloka_tenants(tenant_id) ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create workspace user roles table
     * 
     * Stores user role assignments within specific workspaces.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected function createWorkspaceUserRolesTable($wpdb, string $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'viraloka_workspace_user_roles';
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id CHAR(36) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'member',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY workspace_user (workspace_id, user_id),
            KEY user_id (user_id),
            KEY role (role),
            CONSTRAINT fk_workspace_role_workspace FOREIGN KEY (workspace_id) 
                REFERENCES {$wpdb->prefix}viraloka_workspaces(workspace_id) ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create domain verifications table
     * 
     * Stores custom domain ownership verification tokens and status.
     * 
     * @param \wpdb $wpdb
     * @param string $charset_collate
     * @return void
     */
    protected function createDomainVerificationsTable($wpdb, string $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'viraloka_domain_verifications';
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id CHAR(36) NOT NULL,
            domain VARCHAR(255) NOT NULL,
            verification_token VARCHAR(255) NOT NULL,
            verified_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY domain (domain),
            KEY workspace_id (workspace_id),
            KEY verification_token (verification_token),
            CONSTRAINT fk_domain_verification_workspace FOREIGN KEY (workspace_id) 
                REFERENCES {$wpdb->prefix}viraloka_workspaces(workspace_id) ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Drop all tables (for rollback)
     * 
     * @return void
     */
    public function dropTables(): void
    {
        global $wpdb;
        
        // Drop tables in reverse dependency order
        $tables = [
            $wpdb->prefix . 'viraloka_domain_verifications',
            $wpdb->prefix . 'viraloka_workspace_user_roles',
            $wpdb->prefix . 'viraloka_tenant_users',
            $wpdb->prefix . 'viraloka_workspaces',
            $wpdb->prefix . 'viraloka_tenants',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Check if all tables exist
     * 
     * @return bool
     */
    public function tablesExist(): bool
    {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'viraloka_tenants',
            $wpdb->prefix . 'viraloka_workspaces',
            $wpdb->prefix . 'viraloka_tenant_users',
            $wpdb->prefix . 'viraloka_workspace_user_roles',
            $wpdb->prefix . 'viraloka_domain_verifications',
        ];
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Run migration (create tables if they don't exist)
     * 
     * @return bool True if migration was successful
     */
    public function migrate(): bool
    {
        try {
            if (!$this->tablesExist()) {
                $this->createTables();
                return $this->tablesExist();
            }
            return true;
        } catch (\Exception $e) {
            error_log('WordPressWorkspaceTenantSchema migration failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Run rollback (drop all tables)
     * 
     * @return bool True if rollback was successful
     */
    public function rollback(): bool
    {
        try {
            $this->dropTables();
            return !$this->tablesExist();
        } catch (\Exception $e) {
            error_log('WordPressWorkspaceTenantSchema rollback failed: ' . $e->getMessage());
            return false;
        }
    }
}
