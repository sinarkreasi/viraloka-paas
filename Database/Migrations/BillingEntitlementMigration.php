<?php

namespace Viraloka\Core\Database\Migrations;

/**
 * Billing & Entitlement Migration
 * 
 * Creates database tables for the Billing & Entitlement system:
 * - viraloka_subscriptions: Workspace subscription relationships
 * - viraloka_entitlements: Feature access rights and quotas
 * - viraloka_usage_records: Feature consumption tracking
 * - viraloka_plans: Subscription plan definitions
 * 
 * This migration is host-agnostic and provides the schema definitions.
 * Host-specific implementations should use these definitions with their
 * respective database management tools (e.g., dbDelta for WordPress).
 */
class BillingEntitlementMigration
{
    /**
     * Get the SQL for creating the subscriptions table
     * 
     * @param string $prefix Table prefix
     * @param string $charset_collate Charset and collation
     * @return string
     */
    public static function getSubscriptionsTableSql(string $prefix, string $charset_collate): string
    {
        $table_name = $prefix . 'viraloka_subscriptions';
        
        return "CREATE TABLE $table_name (
            subscription_id CHAR(36) NOT NULL,
            workspace_id CHAR(36) NOT NULL,
            plan_id VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            billing_period VARCHAR(20) NOT NULL,
            started_at DATETIME NOT NULL,
            ends_at DATETIME NULL,
            metadata TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (subscription_id),
            UNIQUE KEY uk_workspace (workspace_id),
            KEY idx_status (status),
            KEY idx_plan (plan_id),
            KEY idx_ends_at (ends_at)
        ) $charset_collate;";
    }
    
    /**
     * Get the SQL for creating the entitlements table
     * 
     * @param string $prefix Table prefix
     * @param string $charset_collate Charset and collation
     * @return string
     */
    public static function getEntitlementsTableSql(string $prefix, string $charset_collate): string
    {
        $table_name = $prefix . 'viraloka_entitlements';
        
        return "CREATE TABLE $table_name (
            entitlement_id CHAR(36) NOT NULL,
            workspace_id CHAR(36) NOT NULL,
            `key` VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL,
            value TEXT NOT NULL,
            current_usage INT NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (entitlement_id),
            UNIQUE KEY uk_workspace_key (workspace_id, `key`),
            KEY idx_workspace (workspace_id),
            KEY idx_key (`key`),
            KEY idx_expires_at (expires_at)
        ) $charset_collate;";
    }
    
    /**
     * Get the SQL for creating the usage records table
     * 
     * @param string $prefix Table prefix
     * @param string $charset_collate Charset and collation
     * @return string
     */
    public static function getUsageRecordsTableSql(string $prefix, string $charset_collate): string
    {
        $table_name = $prefix . 'viraloka_usage_records';
        
        return "CREATE TABLE $table_name (
            usage_id CHAR(36) NOT NULL,
            workspace_id CHAR(36) NOT NULL,
            `key` VARCHAR(255) NOT NULL,
            amount INT NOT NULL,
            metadata TEXT,
            recorded_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (usage_id),
            KEY idx_workspace (workspace_id),
            KEY idx_key (`key`),
            KEY idx_recorded_at (recorded_at),
            KEY idx_workspace_key_recorded (workspace_id, `key`, recorded_at)
        ) $charset_collate;";
    }
    
    /**
     * Get the SQL for creating the plans table
     * 
     * @param string $prefix Table prefix
     * @param string $charset_collate Charset and collation
     * @return string
     */
    public static function getPlansTableSql(string $prefix, string $charset_collate): string
    {
        $table_name = $prefix . 'viraloka_plans';
        
        return "CREATE TABLE $table_name (
            plan_id VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            billing_period VARCHAR(20) NOT NULL,
            entitlements TEXT NOT NULL,
            trial_period_days INT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            metadata TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (plan_id),
            KEY idx_is_active (is_active)
        ) $charset_collate;";
    }
    
    /**
     * Get all table names for the Billing & Entitlement system
     * 
     * @param string $prefix Table prefix
     * @return array
     */
    public static function getTableNames(string $prefix): array
    {
        return [
            $prefix . 'viraloka_subscriptions',
            $prefix . 'viraloka_entitlements',
            $prefix . 'viraloka_usage_records',
            $prefix . 'viraloka_plans',
        ];
    }
}
