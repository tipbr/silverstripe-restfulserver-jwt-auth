<?php

namespace Tipbr\Controllers;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use Tipbr\Interfaces\ApiReadableDataObject;
use Tipbr\Interfaces\ApiWritableDataObject;

/**
 * Generic API Controller for DataObjects
 * 
 * Provides standard REST endpoints for any DataObject that implements
 * ApiReadableDataObject and/or ApiWritableDataObject
 */
class DataApiController extends ApiController
{
    private static $allowed_actions = [
        'index',     // GET /api/crud/{ClassName} - List objects
        'show',      // GET /api/crud/{ClassName}/{ID} - Get specific object
        'add',    // POST /api/crud/{ClassName} - Create new object
        'update',    // PUT /api/crud/{ClassName}/{ID} - Update object
        'delete',    // DELETE /api/crud/{ClassName}/{ID} - Delete object
    ];

    private static $url_handlers = [
        '$ClassName/$ID/$Action' => '$Action',
        '$ClassName/$ID' => 'show',
        '$ClassName' => 'index'
    ];

    /**
     * List objects of a given class
     * GET /api/crud/{ClassName}
     */
    public function index()
    {
        $this->ensureGET();
        
        $className = $this->getClassName();
        if (!$className) {
            return $this->httpError(400, 'Class name required');
        }

        if (!$this->isValidApiClass($className, 'read')) {
            return $this->httpError(403, 'Class not accessible via API');
        }

        $list = DataList::create($className);
        
        // Apply filters from query parameters
        $list = $this->applyFilters($list);
        
        // Apply sorting
        $list = $this->applySorting($list);

        return $this->returnPaginated($list);
    }

    /**
     * Get a specific object
     * GET /api/crud/{ClassName}/{ID}
     */
    public function show()
    {
        $this->ensureGET();
        
        $className = $this->getClassName();
        $id = $this->getID();
        
        if (!$className || !$id) {
            return $this->httpError(400, 'Class name and ID required');
        }

        if (!$this->isValidApiClass($className, 'read')) {
            return $this->httpError(403, 'Class not accessible via API');
        }

        $object = DataObject::get($className)->byID($id);
        if (!$object) {
            return $this->httpError(404, 'Object not found');
        }

        if ($object instanceof ApiReadableDataObject && !$object->canApiRead()) {
            return $this->httpError(403, 'Not authorized to read this object');
        }

        if ($object instanceof ApiReadableDataObject) {
            return $this->success(['data' => $object->toApi()]);
        }

        return $this->httpError(500, 'Object does not implement ApiReadableDataObject');
    }

    /**
     * Add a new object
     * POST /api/crud/{ClassName}
     */
    public function add()
    {
        $this->ensurePOST();
        
        $className = $this->getClassName();
        if (!$className) {
            return $this->httpError(400, 'Class name required');
        }

        if (!$this->isValidApiClass($className, 'write')) {
            return $this->httpError(403, 'Class not writable via API');
        }

        $object = $className::create();
        
        if (!($object instanceof ApiWritableDataObject)) {
            return $this->httpError(500, 'Class does not implement ApiWritableDataObject');
        }

        if (!$object->canApiCreate()) {
            return $this->httpError(403, 'Not authorized to add this object type');
        }

        $data = $this->getJsonData();
        if (!$data) {
            return $this->httpError(400, 'JSON data required');
        }

        $errors = $object->validateApiData($data);
        if (!empty($errors)) {
            return $this->failure(['errors' => $errors]);
        }

        if (!$object->updateFromApi($data)) {
            return $this->httpError(500, 'Failed to add object');
        }

        if ($object instanceof ApiReadableDataObject) {
            return $this->success(['data' => $object->toApi()]);
        }

        return $this->success(['id' => $object->ID]);
    }

