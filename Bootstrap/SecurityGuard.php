<?php

namespace Viraloka\Core\Bootstrap;

use Viraloka\Core\Application;
use Viraloka\Core\Modules\Logger;
use Viraloka\Core\Modules\Module;
use Viraloka\Core\Policy\Contracts\PolicyEngineContract;
use Viraloka\Core\Workspace\Workspace;
use Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface;
use Viraloka\Core\Adapter\Contracts\AuthAdapterInterface;

/**
 * SecurityGuard
 * 
 * Enforces security policies during and after bootstrap.
 * Provides capability gates, nonce validation, and module sandboxing.
 * Uses adapters for host-agnostic security operations.
 */
class SecurityGuard
{
    /**
     * The application instance
     * 
     * @var Application
     */
    protected Application $app;
    
    /**
     * Logger instance
     * 
     * @var Logger
     */
    protected Logger $logger;
    
    /**
     * Auth adapter
     * 
     * @var AuthAdapterInterface
     */
    protected AuthAdapterInterface $authAdapter;
    
    /**
     * Policy Engine instance
     * 
     * @var PolicyEngineContract|null
     */
    protected ?PolicyEngineContract $policyEngine = null;
    
    /**
     * Registered capability gates
     * 
     * @var array
     */
    protected array $capabilityGates = [];
    
    /**
     * Sandboxed modules
     * 
     * @var array
     */
    protected array $sandboxedModules = [];
    
    /**
     * Protected Core namespaces that modules cannot access
     * 
     * @var array
     */
    protected array $protectedNamespaces = [
        'Viraloka\\Core\\Bootstrap\\',
        'Viraloka\\Core\\Application',
    ];
    
    /**
     * Create a new SecurityGuard instance
     * 
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->logger = $app->make(Logger::class);
        
        // Get AuthAdapter from registry
        $registry = $app->make(AdapterRegistryInterface::class);
        $this->authAdapter = $registry->auth();
    }
    
    /**
     * Activate the security guard
     * 
     * Called during the boot phase to initialize security mechanisms.
     * 
     * @return void
     */
    public function activate(): void
    {
        // Initialize capability gate mechanisms
        $this->initializeCapabilityGates();
        
        // Wire PolicyEngine if available
        $this->wirePolicyEngine();
    }
    
