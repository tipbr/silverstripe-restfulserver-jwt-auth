<?php

namespace Tipbr\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Extension for RestfulServer to handle CORS and other API functionality
 * 
 * Note: Authentication is now handled directly by RestfulServer using JWTRestfulAuthenticator
 */
class JWTRestfulServerExtension extends Extension
{
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
