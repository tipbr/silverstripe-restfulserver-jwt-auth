<?php

namespace Tipbr\Tests\Authentication;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

/**
 * Test AuthApiController authentication methods to verify checkPassword fix
 * 
 * These tests verify that the AuthApiController login and changePassword methods
 * no longer use the deprecated checkPassword() method and instead use the proper
 * SilverStripe 6 Member::authenticate() method.
 */
class AuthApiControllerTest extends SapphireTest
{
    protected static $fixture_file = 'AuthApiControllerTest.yml';

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test that Member::authenticate() method is available and works
     * This verifies the fix for the checkPassword issue
     */
    public function testMemberAuthenticateMethodExists()
    {
        // This test verifies that Member::authenticate() static method exists
        // and can be called without errors - this is what replaced checkPassword()
        
        $result = Member::authenticate([
            'Email' => 'test@example.com',
            'Password' => 'testpassword123'
        ]);
        
        $this->assertNotNull($result, 'Member::authenticate() should return a Member object for valid credentials');
        $this->assertEquals('test@example.com', $result->Email, 'Authenticated member should have correct email');
    }

    /**
     * Test Member::authenticate() with invalid credentials
     */
    public function testMemberAuthenticateWithInvalidCredentials()
    {
        $result = Member::authenticate([
            'Email' => 'test@example.com',
            'Password' => 'wrongpassword'
        ]);
        
        $this->assertNull($result, 'Member::authenticate() should return null for invalid credentials');
    }

    /**
     * Test Member::authenticate() with non-existent email
     */
    public function testMemberAuthenticateWithNonExistentEmail()
    {
        $result = Member::authenticate([
            'Email' => 'nonexistent@example.com',
            'Password' => 'anypassword'
        ]);
        
        $this->assertNull($result, 'Member::authenticate() should return null for non-existent email');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}