# 🚀 PRODUCTION DEPLOYMENT CHECKLIST
## Security Hardening - Quick Reference Guide

**Before deploying to production, complete ALL items in this checklist.**

---

## ✅ PRE-DEPLOYMENT CHECKLIST

### 1. Database Migration
**File:** `database/migrations/add_security_features.sql`

```bash
# Connect to your production database
mysql -u your_production_user -p your_production_database < database/migrations/add_security_features.sql
```

**Verify tables created:**
- [ ] `rate_limits` table exists
- [ ] `audit_logs` table exists
- [ ] `users` table has `failed_login_attempts` column
- [ ] `users` table has `locked_until` column

---

### 2. Update Database Credentials
**File:** `includes/config.php` (Lines 43-46)

**FIND:**
```php
define('DB_NAME', 'YOUR_DATABASE_NAME_HERE');
define('DB_USER', 'YOUR_DATABASE_USER_HERE');
define('DB_PASS', 'YOUR_DATABASE_PASSWORD_HERE');
```

**REPLACE WITH:**
```php
define('DB_NAME', 'actual_production_db_name');
define('DB_USER', 'actual_production_db_user');
define('DB_PASS', 'strong_production_password');
```

- [ ] Database name updated
- [ ] Database user updated
- [ ] Database password updated
- [ ] Test connection works

---

### 3. Enable Secure Session Cookies (HTTPS)
**File:** `includes/auth.php` (Line 17)

**FIND:**
```php
ini_set('session.cookie_secure', $isHttps ? 1 : 0);
// UNCOMMENT FOR PRODUCTION: ini_set('session.cookie_secure', 1);
```

**REPLACE WITH:**
```php
ini_set('session.cookie_secure', $isHttps ? 1 : 0);
// PRODUCTION: Force secure cookies
ini_set('session.cookie_secure', 1);
```

- [ ] Line 17: `ini_set('session.cookie_secure', 1);` uncommented

---

### 4. Disable Debug Mode
**File:** `includes/config.php` (Line 62)

**FIND:**
```php
define('DEBUG_MODE', $isLocalhost); // Auto-detect: true for localhost, false for production
// UNCOMMENT FOR PRODUCTION: define('DEBUG_MODE', false);
```

**REPLACE WITH:**
```php
// define('DEBUG_MODE', $isLocalhost); // Auto-detect: true for localhost, false for production
// PRODUCTION: Always disable debug mode
define('DEBUG_MODE', false);
```

- [ ] DEBUG_MODE set to `false`

---

### 5. Enable HTTPS Headers
**File:** `includes/config.php` (Line 130)

**FIND:**
```php
// UNCOMMENT FOR PRODUCTION WITH HTTPS:
// header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
```

**REPLACE WITH:**
```php
// PRODUCTION: HSTS enabled
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
```

- [ ] HSTS header uncommented

---

### 6. Enable HTTPS Redirect
**File:** `.htaccess` (Lines 68-72)

**FIND:**
```apache
# <IfModule mod_rewrite.c>
#     RewriteEngine On
#     RewriteCond %{HTTPS} off
#     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
# </IfModule>
```

**REPLACE WITH:**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

- [ ] HTTPS redirect enabled

---

### 7. Verify SSL Certificate
- [ ] SSL certificate installed on server
- [ ] SSL certificate valid (not expired)
- [ ] SSL certificate matches domain
- [ ] Test HTTPS access: `https://your-domain.com/tmg/`

---

### 8. File Permissions
Set correct permissions on production server:

```bash
# Navigate to your project directory
cd /path/to/tmg

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make log file writable
chmod 666 php_errors.log

# Protect sensitive files (read-only)
chmod 400 includes/config.php
chmod 400 includes/auth.php
chmod 400 includes/security.php
```

- [ ] Directory permissions: 755
- [ ] File permissions: 644
- [ ] Log file writable: 666
- [ ] Config files protected: 400

---

### 9. Remove Development Files (Optional)
Delete files not needed in production:

```bash
# Remove test files
rm -rf tests/

# Remove documentation (optional - keep for reference)
# rm -rf docs/

# Remove git repository (optional)
# rm -rf .git/
```

- [ ] Test files removed
- [ ] Development files cleaned up

---

### 10. Update Error Log Path
**File:** `.htaccess` (Line 4)

**FIND:**
```apache
php_value error_log "c:/xampp/htdocs/tmg/php_errors.log"
```

**REPLACE WITH (Linux/Production):**
```apache
php_value error_log "/var/www/html/tmg/php_errors.log"
```

Or use absolute path to your production directory.

- [ ] Error log path updated for production server

---

## 🧪 POST-DEPLOYMENT TESTING

### Test 1: HTTPS Redirection
```bash
# Test HTTP to HTTPS redirect
curl -I http://your-domain.com/tmg/
# Should return: 301 Moved Permanently
# Location: https://your-domain.com/tmg/
```

