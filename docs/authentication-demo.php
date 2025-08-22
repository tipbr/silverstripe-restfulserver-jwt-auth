<?php

/**
 * Test script to demonstrate JWT Authentication working with RestfulServer
 * 
 * This script shows:
 * 1. How to generate a JWT token using AuthApiController
 * 2. How the JWT token works with RestfulServer endpoints
 * 3. How permissions (canView/canEdit) are respected
 * 
 * To use this:
 * 1. Set up a SilverStripe site with this module
 * 2. Create a test DataObject with api_access enabled
 * 3. Run requests against the endpoints shown below
 */

echo "=== JWT RestfulServer Authentication Demo ===\n\n";

echo "1. AUTHENTICATION ENDPOINT\n";
echo "POST /auth/login\n";
echo "Content-Type: application/json\n";
echo '{"Email": "admin@example.com", "Password": "password"}' . "\n";
echo "Response: {'token': 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'}\n\n";

echo "2. RESTFUL API ACCESS WITH JWT\n";
echo "GET /api/Member/1\n";
echo "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...\n";
echo "Response: Member data (if canView() returns true)\n\n";

echo "3. RESTFUL API CREATION WITH JWT\n";
echo "POST /api/MyDataObject\n";
echo "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...\n";
echo "Content-Type: application/json\n";
echo '{"Title": "New Object"}' . "\n";
echo "Response: Created object (if canCreate() returns true)\n\n";

echo "4. PERMISSION CHECKING\n";
echo "The RestfulServer will automatically call:\n";
echo "- canView() for GET requests\n";
echo "- canEdit() for PUT requests\n";
echo "- canDelete() for DELETE requests\n";
echo "- canCreate() for POST requests\n\n";

echo "5. EXAMPLE DATAOBJECT WITH PERMISSIONS\n";
echo <<<'PHP'
<?php

class MyDataObject extends DataObject 
{
    private static $api_access = true;
    
    private static $db = [
        'Title' => 'Varchar(255)',
        'Content' => 'Text'
    ];
    
    // Only authenticated users can view
    public function canView($member = null) {
        return $member && $member->exists();
    }
    
    // Only admin users can edit
    public function canEdit($member = null) {
        return $member && $member->inGroup('administrators');
    }
    
    // Only admin users can delete
    public function canDelete($member = null) {
        return $member && $member->inGroup('administrators');
    }
    
    // Anyone authenticated can create
    public function canCreate($member = null, $context = []) {
        return $member && $member->exists();
    }
}

PHP;

echo "\n6. TOKEN RENEWAL\n";
echo "If a token is close to expiry, the authenticator will automatically\n";
echo "renew it and add the new token in the X-Renewed-Token response header.\n\n";

echo "7. CURL EXAMPLES\n";
echo "# Get a token\n";
echo "curl -X POST http://yoursite.com/auth/login \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"Email\": \"admin@example.com\", \"Password\": \"password\"}'\n\n";

echo "# Use the token to access the API\n";
echo "curl -X GET http://yoursite.com/api/Member/1 \\\n";
echo "  -H \"Authorization: Bearer YOUR_JWT_TOKEN_HERE\"\n\n";

echo "=== End of Demo ===\n";