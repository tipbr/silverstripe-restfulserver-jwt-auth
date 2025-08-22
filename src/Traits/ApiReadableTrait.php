<?php

namespace Tipbr\Traits;

use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Core\Config\Config;

/**
 * Trait that provides default implementation for ApiReadableDataObject interface
 */
trait ApiReadableTrait
{
    /**
     * @config
     * @var array Fields to expose in API responses
     */
    private static $api_fields = [];

    /**
     * @config
     * @var array Relations to include in API responses  
     */
    private static $api_relations = [];

    /**
     * @config
     * @var array Computed fields to include in API responses
     */
    private static $api_computed_fields = [];

    /**
     * @config
     * @var array Fields to exclude from API responses
     */
    private static $api_exclude_fields = [];

    /**
     * @config
     * @var bool Whether to include all database fields by default
     */
    private static $api_include_all_db_fields = true;

    /**
     * @config
     * @var string Permission required to read via API (null = no permission check)
     */
    private static $api_read_permission = null;

    /**
     * Convert the DataObject to an array suitable for API responses
     */
    public function toApi(array $context = []): array
    {
        if (!$this->canApiRead()) {
            return [];
        }

        $result = [];
        
        // Always include ID if it exists
        if ($this->ID) {
            $result['ID'] = $this->ID;
        }

        // Include database fields
        foreach ($this->getApiFields() as $fieldName) {
            $result[$fieldName] = $this->getApiFieldValue($fieldName);
        }

        // Include relations
        foreach ($this->getApiRelations() as $relationName => $config) {
            $result[$relationName] = $this->getApiRelationValue($relationName, $config, $context);
        }

        // Include computed fields
        foreach ($this->getApiComputedFields() as $fieldName => $method) {
            if (method_exists($this, $method)) {
                $result[$fieldName] = $this->$method();
            } elseif (is_callable($method)) {
                $result[$fieldName] = $method($this);
            }
        }

        return $result;
    }

    /**
     * Get the fields that should be included in API responses
     */
    public function getApiFields(): array
    {
        $configFields = $this->config()->get('api_fields') ?: [];
        $excludeFields = $this->config()->get('api_exclude_fields') ?: [];
        
        // If specific fields are configured, use those
        if (!empty($configFields)) {
            return array_diff($configFields, $excludeFields);
        }

        // Otherwise, include all database fields if configured to do so
        if ($this->config()->get('api_include_all_db_fields')) {
            $dbFields = array_keys($this->config()->get('db') ?: []);
            return array_diff($dbFields, $excludeFields);
        }

        return [];
    }

    /**
     * Get the relations that should be included in API responses
     */
    public function getApiRelations(): array
    {
        return $this->config()->get('api_relations') ?: [];
    }

    /**
     * Get computed/virtual fields for API responses
     */
    public function getApiComputedFields(): array
    {
        return $this->config()->get('api_computed_fields') ?: [];
    }

    /**
     * Check if the current user can read this object via API
     */
    public function canApiRead(): bool
    {
        $permission = $this->config()->get('api_read_permission');
        
        if ($permission) {
            return Permission::check($permission);
        }

        // Fall back to standard canView if available
        if (method_exists($this, 'canView')) {
            return $this->canView();
        }

        return true;
    }

    /**
     * Get the formatted value for an API field
     */
    protected function getApiFieldValue(string $fieldName)
    {
        $value = $this->getField($fieldName);
        
        // Handle DBField objects
        if ($value instanceof DBField) {
            // For dates, return ISO format
            if ($value instanceof \SilverStripe\ORM\FieldType\DBDate || 
                $value instanceof \SilverStripe\ORM\FieldType\DBDatetime) {
                return $value->Rfc2822();
            }
            
            // For other DBFields, return the raw value
            return $value->getValue();
        }
        
        return $value;
    }

    /**
     * Get the value for an API relation
     */
    protected function getApiRelationValue(string $relationName, $config, array $context = [])
    {
        $relation = $this->getComponent($relationName);
        
        if (!$relation || !$relation->exists()) {
            return null;
        }

        // If it's a simple string config, just return the ID
        if (is_string($config)) {
            return $relation->ID;
        }

        // If relation implements ApiReadableDataObject, use its toApi method
        if ($relation instanceof \Tipbr\Interfaces\ApiReadableDataObject) {
            // Check for nested context to prevent infinite loops
            $relationKey = get_class($relation) . ':' . $relation->ID;
            if (isset($context['_processed']) && in_array($relationKey, $context['_processed'])) {
                return ['ID' => $relation->ID];
            }
            
            $newContext = $context;
            $newContext['_processed'][] = $relationKey;
            
            return $relation->toApi($newContext);
        }

        // For has_many or many_many relations
        if ($relation instanceof \SilverStripe\ORM\SS_List) {
            $items = [];
            foreach ($relation as $item) {
                if ($item instanceof \Tipbr\Interfaces\ApiReadableDataObject) {
                    $itemKey = get_class($item) . ':' . $item->ID;
                    if (!isset($context['_processed']) || !in_array($itemKey, $context['_processed'])) {
                        $newContext = $context;
                        $newContext['_processed'][] = $itemKey;
                        $items[] = $item->toApi($newContext);
                    }
                } else {
                    $items[] = ['ID' => $item->ID];
                }
            }
            return $items;
        }

        // Fallback: return basic info
        return [
            'ID' => $relation->ID,
            'ClassName' => $relation->ClassName
        ];
    }
}
