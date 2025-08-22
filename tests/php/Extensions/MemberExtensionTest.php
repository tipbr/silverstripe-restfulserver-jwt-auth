<?php

namespace Tipbr\Tests\Extensions;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Forms\GridField\GridField;
use Tipbr\DataObjects\PasswordResetRequest;

/**
 * Test MemberExtension functionality
 */
class MemberExtensionTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        PasswordResetRequest::class,
    ];

    protected static $required_extensions = [
        Member::class => [
            'Tipbr\Extensions\MemberExtension'
        ]
    ];

    public function testMemberHasPasswordResetRequestsRelationship()
    {
        // Create a test member
        $member = Member::create();
        $member->Email = 'test@example.com';
        $member->FirstName = 'Test';
        $member->Surname = 'User';
        $member->write();

        // Test that the relationship exists
        $this->assertTrue($member->hasMethod('PasswordResetRequests'), 'Member should have PasswordResetRequests method');
        
        // Test the relationship returns a DataList
        $requests = $member->PasswordResetRequests();
        $this->assertInstanceOf('SilverStripe\ORM\DataList', $requests, 'PasswordResetRequests should return a DataList');
    }

    public function testPasswordResetRequestsGridFieldInCMSFields()
    {
        // Create a test member
        $member = Member::create();
        $member->Email = 'test@example.com';
        $member->FirstName = 'Test';
        $member->Surname = 'User';
        $member->write();

        // Get CMS fields
        $fields = $member->getCMSFields();
        
        // Check that the PasswordResets tab exists
        $this->assertTrue($fields->hasTabSet(), 'Member should have a tabset');
        $this->assertNotNull($fields->fieldByName('Root.PasswordResets'), 'PasswordResets tab should exist');
        
        // Check that the gridfield exists
        $gridField = $fields->fieldByName('Root.PasswordResets.PasswordResetRequests');
        $this->assertInstanceOf(GridField::class, $gridField, 'PasswordResetRequests gridfield should exist');
        $this->assertEquals('Password Reset Requests', $gridField->getTitle(), 'GridField should have correct title');
    }

    public function testPasswordResetRequestsDisplayInGridField()
    {
        // Create a test member
        $member = Member::create();
        $member->Email = 'test@example.com';
        $member->FirstName = 'Test';
        $member->Surname = 'User';
        $member->write();

        // Create a password reset request for this member
        $request = PasswordResetRequest::create();
        $request->MemberID = $member->ID;
        $request->write();

        // Get the password reset requests through the relationship
        $requests = $member->PasswordResetRequests();
        $this->assertEquals(1, $requests->count(), 'Member should have one password reset request');
        $this->assertEquals($request->ID, $requests->first()->ID, 'Request should match the created one');
    }
}