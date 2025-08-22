<?php

namespace Tipbr\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\ORM\DataExtension;
use Tipbr\DataObjects\PasswordResetRequest;

/**
 * Extension for Member to show password reset requests in the admin
 */
class MemberExtension extends DataExtension
{
    private static $has_many = [
        'PasswordResetRequests' => PasswordResetRequest::class,
    ];

    /**
     * Add gridfield showing password reset requests to the Member CMS fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner->ID) {
            $gridField = GridField::create(
                'PasswordResetRequests',
                'Password Reset Requests',
                $this->owner->PasswordResetRequests(),
                GridFieldConfig_RecordViewer::create()
            );

            $fields->addFieldToTab('Root.PasswordResets', $gridField);
        }
    }
}