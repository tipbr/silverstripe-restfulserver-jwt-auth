<?php

namespace Tipbr\Controllers;

use App\Models\Task;
use SilverStripe\ORM\DataObject;

/**
 * Example controller showing how to use ApiDataObject with the existing ApiController
 */
class TaskApiController extends ApiController
{
    private static $allowed_actions = [
        'index',
        'view', 
        'create',
        'update',
        'delete'
    ];    private static $url_handlers = [
        'GET ' => 'index',
        'GET $UUID' => 'view',
        'POST ' => 'create', 
        'PUT $UUID' => 'update',
        'DELETE $UUID' => 'delete'
    ];

    /**
     * List all tasks
     */
    public function index()
    {
        $this->ensureUserLoggedIn();
        $this->ensureGET();

        $tasks = Task::get();
        
        return $this->returnPaginated($tasks);
    }

    /**
     * View a specific task
     */
    public function view()
    {
        $this->ensureUserLoggedIn();
        $this->ensureGET();

        $task = $this->getTaskFromUrl();
        
        if (!$task->canView()) {
            return $this->httpError(403, 'Access denied');
        }

        return $this->success([
            'Task' => $task->toApi()
        ]);
    }

    /**
     * Create a new task
     */
    public function create()
    {
        $member = $this->ensureUserLoggedIn();
        $this->ensurePOST();

        $task = Task::create();
        
        if (!$task->canCreate()) {
            return $this->httpError(403, 'Access denied');
        }

        // Set the creator
        $task->CreatedByID = $member->ID;

        // Update from API data
        if ($task->updateFromApi($this->vars)) {
            return $this->success([
                'Task' => $task->toApi(),
                'message' => 'Task created successfully'
            ]);
        } else {
            return $this->failure([
                'message' => 'Failed to create task',
                'errors' => $task->validateApiData($this->vars)
            ]);
        }
    }

    /**
     * Update an existing task
     */
    public function update()
    {
        $this->ensureUserLoggedIn();
        $this->ensurePOST();

        $task = $this->getTaskFromUrl();
        
        if (!$task->canEdit()) {
            return $this->httpError(403, 'Access denied');
        }

        if ($task->updateFromApi($this->vars)) {
            return $this->success([
                'Task' => $task->toApi(),
                'message' => 'Task updated successfully'
            ]);
        } else {
            return $this->failure([
                'message' => 'Failed to update task',
                'errors' => $task->validateApiData($this->vars)
            ]);
        }
    }

    /**
     * Delete a task
     */
    public function delete()
    {
        $this->ensureUserLoggedIn();
        $this->ensureDelete();

        $task = $this->getTaskFromUrl();
        
        if (!$task->canDelete()) {
            return $this->httpError(403, 'Access denied');
        }

        $task->delete();

        return $this->success([
            'message' => 'Task deleted successfully'
        ]);
    }    /**
     * Get task from URL parameter using UUID
     */
    protected function getTaskFromUrl(): Task
    {
        $uuid = $this->request->param('UUID');
        
        if (!$uuid) {
            return $this->httpError(400, 'Invalid task UUID');
        }

        $task = Task::getByApiId($uuid);
        
        if (!$task) {
            return $this->httpError(404, 'Task not found');
        }

        return $task;
    }
}
