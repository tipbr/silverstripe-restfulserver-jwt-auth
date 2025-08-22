<?php

namespace Tipbr\Interfaces;

/**
 * Interface for DataObjects that can be exposed via the REST API
 * 
 * This interface provides a consistent way to:
 * - Serialize objects to API format
 * - Update objects from API data
 * - Control which fields are exposed/writable
 * - Validate API data
 * 
 * Permission checking relies on SilverStripe's built-in canView(), canEdit(), canCreate(), canDelete()
 */
interface ApiDataObject
{
    /**
     * Convert the object to an array suitable for API output
     * 
     * @param array $context Additional context for serialization (e.g. current user, request info)
     * @return array
     */
    public function toApi(array $context = []): array;

    /**
     * Update the object from API input data
     * 
     * @param array $data The input data from the API request
     * @param array $context Additional context (e.g. current user, request info)
     * @return bool Success/failure
     */
    public function updateFromApi(array $data, array $context = []): bool;

    /**
     * Get the fields that should be included in API output
     * 
     * @param array $context Additional context
     * @return array Field names to include
     */
    public function getApiFields(array $context = []): array;

    /**
     * Get the fields that can be written via API
     * 
     * @param array $context Additional context
     * @return array Field names that can be written
     */
    public function getApiWritableFields(array $context = []): array;    /**
     * Validate API input data before updating
     * 
     * @param array $data The input data to validate
     * @param array $context Additional context
     * @return array Array of validation errors (empty if valid)
     */
    public function validateApiData(array $data, array $context = []): array;

    /**
     * Get the API identifier for this object (UUID or ID)
     * 
     * @return string The API identifier
     */
    public function getApiId(): string;
}
