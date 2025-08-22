<?php

namespace Tipbr\Controllers;

use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use Tipbr\Controllers\ApiController;
use Tipbr\Services\JWTService;
use Tipbr\DataObjects\PasswordResetRequest;

class AuthApiController extends ApiController
{
    private static $allowed_actions = [
        'login',
        'verify',
        'refresh',
        'register',
        'forgotPassword',
        'resetPassword',
        'changePassword',
        'logout'
    ];

    public function index()
    {
        return $this->httpError(400, 'Bad Request');
    }

    public function login()
    {
        $this->ensurePOST();

        list($email, $password) = $this->ensureVars([
            'Email' => function ($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            },
            'Password' => function ($password) {
                return strlen($password) >= 8; // TODO: make configurable
            }
        ]);

        $member = Member::get()->filter('Email', $email)->first();
        if (!$member || !$member->checkPassword($password)->isValid()) {
            return $this->httpError(401, 'Invalid credentials');
        }

        $jwtService = JWTService::singleton();
        $token = $jwtService->generateToken($member);

        return $this->success(['token' => $token]);
    }

    public function verify()
    {
        $this->ensureGET();

        $member = $this->ensureUserLoggedIn();
        if (!$member) {
            return $this->httpError(401, 'Not logged in');
        }

        return $this->success([
            'Member' => $member->toMap(),
        ]);
    }

    public function refresh()
    {
        $this->ensurePOST();

        try {
            $currentToken = $this->getBearerToken();
            if (!$currentToken) {
                return $this->httpError(401, 'No token provided');
            }

            $jwtService = JWTService::singleton();
            
            if (!$jwtService->validateToken($currentToken)) {
                return $this->httpError(401, 'Invalid token');
            }

            $memberId = $jwtService->getMemberIdFromToken($currentToken);
            if (!$memberId) {
                return $this->httpError(401, 'Invalid token payload');
            }

            $member = Member::get()->byID($memberId);
            if (!$member) {
                return $this->httpError(401, 'Member not found');
            }

            // Generate a fresh token (not just renewed)
            $newToken = $jwtService->generateToken($member);

            return $this->success(['token' => $newToken]);
        } catch (\Exception $e) {
            return $this->httpError(401, 'Token refresh failed');
        }
    }

    public function register()
    {
        $this->ensurePOST();

        list($email, $password) = $this->ensureVars([
            'Email',
            'Password',
        ]);

        $member = Member::get()->filter('Email', $email)->first();
        if ($member) {
            return $this->httpError(201, 'Member already exists');
        }

        // create a new group "api-users" and assign the user to it
        $group = Group::get()->filter('Code', 'api-users')->first();

        if (!$group) {
            $group = Group::create();
            $group->Code = 'api-users';
            $group->Title = 'Api Users';
            $group->write();
        }

        $member = Member::create();
        $member->FirstName = $email;
        $member->Email = $email;
        $member->Password = $password;
        $member->write();

        // Assign the user to the group
        $member->Groups()->add($group);
        $member->write();

        return $this->success();
    }

    /**
     * Forgot password
     */
    public function forgotPassword()
    {
        $this->ensurePOST();

        list($email) = $this->ensureVars([
            'Email',
        ]);

        $member = Member::get()->filter('Email', $email)->first();
        if (!$member) {
            return $this->httpError(404, 'Member not found');
        }

        $reset = PasswordResetRequest::create();
        $reset->MemberID = $member->ID;
        $reset->write();

        return $this->success();
    }

    /**
     * Reset password
     */
    public function resetPassword()
    {
        $this->ensurePOST();

        list($code, $password) = $this->ensureVars([
            'Code',
            'Password',
        ]);

        $reset = PasswordResetRequest::get()->filter('Code', $code)->first();
        if (!$reset) {
            return $this->httpError(404, 'Reset request not found');
        }

        $member = $reset->Member();
        $member->Password = $password;
        $member->write();

        $reset->delete();

        return $this->success();
    }

    /**
     * Change password
     */
    public function changePassword()
    {
        $this->ensurePOST();

        list($oldPassword, $newPassword) = $this->ensureVars([
            'OldPassword',
            'NewPassword',
        ]);

        $member = $this->ensureUserLoggedIn();

        if (!$member->checkPassword($oldPassword)->isValid()) {
            return $this->httpError(403, 'Invalid password');
        }

        $member->Password = $newPassword;
        $member->write();

        return $this->success();
    }

    public function logout()
    {
        $this->ensurePOST();

        // Invalidate the current session
        $member = $this->ensureUserLoggedIn();
        if (!$member) {
            return $this->httpError(401, 'Not logged in');
        }

    // TODO: invalidate all tokens for this user

        return $this->success(['message' => 'Logged out successfully']);
    }
}