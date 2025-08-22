# JWT Authentication for SilverStripe RestfulServer

This module provides JWT (JSON Web Token) authentication for SilverStripe's RestfulServer module, enabling secure API access with proper permission integration.

## Features

- **JWT Token Authentication**: Secure API authentication using industry-standard JWT tokens
- **RestfulServer Integration**: Seamlessly integrates with SilverStripe's RestfulServer module
- **Permission Checking**: Respects DataObject `canView()`, `canEdit()`, `canDelete()`, and `canCreate()` methods
- **Automatic Token Renewal**: Tokens are automatically renewed when close to expiry
- **CORS Support**: Built-in CORS headers for cross-domain API access
- **Auth API Endpoints**: Login, logout, token refresh, password reset functionality

## Quick Start

### 1. Installation

```bash
composer require tipbr/silverstripe-restfulserver-jwt-auth
```

### 2. Configuration

Set your JWT secret in your environment file:

```bash
# .env
JWT_SECRET=your-super-secret-jwt-key-here
```

The module comes pre-configured but you can customize settings in `_config.yml`:

```yaml
# Configure JWT Service
Tipbr\Services\JWTService:
  lifetime: 604800      # 7 days in seconds
  renewal_threshold: 3600  # 1 hour in seconds
  algorithm: 'HS256'
```

### 3. Enable API Access on Your DataObjects

```php
<?php

class MyDataObject extends DataObject 
{
    private static $api_access = true;
    
    private static $db = [
        'Title' => 'Varchar(255)',
        'Content' => 'Text'
    ];
    
    // Permission methods are automatically respected
    public function canView($member = null) {
        return $member && $member->exists();
    }
    
    public function canEdit($member = null) {
        return $member && $member->inGroup('editors');
    }
}
```

## Usage

### Authentication

#### Get a JWT Token

```bash
curl -X POST http://yoursite.com/auth/login \
  -H "Content-Type: application/json" \
  -d '{"Email": "user@example.com", "Password": "password"}'
```

Response:
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

### API Access

Once you have a JWT token, use it to access RestfulServer endpoints:

```bash
# Get a DataObject
curl -X GET http://yoursite.com/api/MyDataObject/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Create a DataObject
curl -X POST http://yoursite.com/api/MyDataObject \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"Title": "My New Object"}'

# Update a DataObject  
curl -X PUT http://yoursite.com/api/MyDataObject/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"Title": "Updated Title"}'

# Delete a DataObject
curl -X DELETE http://yoursite.com/api/MyDataObject/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Authentication Endpoints

- `POST /auth/login` - Authenticate and get a JWT token
- `GET /auth/verify` - Verify the current token and get user info
- `POST /auth/refresh` - Get a fresh JWT token
- `POST /auth/register` - Register a new user account
- `POST /auth/forgotPassword` - Request a password reset
- `POST /auth/resetPassword` - Reset password with token
- `POST /auth/changePassword` - Change password for authenticated user
- `POST /auth/logout` - Invalidate current session

## Permission Integration

The authenticator integrates seamlessly with SilverStripe's permission system. RestfulServer automatically calls the appropriate permission methods on your DataObjects:

- `canView()` for GET requests
- `canEdit()` for PUT requests  
- `canDelete()` for DELETE requests
- `canCreate()` for POST requests

The authenticated user is available via `Security::getCurrentUser()` in these methods.

## Documentation

- [Complete Documentation](docs/README.md)
- [Authentication Demo](docs/authentication-demo.php)

## Requirements

- SilverStripe Framework 6.0+
- SilverStripe Admin 3.0+
- SilverStripe RestfulServer 4.x
- Firebase JWT 6.0+

## Testing

Run the test suite:

```bash
vendor/bin/phpunit tests/php/Authentication/
```

## Security Considerations

1. **JWT Secret**: Use a strong, randomly generated secret
2. **HTTPS**: Always use HTTPS in production
3. **Token Expiry**: Configure appropriate token lifetimes
4. **Permissions**: Implement proper permission methods on DataObjects

## Migration Notes

This version represents a major overhaul from previous versions to properly integrate with RestfulServer's authentication system. Key changes:

- Authenticator now uses static `authenticate()` method matching RestfulServer patterns
- Proper integration with SilverStripe's Security system
- Permission checking through DataObject methods (canView/canEdit/etc.)
- Simplified configuration and improved documentation

## License

See [License](LICENSE.md)

## Support

For issues and support, please visit the [GitHub repository](https://github.com/tipbr/silverstripe-restfulserver-jwt-auth).
