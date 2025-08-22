<?php

namespace Tipbr\Traits;

use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Security;

/**
 * Trait that provides default implementation for ApiDataObject interface
 * 
 * This trait leverages SilverStripe's existing permission system:
 * - Uses canView() to control API read access
 * - Uses canEdit() to control API write access  
 * - Uses canCreate() for new object creation
 * - Uses canDelete() for object deletion
 */
trait ApiDataObjectTrait
{
    /**
     * @config
     * @var array Fields to include in API output (empty = include all DB fields)
     */
    private static $api_fields = [];

    /**
     * @config
     * @var array Fields to exclude from API output
     */
    private static $api_exclude_fields = ['ClassName'];

    /**
     * @config  
     * @var array Fields that can be written via API (empty = include all DB fields)
     */
    private static $api_writable_fields = [];

    /**
     * @config
     * @var array Fields to exclude from API writes
     */
    private static $api_exclude_writable_fields = ['ID', 'Created', 'LastEdited', 'ClassName'];

    /**
     * @config
     * @var bool Whether to include has_one relationships in API output
     */
    private static $api_include_has_one = true;    /**
     * @config
     * @var bool Whether to include has_many/many_many relationships in API output
     */
    private static $api_include_relations = false;

    /**
     * @config
     * @var bool Whether to use UUID for API identification instead of ID
     */
    private static $api_use_uuid = true;

    /**
     * @config
     * @var string The field name that contains the UUID
     */
    private static $api_uuid_field = 'UUID';    /**
     * Convert the object to an array suitable for API output
     */
    public function toApi(array $context = []): array
    {
        if (!$this->canView()) {
            return [];
        }

        $data = [];
        $fields = $this->getApiFields($context);

        foreach ($fields as $fieldName) {
            $data[$fieldName] = $this->getApiFieldValue($fieldName, $context);
        }

        // Use UUID as the primary identifier in API output if configured
        if ($this->config()->get('api_use_uuid') && $this->hasField($this->config()->get('api_uuid_field'))) {
            $uuidField = $this->config()->get('api_uuid_field');
            $data['id'] = $this->getField($uuidField);
            
            // Remove the UUID field from the main data if it's there
            unset($data[$uuidField]);
        } else {
            // Fall back to using ID
            $data['id'] = $this->ID;
        }

        // Add any computed/virtual fields
        $computed = $this->getApiComputedFields($context);
        if (!empty($computed)) {
            $data = array_merge($data, $computed);
        }

        return $data;
    }

