<?php

namespace App\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use Tipbr\Interfaces\ApiDataObject;
use Tipbr\Traits\ApiDataObjectTrait;

/**
 * Example DataObject showing how to use the API interface and trait
 */
class Task extends DataObject implements ApiDataObject
{
    use ApiDataObjectTrait;

    private static $table_name = 'Task';    private static $db = [
        'UUID' => 'Varchar(36)',
        'Title' => 'Varchar(255)',
        'Description' => 'Text',
        'IsCompleted' => 'Boolean',
        'DueDate' => 'Date',
        'Priority' => 'Enum("Low,Medium,High","Medium")'
    ];

    private static $indexes = [
        'UUID' => [
            'type' => 'unique',
            'columns' => ['UUID']
        ]
    ];

    private static $has_one = [
        'AssignedTo' => Member::class,
        'CreatedBy' => Member::class
    ];

    private static $has_many = [
        'Comments' => TaskComment::class
    ];    // API Configuration
    private static $api_use_uuid = true;
    private static $api_uuid_field = 'UUID';
    
    private static $api_fields = [
        'UUID',
        'Title', 
        'Description',
        'IsCompleted',
        'DueDate',
        'Priority',
        'AssignedTo',
        'CreatedBy',
        'Created',
        'LastEdited'
    ];

    private static $api_writable_fields = [
        'Title',
        'Description', 
        'IsCompleted',
        'DueDate',
        'Priority',
        'AssignedTo'
    ];

    private static $api_include_relations = false; // Don't include comments by default

    /**
     * Add computed fields to API output
     */
    protected function getApiComputedFields(array $context = []): array
    {
        return [
            'IsOverdue' => $this->getIsOverdue(),
            'DaysUntilDue' => $this->getDaysUntilDue(),
            'CommentCount' => $this->Comments()->count()
        ];
    }

    /**
     * Custom validation for API data
     */
    protected function validateApiDataCustom(array $data, array $context = []): array
    {
        $errors = [];

        // Validate title is not empty
        if (isset($data['Title']) && empty(trim($data['Title']))) {
            $errors[] = 'Title cannot be empty';
        }

        // Validate due date is not in the past for new tasks
        if (isset($data['DueDate']) && !$this->exists()) {
            $dueDate = strtotime($data['DueDate']);
            if ($dueDate && $dueDate < strtotime('today')) {
                $errors[] = 'Due date cannot be in the past';
            }
        }

        return $errors;
    }

    // SilverStripe permission methods (these control API access automatically)
    public function canView($member = null)
    {
        return Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canEdit($member = null)
    {
        // Users can edit their own tasks or if they have admin permissions
        if ($this->CreatedByID && $member && $this->CreatedByID == $member->ID) {
            return true;
        }
        
        return Permission::check('ADMIN', 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canDelete($member = null)
    {
        return Permission::check('ADMIN', 'any', $member);
    }

    // Helper methods
    public function getIsOverdue(): bool
    {
        if (!$this->DueDate || $this->IsCompleted) {
            return false;
        }
        
        return strtotime($this->DueDate) < strtotime('today');
    }

    public function getDaysUntilDue(): ?int
    {
        if (!$this->DueDate) {
            return null;
        }
        
        $dueDate = strtotime($this->DueDate);
        $today = strtotime('today');
          return (int) (($dueDate - $today) / (60 * 60 * 24));
    }

    /**
     * Generate UUID before writing
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->onBeforeWriteApiDataObject();
    }
}
