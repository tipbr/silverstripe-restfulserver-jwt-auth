<?php

namespace Tipbr\Authentication;

use SilverStripe\Security\Member;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\RestfulServer\Authenticator\RestfulAuthenticator;
use Tipbr\Services\JWTService;

/**
 * JWT Authenticator for RestfulServer
 * 
 * This authenticator validates JWT tokens from the Authorization header
 * and authenticates users for RestfulServer API endpoints.
 */
class JWTRestfulAuthenticator implements RestfulAuthenticator
{
    /**
     * Authenticate a user from the current request
     * 
     * @param HTTPRequest $request
     * @return Member|null Returns the authenticated member or null if authentication fails
     */
    public function authenticate(HTTPRequest $request): ?Member
    {
        $token = $this->getBearerToken($request);

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

        // Optional: Renew token if needed and add to response headers
        try {
            $renewedToken = $jwtService->renewToken($token);
            if ($renewedToken !== $token) {
                // Add renewed token to response headers for client to use
                $response = $request->getSession()->getResponse();
                if ($response) {
                    $response->addHeader('X-Renewed-Token', $renewedToken);
                }
            }
        } catch (\Exception $e) {
            // Token renewal failed, but we can still authenticate with the current token
            // Log this if needed
        }

        return $member;
    }

    /**
     * Extract Bearer token from Authorization header
     * 
     * @param HTTPRequest $request
     * @return string|null
     */
    protected function getBearerToken(HTTPRequest $request): ?string
    {
        $header = $this->getAuthorizationHeader($request);

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
     * @param HTTPRequest $request
     * @return string
     */
    protected function getAuthorizationHeader(HTTPRequest $request): string
    {
        $header = '';

        if ($auth = $request->getHeader('Authorization')) {
            $header = trim($auth);
        } elseif ($auth = $request->getHeader('HTTP_AUTHORIZATION')) {
            $header = trim($auth);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );

            if (isset($requestHeaders['Authorization'])) {
                $header = trim($requestHeaders['Authorization']);
            }
        }

        return $header;
    }
}