    /**
     * Update the object from API input data
     */
    public function updateFromApi(array $data, array $context = []): bool
    {
        if (!$this->canEdit()) {
            return false;
        }

        $errors = $this->validateApiData($data, $context);
        if (!empty($errors)) {
            return false;
        }

        $writableFields = $this->getApiWritableFields($context);
        
        foreach ($data as $fieldName => $value) {
            if (in_array($fieldName, $writableFields)) {
                $this->setApiFieldValue($fieldName, $value, $context);
            }
        }        try {
            $this->write();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure UUID is generated before writing (if configured)
     * Override this method or call it from your DataObject's onBeforeWrite
     */
    public function onBeforeWriteApiDataObject(): void
    {
        $this->ensureUUID();
    }

    /**
     * Get the fields that should be included in API output
     */
    public function getApiFields(array $context = []): array
    {
        $configFields = $this->config()->get('api_fields') ?: [];
        $excludeFields = $this->config()->get('api_exclude_fields') ?: [];
        
        // If specific fields are configured, use those
        if (!empty($configFields)) {
            return array_diff($configFields, $excludeFields);
        }        // Otherwise, build from DB fields
        $fields = []; // Don't automatically include ID since we'll use UUID
        
        // Add database fields
        $dbFields = array_keys($this->config()->get('db') ?: []);
        $fields = array_merge($fields, $dbFields);

        // Add has_one relationships if configured
        if ($this->config()->get('api_include_has_one')) {
            $hasOneFields = array_keys($this->config()->get('has_one') ?: []);
            $fields = array_merge($fields, $hasOneFields);
            
            // Also include the ID fields
            foreach ($hasOneFields as $relation) {
                $fields[] = $relation . 'ID';
            }
        }

        // Add has_many/many_many if configured  
        if ($this->config()->get('api_include_relations')) {
            $hasManyFields = array_keys($this->config()->get('has_many') ?: []);
            $manyManyFields = array_keys($this->config()->get('many_many') ?: []);
            $fields = array_merge($fields, $hasManyFields, $manyManyFields);
        }

        return array_diff(array_unique($fields), $excludeFields);
    }

    /**
     * Get the fields that can be written via API
     */
    public function getApiWritableFields(array $context = []): array
    {
        $configFields = $this->config()->get('api_writable_fields') ?: [];
        $excludeFields = $this->config()->get('api_exclude_writable_fields') ?: [];
        
        // If specific fields are configured, use those
        if (!empty($configFields)) {
            return array_diff($configFields, $excludeFields);
        }

        // Otherwise, include all database fields except excluded ones
        $dbFields = array_keys($this->config()->get('db') ?: []);
        
        // Add has_one relationships (but not the ID fields - those are handled automatically)
        $hasOneFields = array_keys($this->config()->get('has_one') ?: []);
        
        $writableFields = array_merge($dbFields, $hasOneFields);
        
        return array_diff($writableFields, $excludeFields);
    }

    /**
     * Validate API input data before updating
     */
    public function validateApiData(array $data, array $context = []): array
    {
        $errors = [];
        $writableFields = $this->getApiWritableFields($context);
        
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

        // Allow subclasses to add custom validation
        $customErrors = $this->validateApiDataCustom($data, $context);
        if (!empty($customErrors)) {
            $errors = array_merge($errors, $customErrors);
        }

        return $errors;
    }

    /**
     * Get the value of a field for API output
     */
    protected function getApiFieldValue(string $fieldName, array $context = [])
    {
        // Handle relationships
        if ($this->hasRelation($fieldName)) {
            $relation = $this->getComponent($fieldName);
            if ($relation && $relation->exists()) {
                if ($relation instanceof ApiDataObject) {
                    return $relation->toApi($context);
                } else {
                    return $relation->toMap();
                }
            }
            return null;
        }

        // Handle has_many/many_many if requested
        $hasManyFields = array_keys($this->config()->get('has_many') ?: []);
        $manyManyFields = array_keys($this->config()->get('many_many') ?: []);
        
        if (in_array($fieldName, array_merge($hasManyFields, $manyManyFields))) {
            $relations = $this->getComponents($fieldName);
            $output = [];
            
            foreach ($relations as $relation) {
                if ($relation instanceof ApiDataObject) {
                    $output[] = $relation->toApi($context);
                } else {
                    $output[] = $relation->toMap();
                }
            }
            
            return $output;
        }

        // Regular field value
        return $this->getField($fieldName);
    }

    /**
     * Set a field value from API data with appropriate type conversion
     */
    protected function setApiFieldValue(string $fieldName, $value, array $context = []): void
    {
        // Handle has_one relationships
        $hasOneFields = array_keys($this->config()->get('has_one') ?: []);
        if (in_array($fieldName, $hasOneFields)) {
            // Set the ID field
            $this->setField($fieldName . 'ID', $value);
            return;
        }

        // Handle regular database fields
        $dbFields = $this->config()->get('db') ?: [];
        if (isset($dbFields[$fieldName])) {
            $fieldType = $dbFields[$fieldName];
            $value = $this->convertApiValueForField($value, $fieldType);
        }
        
        $this->setField($fieldName, $value);
    }

    /**
     * Get computed/virtual fields for API output
     * Override this in your DataObject to add computed fields
     */
    protected function getApiComputedFields(array $context = []): array
    {
        return [];
    }

    /**
     * Custom validation logic - override in your DataObject
     */
    protected function validateApiDataCustom(array $data, array $context = []): array
    {
        return [];
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
    }    /**
     * Check if this object has a relation with the given name
     */
    protected function hasRelation(string $name): bool
    {
        $hasOne = $this->config()->get('has_one') ?: [];
        $hasMany = $this->config()->get('has_many') ?: [];
        $manyMany = $this->config()->get('many_many') ?: [];
        
        return isset($hasOne[$name]) || isset($hasMany[$name]) || isset($manyMany[$name]);
    }

    /**
     * Get the API identifier for this object (UUID or ID)
     */
    public function getApiId(): string
    {
        if ($this->config()->get('api_use_uuid') && $this->hasField($this->config()->get('api_uuid_field'))) {
            $uuidField = $this->config()->get('api_uuid_field');
            return $this->getField($uuidField);
        }
        
        return (string) $this->ID;
    }

    /**
     * Find an object by its API identifier (UUID or ID)
     */
    public static function getByApiId(string $identifier)
    {
        $singleton = singleton(static::class);
        
        if ($singleton->config()->get('api_use_uuid') && $singleton->hasField($singleton->config()->get('api_uuid_field'))) {
            $uuidField = $singleton->config()->get('api_uuid_field');
            return static::get()->filter($uuidField, $identifier)->first();
        }
        
        // Fall back to ID lookup
        if (is_numeric($identifier)) {
            return static::get()->byID($identifier);
        }
        
        return null;
    }

    /**
     * Generate a UUID for this object if it doesn't have one
     */
    public function ensureUUID(): void
    {
        if (!$this->config()->get('api_use_uuid')) {
            return;
        }

        $uuidField = $this->config()->get('api_uuid_field');
        
        if ($this->hasField($uuidField) && !$this->getField($uuidField)) {
            $this->setField($uuidField, $this->generateUUID());
        }
    }

    /**
     * Generate a UUID v4
     */
    protected function generateUUID(): string
    {
        return Uuid::uuid4()->toString();
    }
}
