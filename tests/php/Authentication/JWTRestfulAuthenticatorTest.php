<?php

namespace Tipbr\Tests\Authentication;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Security\Group;
use Tipbr\Authentication\JWTRestfulAuthenticator;
use Tipbr\Services\JWTService;

/**
 * Test JWT RestfulServer Authentication
 */
class JWTRestfulAuthenticatorTest extends SapphireTest
{
    protected static $fixture_file = 'JWTRestfulAuthenticatorTest.yml';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test member
        $member = new Member();
        $member->Email = 'test@example.com';
        $member->FirstName = 'Test';
        $member->Surname = 'User';
        $member->write();
    }

    public function testAuthenticateWithValidToken()
    {
        // Get the test member
        $member = Member::get()->filter('Email', 'test@example.com')->first();
        $this->assertNotNull($member, 'Test member should exist');

        // Generate a JWT token for the member
        $jwtService = JWTService::singleton();
        $token = $jwtService->generateToken($member);
        $this->assertNotEmpty($token, 'JWT token should be generated');

        // Mock the Authorization header with Bearer token
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // Call the static authenticate method
        $authenticatedMember = JWTRestfulAuthenticator::authenticate();

        // Verify authentication succeeded
        $this->assertNotNull($authenticatedMember, 'Member should be authenticated');
        $this->assertEquals($member->ID, $authenticatedMember->ID, 'Authenticated member should match original member');
        $this->assertEquals($member->Email, $authenticatedMember->Email, 'Email should match');

        // Verify the current user is set in Security context
        $currentUser = Security::getCurrentUser();
        $this->assertNotNull($currentUser, 'Current user should be set');
        $this->assertEquals($member->ID, $currentUser->ID, 'Current user should match authenticated member');
    }

    public function testAuthenticateWithInvalidToken()
    {
        // Set an invalid JWT token
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid.token.here';

        // Call the static authenticate method
        $authenticatedMember = JWTRestfulAuthenticator::authenticate();

        // Verify authentication failed
        $this->assertNull($authenticatedMember, 'Authentication should fail with invalid token');
    }

    public function testAuthenticateWithNoToken()
    {
        // Remove any authorization header
        unset($_SERVER['HTTP_AUTHORIZATION']);

        // Call the static authenticate method
        $authenticatedMember = JWTRestfulAuthenticator::authenticate();

        // Verify authentication failed
        $this->assertNull($authenticatedMember, 'Authentication should fail with no token');
    }

    public function testAuthenticateWithMalformedToken()
    {
        // Set a malformed authorization header
        $_SERVER['HTTP_AUTHORIZATION'] = 'NotBearer token';

        // Call the static authenticate method
        $authenticatedMember = JWTRestfulAuthenticator::authenticate();

        // Verify authentication failed
        $this->assertNull($authenticatedMember, 'Authentication should fail with malformed authorization header');
    }

    public function testAuthenticateWithExpiredToken()
    {
        // Get the test member
        $member = Member::get()->filter('Email', 'test@example.com')->first();
        
        // Generate an expired token by manipulating the JWT service temporarily
        $jwtService = JWTService::singleton();
        
        // Temporarily set a very short lifetime to create an expired token
        $originalLifetime = $jwtService->config()->get('lifetime');
        $jwtService->config()->set('lifetime', -1); // Expired immediately
        
        $expiredToken = $jwtService->generateToken($member);
        
        // Restore original lifetime
        $jwtService->config()->set('lifetime', $originalLifetime);
        
        // Mock the Authorization header with expired token
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $expiredToken;

        // Call the static authenticate method
        $authenticatedMember = JWTRestfulAuthenticator::authenticate();

        // Verify authentication failed
        $this->assertNull($authenticatedMember, 'Authentication should fail with expired token');
    }

    protected function tearDown(): void
    {
        // Clean up $_SERVER variables
        unset($_SERVER['HTTP_AUTHORIZATION']);
        
        // Clear current user
        Security::setCurrentUser(null);
        
        parent::tearDown();
    }
}