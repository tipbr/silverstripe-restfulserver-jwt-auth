<?php

namespace Tipbr\Admin;

use SilverStripe\Admin\ModelAdmin;
use Tipbr\DataObjects\PasswordResetRequest;

class PasswordResetAdmin extends ModelAdmin
{
    private static $managed_models = [
        PasswordResetRequest::class,
    ];

    private static $url_segment = 'password-reset-requests';

    private static $menu_title = 'Password Reset Requests';

    private static $menu_icon_class = 'font-icon-lock';
}