    /**
     * Update an existing object
     * PUT /api/crud/{ClassName}/{ID}
     */
    public function update()
    {
        if (!$this->request->isPUT() && !$this->request->isPOST()) {
            return $this->httpError(400, 'PUT or POST request required');
        }
        
        $className = $this->getClassName();
        $id = $this->getID();
        
        if (!$className || !$id) {
            return $this->httpError(400, 'Class name and ID required');
        }

        if (!$this->isValidApiClass($className, 'write')) {
            return $this->httpError(403, 'Class not writable via API');
        }

        $object = DataObject::get($className)->byID($id);
        if (!$object) {
            return $this->httpError(404, 'Object not found');
        }

        if (!($object instanceof ApiWritableDataObject)) {
            return $this->httpError(500, 'Object does not implement ApiWritableDataObject');
        }

        if (!$object->canApiWrite()) {
            return $this->httpError(403, 'Not authorized to update this object');
        }

        $data = $this->getJsonData();
        if (!$data) {
            return $this->httpError(400, 'JSON data required');
        }

        $errors = $object->validateApiData($data);
        if (!empty($errors)) {
            return $this->failure(['errors' => $errors]);
        }

        if (!$object->updateFromApi($data)) {
            return $this->httpError(500, 'Failed to update object');
        }

        if ($object instanceof ApiReadableDataObject) {
            return $this->success(['data' => $object->toApi()]);
        }

        return $this->success(['id' => $object->ID]);
    }

    /**
     * Delete an object
     * DELETE /api/crud/{ClassName}/{ID}
     */
    public function delete()
    {
        $this->ensureDelete();
        
        $className = $this->getClassName();
        $id = $this->getID();
        
        if (!$className || !$id) {
            return $this->httpError(400, 'Class name and ID required');
        }

        if (!$this->isValidApiClass($className, 'write')) {
            return $this->httpError(403, 'Class not writable via API');
        }

        $object = DataObject::get($className)->byID($id);
        if (!$object) {
            return $this->httpError(404, 'Object not found');
        }

        if ($object instanceof ApiWritableDataObject && !$object->canApiDelete()) {
            return $this->httpError(403, 'Not authorized to delete this object');
        }

        try {
            $object->delete();
            return $this->success(['deleted' => true]);
        } catch (\Exception $e) {
            return $this->httpError(500, 'Failed to delete object');
        }
    }

    /**
     * Get the class name from the URL
     */
    protected function getClassName(): ?string
    {
        $className = $this->request->param('ClassName');
        
        if (!$className) {
            return null;
        }

        // Security check: ensure class exists and is a DataObject
        if (!class_exists($className) || !is_subclass_of($className, DataObject::class)) {
            return null;
        }

        return $className;
    }

    /**
     * Get the ID from the URL
     */
    protected function getID(): ?int
    {
        $id = $this->request->param('ID');
        return $id ? (int) $id : null;
    }

    /**
     * Check if a class is valid for API access
     */
    protected function isValidApiClass(string $className, string $operation = 'read'): bool
    {
        if (!class_exists($className) || !is_subclass_of($className, DataObject::class)) {
            return false;
        }

        if ($operation === 'read') {
            return is_subclass_of($className, ApiReadableDataObject::class) || 
                   in_array(ApiReadableDataObject::class, class_implements($className));
        }

        if ($operation === 'write') {
            return is_subclass_of($className, ApiWritableDataObject::class) || 
                   in_array(ApiWritableDataObject::class, class_implements($className));
        }

        return false;
    }

    /**
     * Get JSON data from the request body
     */
    protected function getJsonData(): ?array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }

    /**
     * Apply filters from query parameters to a DataList
     */
    protected function applyFilters(DataList $list): DataList
    {
        $filters = $this->request->getVar('filter');
        if (!$filters || !is_array($filters)) {
            return $list;
        }

        foreach ($filters as $field => $value) {
            // Basic security: only allow filtering on valid field names
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                $list = $list->filter($field, $value);
            }
        }

        return $list;
    }

    /**
     * Apply sorting from query parameters to a DataList
     */
    protected function applySorting(DataList $list): DataList
    {
        $sort = $this->request->getVar('sort');
        if (!$sort) {
            return $list;
        }

        // Basic security: only allow sorting on valid field names
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?$/i', $sort)) {
            $list = $list->sort($sort);
        }

        return $list;
    }
}
