<?php

namespace Tipbr\Authentication;

use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\IdentityStore;
use Tipbr\Services\JWTService;

/**
 * JWT Authenticator for RestfulServer
 * 
 * This authenticator validates JWT tokens from the Authorization header
 * and authenticates users for RestfulServer API endpoints.
 * 
 * Follows the same pattern as BasicRestfulAuthenticator with a static authenticate() method
 * that returns a Member object or null if authentication fails.
 */
class JWTRestfulAuthenticator
{
    /**
     * The authenticate function
     * 
     * Takes the JWT token from the Authorization header and attempts to authenticate a user
     * 
     * @return Member|null The Member object, or null if no member could be authenticated
     */
    public static function authenticate(): ?Member
    {
        $token = static::getBearerToken();

        if (!$token) {
            return null;
        }

        $jwtService = JWTService::singleton();

        if (!$jwtService->validateToken($token)) {
            return null;
        }

        $memberId = $jwtService->getMemberIdFromToken($token);
        if (!$memberId) {
            return null;
        }

        $member = Member::get()->byID($memberId);
        if (!$member) {
            return null;
        }

        // Set the current user in the security context for permission checks
        Injector::inst()->get(IdentityStore::class)->logIn($member);
        Security::setCurrentUser($member);

        // Optional: Renew token if needed and add to response headers
        try {
            $renewedToken = $jwtService->renewToken($token);
            if ($renewedToken !== $token) {
                // Add renewed token to response headers for client to use
                $controller = Controller::curr();
                if ($controller && $controller->getResponse()) {
                    $controller->getResponse()->addHeader('X-Renewed-Token', $renewedToken);
                }
            }
        } catch (\Exception $e) {
            // Token renewal failed, but we can still authenticate with the current token
            // Log this if needed - could add logging here in the future
        }

        return $member;
    }

    /**
     * Extract Bearer token from Authorization header
     * 
     * @return string|null
     */
    protected static function getBearerToken(): ?string
    {
        $header = static::getAuthorizationHeader();

        if (!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Get Authorization header from request
     * 
     * @return string
     */
    protected static function getAuthorizationHeader(): string
    {
        $header = '';

        // Try different ways to get the Authorization header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );

            if (isset($requestHeaders['Authorization'])) {
                $header = trim($requestHeaders['Authorization']);
            }
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $header = trim($value);
                    break;
                }
            }
        }

        return $header;
    }
}
