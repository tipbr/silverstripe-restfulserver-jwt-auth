<?php

namespace Tipbr\Controllers;

use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use TipBr\DataObjects\PasswordResetRequest;
use FullscreenInteractive\Restful\Controllers\AuthController;

// TODO: User group should be configurable?
class AuthApiController extends ApiController
{
    private static $allowed_actions = [
        'login',
        'register',
        'forgotPassword',
        'resetPassword',
        'changePassword'
    ];

    /**
     * Register a new user
     */
    // TODO: accept extra fields for the member
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
}