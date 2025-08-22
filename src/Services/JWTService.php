<?php

namespace Tipbr\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Control\Director;
use SilverStripe\Security\Member;

/**
 * JWT Service for handling token creation, validation, and renewal
 */
class JWTService
{
    use Injectable, Configurable;

    /**
     * @config
     * @var string JWT secret key
     */
    private static $secret = null;

    /**
     * @config  
     * @var int JWT lifetime in seconds (default: 7 days)
     */
    private static $lifetime = 604800; // 7 days

    /**
     * @config
     * @var int Renewal threshold in seconds (default: 1 hour)
     */
    private static $renewal_threshold = 3600; // 1 hour

    /**
     * @config
     * @var string JWT algorithm
     */
    private static $algorithm = 'HS256';

    /**
     * Get the JWT secret from environment or config
     */
    public function getSecret(): string
    {
        $secret = $this->config()->get('secret');
        
        if (!$secret) {
            $secret = getenv('JWT_SECRET');
        }
        
        if (!$secret) {
            throw new \Exception('JWT secret not configured. Set JWT_SECRET environment variable or configure JWTService.secret');
        }
        
        return $secret;
    }

    /**
     * Generate a JWT token for a member
     */
    public function generateToken(Member $member): string
    {
        $now = time();
        $expiry = $now + $this->config()->get('lifetime');
        
        $payload = [
            'memberId' => $member->ID,
            'email' => $member->Email,
            'iss' => Director::absoluteBaseURL(),
            'iat' => $now,  // issued at
            'exp' => $expiry, // expiry
            'rat' => $now,  // renewed at
            'jti' => $this->generateJti() // unique identifier
        ];

        return JWT::encode($payload, $this->getSecret(), $this->config()->get('algorithm'));
    }

    /**
     * Validate a JWT token
     */
    public function validateToken(string $token): bool
    {
        try {
            JWT::decode(
                $token,
                new Key($this->getSecret(), $this->config()->get('algorithm'))
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Decode a JWT token and return the payload
     */
    public function decodeToken(string $token): object
    {
        return JWT::decode(
            $token,
            new Key($this->getSecret(), $this->config()->get('algorithm'))
        );
    }

    /**
     * Check if a token needs renewal and renew it if necessary
     */
    public function renewToken(string $token): string
    {
        try {
            $payload = $this->decodeToken($token);
            $payloadArray = (array) $payload;
            
            // Check if token needs renewal based on 'rat' (renewed at) claim
            $renewedAt = $payloadArray['rat'] ?? $payloadArray['iat'];
            $timeSinceRenewal = time() - $renewedAt;
            
            // If token was renewed less than threshold ago, return original token
            if ($timeSinceRenewal < $this->config()->get('renewal_threshold')) {
                return $token;
            }
            
            // Update renewal time and expiry
            $payloadArray['rat'] = time();
            $payloadArray['exp'] = time() + $this->config()->get('lifetime');
            
            return JWT::encode($payloadArray, $this->getSecret(), $this->config()->get('algorithm'));
            
        } catch (\Exception $e) {
            throw new \Exception('Invalid token for renewal: ' . $e->getMessage());
        }
    }

    /**
     * Extract member ID from token
     */
    public function getMemberIdFromToken(string $token): ?int
    {
        try {
            $payload = $this->decodeToken($token);
            return $payload->memberId ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate a unique token identifier
     */
    private function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }
}
