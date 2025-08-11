# Flutter Nexio App - API Documentation

## Overview
The Nexio platform now provides dedicated JSON API endpoints for mobile/Flutter authentication that properly handle sessions and return JSON responses instead of HTML redirects.

## Base URL
- Production: `https://app.nexiosolution.it/piattaforma-collaborativa`
- Local Testing: `http://localhost/piattaforma-collaborativa`

## Authentication Endpoints

### 1. Login
**Endpoint:** `/backend/api/login.php`  
**Method:** `POST`  
**Content-Type:** `application/json`

**Request Body:**
```json
{
  "username": "test_api_user",
  "password": "Test123!@#"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 25,
    "username": "test_api_user",
    "nome": "Test API",
    "cognome": "User",
    "email": "test.api@nexio.com",
    "ruolo": "utente",
    "full_name": "Test API User"
  },
  "session_id": "8pva382vi46tlkoi3v8a1ck9bn",
  "current_company": null,
  "companies": [],
  "permissions": {
    "is_super_admin": false,
    "is_utente_speciale": false,
    "has_elevated_privileges": false
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "error": "Username o password non validi"
}
```

**Rate Limit Response (429):**
```json
{
  "success": false,
  "error": "Troppi tentativi di login. Riprova tra X secondi."
}
```

### 2. Validate Session
**Endpoint:** `/backend/api/validate-session.php`  
**Method:** `GET`  
**Headers:** 
- `Cookie: PHPSESSID={session_id}` (from login response)
- Or use `X-Session-Id: {session_id}` header

**Success Response (200):**
```json
{
  "success": true,
  "authenticated": true,
  "user": { /* user object */ },
  "session_id": "8pva382vi46tlkoi3v8a1ck9bn",
  "current_company": null,
  "companies": [],
  "permissions": { /* permissions object */ }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "authenticated": false,
  "error": "Not authenticated"
}
```

### 3. Logout
**Endpoint:** `/backend/api/logout.php`  
**Method:** `POST`  
**Headers:** 
- `Cookie: PHPSESSID={session_id}`

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

## Flutter Implementation Notes

### Updated API Service
The `api_service.dart` file has been updated with:
- Proper JSON request/response handling
- Session cookie management
- Error handling for different HTTP status codes

### Updated Auth Provider
The `auth_provider.dart` file has been updated with:
- New User model with all fields from API
- Company model for multi-tenant support
- Permissions handling
- Session validation method

### Session Management
1. Store the `session_id` from login response in SharedPreferences
2. Include session cookie in all authenticated requests
3. Handle 401 responses by clearing session and redirecting to login

### Example Flutter Code

```dart
// Login
final response = await apiService.login(username, password);
if (response['success']) {
  // Store session and user data
  await prefs.setString('session_id', response['session_id']);
  // Navigate to dashboard
}

// Validate session on app startup
final response = await apiService.validateSession();
if (response['authenticated']) {
  // Session is valid, continue to app
} else {
  // Session expired, show login
}

// Include session in API calls
final headers = {
  'Content-Type': 'application/json',
  'Cookie': 'PHPSESSID=$sessionId',
};
```

## Testing

### Test Credentials
- Username: `test_api_user`
- Password: `Test123!@#`

### Test Tools
1. **PHP Test Script:** `/test-login-api.php` - Run with `php test-login-api.php`
2. **HTML Test Page:** `/test-flutter-login.html` - Open in browser
3. **Flutter App:** Update `baseUrl` in `api_service.dart` for local testing

## Error Handling

The API returns consistent error responses:
- `400` - Bad Request (missing parameters)
- `401` - Unauthorized (invalid credentials or expired session)
- `429` - Too Many Requests (rate limiting)
- `500` - Internal Server Error

Always check the `success` field in the response and handle errors appropriately in the Flutter app.

## CORS Headers

All API endpoints include proper CORS headers for cross-origin requests:
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, GET, OPTIONS
Access-Control-Allow-Headers: Content-Type, Accept, X-Auth-Token
```

## Security Notes

1. **HTTPS:** Always use HTTPS in production
2. **Session Timeout:** Sessions expire after inactivity
3. **Rate Limiting:** Login attempts are rate-limited per IP
4. **Password Requirements:** Enforce strong passwords
5. **Token Storage:** Store session tokens securely in Flutter

## Support

For issues or questions about the API:
1. Check error logs at `/logs/error.log`
2. Test with the provided test scripts
3. Ensure proper headers and request format