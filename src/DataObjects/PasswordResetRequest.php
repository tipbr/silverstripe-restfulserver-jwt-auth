<?php

namespace Tipbr\DataObjects;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Forms\ReadonlyField;

// TODO: should be configurable. Length, expiry time, auto delete etc.

class PasswordResetRequest extends DataObject
{
    private static $table_name = 'PasswordResetRequest';

    private static $db = [
        "Code" => "Varchar(6)",
        "Created" => "Datetime",
        "Expired" => 'Boolean',
    ];

    private static $has_one = [
        "Member" => Member::class,
    ];

    private static $summary_fields = [
        "Member.Email" => "Email",
        "Code" => "Code",
        "Created" => "Created",
        "ExpiryStatus" => "Status"
    ];

    private static $default_sort = "Created DESC";

    // make the fields all readoly
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(['MemberID', 'Code', 'Created']);

        $fields->addFieldsToTab('Root.Main', [
            ReadonlyField::create('MemberEmail', 'Member Email', $this->Member()->Email),
            ReadonlyField::create('Code', 'Reset Code', $this->Code),
            ReadonlyField::create('Created', 'Created', $this->Created),
            ReadonlyField::create('ExpiryTime', 'Expires', $this->getExpiryTime()),
            ReadonlyField::create('ExpiryStatus', 'Status', $this->getExpiryStatus()),
        ]);

        return $fields;
    }

    /**
     * Get the expiry time (1 hour after creation)
     *
     * @return string
     */
    public function getExpiryTime()
    {
        if ($this->Created) {
            return date('Y-m-d H:i:s', strtotime($this->Created) + 3600);
        }
        return '';
    }

    /**
     * Check if the request is expired
     *
     * @return boolean
     */
    public function isExpired()
    {
        if ($this->Created) {
            return (time() > (strtotime($this->Created) + 3600));
        }
        return false;
    }

    /**
     * Get human-readable expiry status
     *
     * @return string
     */
    public function getExpiryStatus()
    {
        return $this->isExpired() ? 'Expired' : 'Active';
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Check if member has any other active requests and delete them.
        $existingRequests = PasswordResetRequest::get()->filter([
            "MemberID" => $this->MemberID
        ]);

        foreach ($existingRequests as $request) {
            $request->delete();
        }

        if (!$this->Created) {
            $this->Created = date("Y-m-d H:i:s");
        }

        if (!$this->Code) {
            $this->Code = $this->generateCode();
        }

        $this->sendPasswordResetEmail();
    }

    private function generateCode()
    {
        return rand(100000, 999999);
    }

    private function sendPasswordResetEmail()
    {
        $email = $this->Member()->Email;
        $subject = "Password Reset Request";
        $message = "Your password reset code is: " . $this->Code;

        mail($email, $subject, $message);
    }
}