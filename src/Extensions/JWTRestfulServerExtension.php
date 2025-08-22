<?php

namespace Tipbr\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\IdentityStore;
use Tipbr\Authentication\JWTRestfulAuthenticator;

/**
 * Extension for RestfulServer to handle JWT authentication
 * 
 * This extension hooks into RestfulServer's authentication process
 * to validate JWT tokens and set the current user context.
 */
class JWTRestfulServerExtension extends Extension
{
    /**
     * Hook into the authentication process before any RestfulServer action
     * 
     * @param HTTPRequest $request
     * @param string $action
     */
    public function onBeforeInit(HTTPRequest $request, $action = null)
    {
        // Only process API requests (you might want to adjust this condition)
        $isApiRequest = $this->isApiRequest($request);

        if (!$isApiRequest) {
            return;
        }

        // Try JWT authentication
        $authenticator = new JWTRestfulAuthenticator();
        $member = $authenticator->authenticate($request);

        if ($member) {
            // Set the current user for this request
            Injector::inst()->get(IdentityStore::class)->logIn($member);
            Security::setCurrentUser($member);
        }
    }

    /**
     * Add CORS headers to API responses
     * 
     * @param HTTPRequest $request
     * @param HTTPResponse $response
     */
    public function onAfterInit(HTTPRequest $request, HTTPResponse $response)
    {
        if ($this->isApiRequest($request)) {
            $response->addHeader('Access-Control-Allow-Origin', '*');
            $response->addHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->addHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

            // Handle preflight OPTIONS requests
            if ($request->httpMethod() === 'OPTIONS') {
                $response->setStatusCode(200);
                $response->setBody('');
                return $response;
            }
        }
    }

    /**
     * Determine if this is an API request
     * You might want to customize this logic based on your routing setup
     * 
     * @param HTTPRequest $request
     * @return bool
     */
    protected function isApiRequest(HTTPRequest $request): bool
    {
        $url = $request->getURL();

        // Customize these conditions based on your API routing
        return (
            strpos($url, 'api/') === 0 ||
            strpos($url, '/api/') !== false ||
            $request->getHeader('Content-Type') === 'application/json' ||
            $request->getHeader('Accept') === 'application/json'
        );
    }
}
