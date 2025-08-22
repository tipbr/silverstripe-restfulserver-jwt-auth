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

#### Verify Token

```bash
curl -X GET http://yoursite.com/auth/verify \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

#### Refresh Token

```bash
curl -X POST http://yoursite.com/auth/refresh \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### API Access

Once you have a JWT token, use it to access RestfulServer endpoints:

#### Get a DataObject

```bash
curl -X GET http://yoursite.com/api/MyDataObject/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

#### Create a DataObject

```bash
curl -X POST http://yoursite.com/api/MyDataObject \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"Title": "My New Object", "Content": "Some content"}'
```

#### Update a DataObject

```bash
curl -X PUT http://yoursite.com/api/MyDataObject/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"Title": "Updated Title"}'
```

#### Delete a DataObject

```bash
curl -X DELETE http://yoursite.com/api/MyDataObject/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Authentication Endpoints

The module provides several authentication-related endpoints under `/auth/`:

- `POST /auth/login` - Authenticate and get a JWT token
- `GET /auth/verify` - Verify the current token and get user info
- `POST /auth/refresh` - Get a fresh JWT token
- `POST /auth/register` - Register a new user account
- `POST /auth/forgotPassword` - Request a password reset
- `POST /auth/resetPassword` - Reset password with token
- `POST /auth/changePassword` - Change password for authenticated user
- `POST /auth/logout` - Invalidate current session

## Permission Integration

The authenticator integrates seamlessly with SilverStripe's permission system:

1. **Authentication**: JWT tokens are validated and the user is set in the Security context
2. **Authorization**: RestfulServer automatically calls the appropriate permission methods:
   - `canView()` for GET requests
   - `canEdit()` for PUT requests  
   - `canDelete()` for DELETE requests
   - `canCreate()` for POST requests
3. **Context**: The authenticated user is available via `Security::getCurrentUser()`

Example permission method:

```php
public function canEdit($member = null) {
    // $member is automatically the JWT-authenticated user
    return $member && (
        $member->inGroup('editors') || 
        $this->OwnerID === $member->ID
    );
}
```

## Token Renewal

Tokens are automatically renewed when they're within the renewal threshold of expiry. The new token is returned in the `X-Renewed-Token` response header.

## Configuration Options

### JWT Service Configuration

```yaml
Tipbr\Services\JWTService:
  # Token lifetime in seconds (default: 7 days)
  lifetime: 604800
  
  # Renewal threshold in seconds (default: 1 hour)
  renewal_threshold: 3600
  
  # JWT algorithm (default: HS256)
  algorithm: 'HS256'
  
  # JWT secret (better to use environment variable)
  secret: 'your-secret-key'
```

### CORS Configuration

```yaml
# Optional: Configure CORS for development
SilverStripe\Control\HTTPResponse:
  cors:
    enabled: true
    allow_origin: '*'
    allow_headers: 'Authorization, Content-Type, Accept'
    allow_methods: 'GET, POST, PUT, DELETE, OPTIONS'
```

## Security Considerations

1. **JWT Secret**: Use a strong, randomly generated secret and store it securely
2. **HTTPS**: Always use HTTPS in production to protect tokens in transit
3. **Token Expiry**: Configure appropriate token lifetimes for your security requirements
4. **Permissions**: Implement proper permission methods on your DataObjects
5. **Input Validation**: Validate and sanitize all API inputs

## Troubleshooting

### Token Not Working

1. Check that the JWT secret is configured correctly
2. Verify the token hasn't expired
3. Ensure the Authorization header format is correct: `Bearer YOUR_TOKEN`

### Permission Denied

1. Check that `api_access = true` is set on your DataObject
2. Verify the permission methods (`canView`, `canEdit`, etc.) return true
3. Confirm the user is properly authenticated

### CORS Issues

1. Ensure the JWTRestfulServerExtension is enabled
2. Check your CORS configuration
3. Verify preflight OPTIONS requests are handled correctly

## Migration from Previous Version

If you're upgrading from a previous version that used the instance-based authenticator:

1. The authenticator now uses a static `authenticate()` method matching RestfulServer's expectations
2. Remove any custom `RestfulAuthenticator` interface implementations
3. The `permission_checker` configuration is no longer needed
4. Authentication is now handled directly by RestfulServer instead of extensions

## Testing

Run the test suite to verify everything is working:

```bash
vendor/bin/phpunit tests/php/Authentication/
```

## Support

For issues and support, please visit the [GitHub repository](https://github.com/tipbr/silverstripe-restfulserver-jwt-auth).