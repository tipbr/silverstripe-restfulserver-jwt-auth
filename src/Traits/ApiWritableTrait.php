<?php

namespace Tipbr\Traits;

use SilverStripe\Security\Permission;
use SilverStripe\Core\Convert;

/**
 * Trait that provides default implementation for ApiWritableDataObject interface
 */
trait ApiWritableTrait
{
    /**
     * @config
     * @var array Fields that can be written via API
     */
    private static $api_writable_fields = [];

    /**
     * @config
     * @var array Fields that are excluded from API writes
     */
    private static $api_exclude_writable_fields = ['ID', 'Created', 'LastEdited'];

    /**
     * @config
     * @var bool Whether to include all database fields as writable by default
     */
    private static $api_include_all_db_fields_writable = false;

    /**
     * @config
     * @var string Permission required to write via API
     */
    private static $api_write_permission = null;

    /**
     * @config
     * @var string Permission required to create via API
     */
    private static $api_create_permission = null;

    /**
     * @config
     * @var string Permission required to delete via API
     */
    private static $api_delete_permission = null;

    /**
     * Update the DataObject from API data
     */
    public function updateFromApi(array $data, array $context = []): bool
    {
        if (!$this->canApiWrite()) {
            return false;
        }

        $errors = $this->validateApiData($data);
        if (!empty($errors)) {
            return false;
        }

        $writableFields = $this->getApiWritableFields();
        
        foreach ($data as $fieldName => $value) {
            if (in_array($fieldName, $writableFields)) {
                $this->setApiFieldValue($fieldName, $value);
            }
        }

        try {
            $this->write();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the fields that can be written via API
     */
    public function getApiWritableFields(): array
    {
        $configFields = $this->config()->get('api_writable_fields') ?: [];
        $excludeFields = $this->config()->get('api_exclude_writable_fields') ?: [];
        
        // If specific fields are configured, use those
        if (!empty($configFields)) {
            return array_diff($configFields, $excludeFields);
        }

        // Otherwise, include all database fields if configured to do so
        if ($this->config()->get('api_include_all_db_fields_writable')) {
            $dbFields = array_keys($this->config()->get('db') ?: []);
            return array_diff($dbFields, $excludeFields);
        }

        return [];
    }

    /**
     * Validate API data before writing
     */
    public function validateApiData(array $data): array
    {
        $errors = [];
        $writableFields = $this->getApiWritableFields();
        
        foreach ($data as $fieldName => $value) {
            if (!in_array($fieldName, $writableFields)) {
                $errors[] = "Field '{$fieldName}' is not writable via API";
                continue;
            }

            // Validate field type if we have DB field definitions
            $dbFields = $this->config()->get('db') ?: [];
            if (isset($dbFields[$fieldName])) {
                $fieldType = $dbFields[$fieldName];
                if (!$this->validateFieldType($fieldName, $value, $fieldType)) {
                    $errors[] = "Invalid value for field '{$fieldName}'";
                }
            }
        }

        return $errors;
    }

    /**
     * Check if the current user can write this object via API
     */
    public function canApiWrite(): bool
    {
        $permission = $this->config()->get('api_write_permission');
        
        if ($permission) {
            return Permission::check($permission);
        }

        // Fall back to standard canEdit if available
        if (method_exists($this, 'canEdit')) {
            return $this->canEdit();
        }

        return true;
    }

    /**
     * Check if the current user can create this type of object via API
     */
    public function canApiCreate(): bool
    {
        $permission = $this->config()->get('api_create_permission');
        
        if ($permission) {
            return Permission::check($permission);
        }

        // Fall back to standard canCreate if available
        if (method_exists($this, 'canCreate')) {
            return $this->canCreate();
        }

        return true;
    }

    /**
     * Check if the current user can delete this object via API
     */
    public function canApiDelete(): bool
    {
        $permission = $this->config()->get('api_delete_permission');
        
        if ($permission) {
            return Permission::check($permission);
        }

        // Fall back to standard canDelete if available
        if (method_exists($this, 'canDelete')) {
            return $this->canDelete();
        }

        return true;
    }

    /**
     * Set a field value from API data with appropriate type conversion
     */
    protected function setApiFieldValue(string $fieldName, $value): void
    {
        $dbFields = $this->config()->get('db') ?: [];
        
        if (isset($dbFields[$fieldName])) {
            $fieldType = $dbFields[$fieldName];
            $value = $this->convertApiValueForField($value, $fieldType);
        }
        
        $this->setField($fieldName, $value);
    }

    /**
     * Convert API value to appropriate type for database field
     */
    protected function convertApiValueForField($value, string $fieldType)
    {
        $fieldType = strtolower($fieldType);
        
        if (strpos($fieldType, 'boolean') !== false) {
            return (bool) $value;
        }
        
        if (strpos($fieldType, 'int') !== false) {
            return (int) $value;
        }
        
        if (strpos($fieldType, 'float') !== false || strpos($fieldType, 'decimal') !== false) {
            return (float) $value;
        }
        
        if (strpos($fieldType, 'date') !== false) {
            if (is_string($value)) {
                return date('Y-m-d H:i:s', strtotime($value));
            }
        }
        
        return $value;
    }

    /**
     * Validate that a value is appropriate for a field type
     */
    protected function validateFieldType(string $fieldName, $value, string $fieldType): bool
    {
        $fieldType = strtolower($fieldType);
        
        if (strpos($fieldType, 'boolean') !== false) {
            return is_bool($value) || is_numeric($value) || in_array(strtolower($value), ['true', 'false']);
        }
        
        if (strpos($fieldType, 'int') !== false) {
            return is_numeric($value) && (int) $value == $value;
        }
        
        if (strpos($fieldType, 'float') !== false || strpos($fieldType, 'decimal') !== false) {
            return is_numeric($value);
        }
        
        if (strpos($fieldType, 'varchar') !== false) {
            if (preg_match('/varchar\((\d+)\)/', $fieldType, $matches)) {
                $maxLength = (int) $matches[1];
                return strlen($value) <= $maxLength;
            }
        }
        
        if (strpos($fieldType, 'text') !== false) {
            return is_string($value);
        }
        
        if (strpos($fieldType, 'date') !== false) {
            return is_string($value) && strtotime($value) !== false;
        }
        
        return true; // Default to valid for unknown types
    }
}