    /**
     * Wire PolicyEngine into SecurityGuard
     * 
     * Connects the PolicyEngine for policy-first access control.
     * 
     * @return void
     */
    protected function wirePolicyEngine(): void
    {
        // Check if PolicyEngine is available
        if ($this->app->bound(PolicyEngineContract::class)) {
            try {
                $this->policyEngine = $this->app->make(PolicyEngineContract::class);
                // PolicyEngine wired successfully (info logging disabled for production)
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Failed to wire PolicyEngine: {$e->getMessage()}",
                    'security-guard',
                    'PolicyEngine Wiring Error'
                );
            }
        }
    }
    
    /**
     * Initialize capability gate mechanisms
     * 
     * Sets up WordPress capability checks for workspace-scoped permissions.
     * 
     * @return void
     */
    protected function initializeCapabilityGates(): void
    {
        // Register default capability gates
        $this->registerCapabilityGate('manage_viraloka_modules', 'manage_options');
        $this->registerCapabilityGate('manage_viraloka_workspaces', 'manage_options');
        $this->registerCapabilityGate('manage_viraloka_contexts', 'manage_options');
    }
    
    /**
     * Register a capability gate
     * 
     * Maps a Viraloka capability to a WordPress capability.
     * 
     * @param string $viralokaCapability
     * @param string $wordpressCapability
     * @return void
     */
    public function registerCapabilityGate(string $viralokaCapability, string $wordpressCapability): void
    {
        $this->capabilityGates[$viralokaCapability] = $wordpressCapability;
    }
    
    /**
     * Enforce a capability gate
     * 
     * Checks if the current user has the required capability.
     * 
     * @param string $capability
     * @return bool
     */
    public function enforceCapabilityGate(string $capability): bool
    {
        // Check if we have a mapping for this capability
        if (!isset($this->capabilityGates[$capability])) {
            $this->logger->warning("Unknown capability gate: {$capability}", null);
            return false;
        }
        
        $mappedCapability = $this->capabilityGates[$capability];
        
        // Use AuthAdapter to check permission
        $hasCapability = $this->authAdapter->hasPermission($mappedCapability);
        
        if (!$hasCapability) {
            $this->logger->warning(
                "Capability gate denied: {$capability} (requires {$mappedCapability})",
                null
            );
        }
        
        return $hasCapability;
    }
    
    /**
     * Validate a nonce for state-changing operations
     * 
     * Provides CSRF protection by validating nonces through AuthAdapter.
     * 
     * @param string $action The action name
     * @param string $nonce The nonce to validate
     * @return bool
     */
    public function validateNonce(string $action, string $nonce): bool
    {
        // Use AuthAdapter for nonce verification
        $result = $this->authAdapter->verifyNonce($nonce, $action);
        
        if (!$result) {
            $this->logger->warning(
                "Nonce validation failed for action: {$action}",
                null
            );
        }
        
        return $result;
    }
    
    /**
     * Sandbox a module to prevent unauthorized Core access
     * 
     * Prevents modules from accessing protected Core internals.
     * 
     * @param Module $module
     * @return void
     */
    public function sandboxModule(Module $module): void
    {
        $moduleId = $module->getId();
        
        // Mark module as sandboxed
        $this->sandboxedModules[$moduleId] = true;
        
        // Module sandboxed (info logging disabled for production)
    }
    
    /**
     * Check if a module is attempting to access protected Core internals
     * 
     * @param string $moduleId
     * @param string $className
     * @return bool True if access is allowed, false if denied
     */
    public function checkModuleAccess(string $moduleId, string $className): bool
    {
        // Check if module is sandboxed
        if (!isset($this->sandboxedModules[$moduleId])) {
            return true; // Module not sandboxed, allow access
        }
        
        // Check if class is in protected namespace
        foreach ($this->protectedNamespaces as $namespace) {
            if (strpos($className, $namespace) === 0) {
                $this->logger->warning(
                    "Module attempted to access protected Core class: {$className}",
                    $moduleId
                );
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get all registered capability gates
     * 
     * @return array
     */
    public function getCapabilityGates(): array
    {
        return $this->capabilityGates;
    }
    
    /**
     * Check if a module is sandboxed
     * 
     * @param string $moduleId
     * @return bool
     */
    public function isModuleSandboxed(string $moduleId): bool
    {
        return isset($this->sandboxedModules[$moduleId]);
    }
    
    /**
     * Evaluate access request using PolicyEngine
     * 
     * Delegates to PolicyEngine for policy-first access control.
     * Falls back to capability gates if PolicyEngine is not available.
     * 
     * @param string $userId User ID
     * @param string $resource Resource identifier
     * @param string $action Action being performed
     * @param Workspace $workspace Workspace context
     * @return bool True if access is granted, false otherwise
     */
    public function evaluateAccess(string $userId, string $resource, string $action, Workspace $workspace): bool
    {
        // If PolicyEngine is available, use it for evaluation
        if ($this->policyEngine !== null) {
            return $this->policyEngine->evaluate($userId, $resource, $action, $workspace);
        }
        
        // Fallback to basic capability check
        $capability = $this->getCapabilityForResource($resource, $action);
        if ($capability) {
            return $this->enforceCapabilityGate($capability);
        }
        
        // If no specific capability required, allow access
        return true;
    }
    
    /**
     * Check entitlement using PolicyEngine
     * 
     * @param string $userId User ID
     * @param string $entitlement Entitlement identifier
     * @param Workspace $workspace Workspace context
     * @return bool True if user has entitlement, false otherwise
     */
    public function checkEntitlement(string $userId, string $entitlement, Workspace $workspace): bool
    {
        if ($this->policyEngine !== null) {
            return $this->policyEngine->checkEntitlement($userId, $entitlement, $workspace);
        }
        
        // If PolicyEngine not available, allow access (graceful degradation)
        return true;
    }
    
    /**
     * Enforce quota using PolicyEngine
     * 
     * @param string $userId User ID
     * @param string $resourceType Resource type
     * @param int $amount Amount to check
     * @param Workspace $workspace Workspace context
     * @return bool True if quota available, false if limit reached
     */
    public function enforceQuota(string $userId, string $resourceType, int $amount, Workspace $workspace): bool
    {
        if ($this->policyEngine !== null) {
            return $this->policyEngine->enforceQuota($userId, $resourceType, $amount, $workspace);
        }
        
        // If PolicyEngine not available, allow access (graceful degradation)
        return true;
    }
    
    /**
     * Get capability for a resource/action combination
     * 
     * @param string $resource Resource identifier
     * @param string $action Action being performed
     * @return string|null Capability name, or null if no specific capability required
     */
    protected function getCapabilityForResource(string $resource, string $action): ?string
    {
        // Simple mapping for fallback capability checks
        $capabilityMap = [
            'modules:install' => 'manage_viraloka_modules',
            'modules:uninstall' => 'manage_viraloka_modules',
            'workspaces:create' => 'manage_viraloka_workspaces',
            'workspaces:delete' => 'manage_viraloka_workspaces',
        ];
        
        $key = $resource . ':' . $action;
        return $capabilityMap[$key] ?? null;
    }
    
    /**
     * Get the PolicyEngine instance
     * 
     * @return PolicyEngineContract|null
     */
    public function getPolicyEngine(): ?PolicyEngineContract
    {
        return $this->policyEngine;
    }
}