- [ ] HTTP redirects to HTTPS

### Test 2: Security Headers
```bash
# Check security headers
curl -I https://your-domain.com/tmg/

# Should include:
# Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
# X-Frame-Options: SAMEORIGIN
# X-Content-Type-Options: nosniff
# X-XSS-Protection: 1; mode=block
# Content-Security-Policy: ...
```

- [ ] All security headers present

### Test 3: Account Lockout
1. Try logging in with wrong password 5 times
2. Verify account is locked
3. Check `audit_logs` table for events

- [ ] Account locks after 5 failed attempts
- [ ] Lockout message displayed
- [ ] Events logged in database

### Test 4: Rate Limiting
1. Make 6+ rapid requests to an endpoint
2. Verify 429 (Too Many Requests) response

- [ ] Rate limiting active
- [ ] Proper error message displayed

### Test 5: Password Complexity
1. Try creating user with weak password
2. Verify validation error

- [ ] Password requires 12+ characters
- [ ] Password requires uppercase, lowercase, number, special char

### Test 6: Authorization
1. Login as regular user
2. Try accessing officer management
3. Verify 403 Forbidden response

- [ ] Officer management requires enforcer/admin
- [ ] Proper authorization checks in place

### Test 7: Debug Mode Disabled
1. Trigger an error (e.g., invalid input)
2. Verify generic error message shown (not detailed)

- [ ] Generic error messages in production
- [ ] No file paths or database details exposed

### Test 8: Session Security
1. Check browser cookies
2. Verify session cookie has:
   - Secure flag
   - HttpOnly flag
   - SameSite=Strict

- [ ] Session cookies secure
- [ ] Session expires after 30 min inactivity

---

## 🔍 MONITORING SETUP

### Daily Checks
```sql
-- Check failed login attempts
SELECT * FROM audit_logs
WHERE action = 'login_failed'
AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY created_at DESC;

-- Check locked accounts
SELECT username, failed_login_attempts, locked_until
FROM users
WHERE locked_until > NOW();
```

### Weekly Checks
```sql
-- Check rate limit violations
SELECT ip_address, action, COUNT(*) as attempts
FROM rate_limits
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY ip_address, action
HAVING attempts > 10
ORDER BY attempts DESC;
```

### Monthly Maintenance
```sql
-- Clean old audit logs (keep 90 days)
DELETE FROM audit_logs
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Clean expired rate limits
DELETE FROM rate_limits
WHERE expires_at < NOW();
```

- [ ] Monitoring queries saved
- [ ] Cron jobs scheduled (optional)

---

## 📋 FINAL VERIFICATION

Before going live, verify ALL checkboxes above are checked:

### Configuration
- [ ] Database credentials updated
- [ ] DEBUG_MODE = false
- [ ] Secure cookies enabled
- [ ] HTTPS headers enabled
- [ ] HTTPS redirect enabled

### Database
- [ ] Migration script executed
- [ ] All tables created successfully
- [ ] Database connection tested

### Security
- [ ] SSL certificate valid
- [ ] Security headers present
- [ ] File permissions correct
- [ ] Error log path updated

### Testing
- [ ] HTTPS redirection works
- [ ] Account lockout works
- [ ] Rate limiting works
- [ ] Password complexity enforced
- [ ] Authorization checks work
- [ ] Debug mode disabled
- [ ] Session security verified

### Monitoring
- [ ] Audit logs accessible
- [ ] Error logs accessible
- [ ] Monitoring queries ready

---

## 🆘 ROLLBACK PLAN

If issues occur after deployment:

### Emergency Disable Security Features

1. **Disable HTTPS Redirect (if SSL issues):**
   ```apache
   # In .htaccess, comment out:
   # RewriteCond %{HTTPS} off
   # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

2. **Enable Debug Mode (temporarily for troubleshooting):**
   ```php
   // In includes/config.php
   define('DEBUG_MODE', true);
   ```

3. **Check Error Logs:**
   ```bash
   tail -f /path/to/tmg/php_errors.log
   ```

4. **Database Rollback (if needed):**
   ```sql
   -- Remove lockout columns
   ALTER TABLE users DROP COLUMN failed_login_attempts;
   ALTER TABLE users DROP COLUMN locked_until;

   -- Drop security tables
   DROP TABLE rate_limits;
   DROP TABLE audit_logs;
   ```

---

## ✅ DEPLOYMENT COMPLETE

Once all checks pass:

1. Announce maintenance window to users
2. Deploy changes to production
3. Run all post-deployment tests
4. Monitor for 24-48 hours
5. Document any issues encountered

**Congratulations! Your system is now hardened and production-ready!** 🎉

---

**Document Version:** 1.0
**Last Updated:** December 4, 2025
