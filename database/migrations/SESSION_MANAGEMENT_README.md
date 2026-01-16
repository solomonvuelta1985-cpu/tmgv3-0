# Session Management Feature - Installation Guide

## Overview
This feature adds multi-device session tracking with a 2-device limit for admin users to enhance security.

## Features
- **Device Limit**: Admin users can only be logged in on 2 devices simultaneously
- **Session Tracking**: All active sessions are tracked with device info, IP address, and browser details
- **Automatic Cleanup**: Expired sessions are automatically cleaned up
- **Visual Indicator**: Login page shows device limit notice for admins
- **Logout Cleanup**: Sessions are properly cleaned up when users logout

## Installation Steps

### 1. Run Database Migration
Execute the SQL migration file in phpMyAdmin or MySQL CLI:

```bash
# Navigate to the migrations folder
cd database/migrations

# Run the migration
mysql -u your_username -p traffic_system < add_session_management.sql
```

OR in phpMyAdmin:
1. Open phpMyAdmin
2. Select the `traffic_system` database
3. Click on the "SQL" tab
4. Copy and paste the contents of `add_session_management.sql`
5. Click "Go" to execute

### 2. Verify Table Creation
Check that the `active_sessions` table was created successfully:

```sql
DESCRIBE active_sessions;
```

You should see columns: session_id, user_id, session_token, device_info, ip_address, user_agent, login_time, last_activity, expires_at, is_active

## How It Works

### For Admin Users:
1. **First Device Login**: User logs in successfully, session is created
2. **Second Device Login**: User logs in on another device, both sessions remain active
3. **Third Device Login Attempt**:
   - Error message: "Maximum device limit reached (2 devices). Please logout from another device first."
   - Login is blocked until user logs out from one of the other devices

### For Other Users (enforcer, cashier, lto_staff, user):
- No device limit (unlimited concurrent sessions)

## Session Management

### Automatic Session Cleanup:
- Expired sessions (>30 minutes of inactivity) are automatically marked as inactive
- Cleanup runs every time `get_active_session_count()` is called

### Manual Logout:
- When a user logs out, their session is immediately deactivated
- The session record in `active_sessions` table is marked as `is_active = 0`

## Security Benefits

1. **Prevents Account Sharing**: Limits admin accounts to 2 devices max
2. **Enhanced Monitoring**: Track which devices/IPs accessed each account
3. **Audit Trail**: Complete login history with timestamps
4. **Session Hijacking Protection**: Each session has a unique token
5. **Automatic Timeout**: Sessions expire after 30 minutes of inactivity

## Troubleshooting

### Issue: "Maximum device limit reached" but user has no active sessions
**Solution**: Clean up stale sessions manually:
```sql
UPDATE active_sessions
SET is_active = 0
WHERE user_id = [USER_ID];
```

### Issue: Sessions not being created
**Solution**: Check PHP error logs and ensure:
1. `includes/session_manager.php` is included
2. Database connection is working
3. Table `active_sessions` exists

## Configuration

### Change Device Limit
Edit `includes/session_manager.php`, line ~147:
```php
// Change from 2 to your desired limit
$maxSessions = ($role === 'admin') ? 2 : 999;
```

### Change Session Timeout
Edit in two places:

1. `includes/session_manager.php` (lines ~67, ~197):
```php
// Change from 30 minutes to your desired time
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
```

2. `includes/auth.php` (line ~22):
```php
// Change from 1800 seconds (30 min) to your desired time
ini_set('session.gc_maxlifetime', 1800);
```

## Database Schema

```sql
CREATE TABLE IF NOT EXISTS active_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    device_info VARCHAR(255) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,

    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
```

## Files Modified

1. `database/migrations/add_session_management.sql` - Database migration
2. `includes/session_manager.php` - Session management functions (NEW)
3. `public/login.php` - Added session limit check and visual indicator
4. `includes/auth.php` - Added session_token to create_session() and destroy_session()

## Testing

### Test the Feature:
1. **Login as Admin** on Device 1 (e.g., Chrome)
2. **Login as Admin** on Device 2 (e.g., Firefox or another computer)
3. **Try to Login** on Device 3 - Should be blocked with error message
4. **Logout** from Device 1
5. **Login again** on Device 3 - Should work now

### Check Active Sessions:
```sql
SELECT user_id, device_info, ip_address, login_time, is_active
FROM active_sessions
WHERE is_active = 1
ORDER BY login_time DESC;
```

## Support

For issues or questions:
- Check PHP error logs at `C:\xampp\php\logs\php_error_log`
- Review database migration success
- Verify all files are in place

## License
Part of Traffic Citation Management System (TMG)
