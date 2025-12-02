# Traffic Management System - Deployment Guide

## 🚀 Deploying to Production

This guide explains how to deploy the Traffic Management System (TMG) to a production server and configure the base path for different hosting environments.

---

## 📋 Table of Contents

1. [Understanding Base Paths](#understanding-base-paths)
2. [Configuration Steps](#configuration-steps)
3. [Common Deployment Scenarios](#common-deployment-scenarios)
4. [Verifying the Deployment](#verifying-the-deployment)
5. [Troubleshooting](#troubleshooting)

---

## Understanding Base Paths

The application uses a **dynamic base path** system that allows it to work in different hosting environments:

- **Development (XAMPP)**: `http://localhost/tmg/` → Base Path: `/tmg`
- **Production (Root)**: `https://yourdomain.com/` → Base Path: `` (empty string)
- **Production (Subdirectory)**: `https://yourdomain.com/traffic/` → Base Path: `/traffic`

---

## Configuration Steps

### Step 1: Update `includes/config.php`

Open `includes/config.php` and locate this line:

```php
define('BASE_PATH', '/tmg'); // CHANGE THIS IN PRODUCTION!
```

**Change it based on your deployment:**

#### For Root Domain Deployment:
```php
define('BASE_PATH', ''); // Empty string for root
```

#### For Subdirectory Deployment:
```php
define('BASE_PATH', '/your-subdirectory-name');
```

Example:
```php
define('BASE_PATH', '/traffic'); // If hosted at yourdomain.com/traffic/
```

### Step 2: Verify JavaScript Configuration

The system automatically includes the JavaScript configuration through the template. Ensure your page templates include this line in the `<head>` section:

```php
<?php include __DIR__ . '/../includes/js_config.php'; ?>
```

This file:
- Injects the base path from PHP into JavaScript
- Loads the `config.js` file with helper functions
- Sets up the `buildApiUrl()` and `buildPublicUrl()` functions

### Step 3: Database Configuration

Update database credentials in `includes/config.php`:

```php
define('DB_HOST', 'localhost');        // Your database host
define('DB_NAME', 'traffic_system');   // Your database name
define('DB_USER', 'your_db_user');     // Your database username
define('DB_PASS', 'your_db_password'); // Your database password
```

### Step 4: File Permissions

Set proper file permissions on your production server:

```bash
# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make writable directories
chmod 777 php_errors.log
```

---

## Common Deployment Scenarios

### Scenario 1: Deploying to cPanel (Subdirectory)

1. Upload files to `public_html/tmg/`
2. Update `includes/config.php`:
   ```php
   define('BASE_PATH', '/tmg');
   ```
3. Import database using phpMyAdmin
4. Access: `https://yourdomain.com/tmg/`

### Scenario 2: Deploying to VPS/Dedicated Server (Root)

1. Upload files to `/var/www/html/` or web root
2. Update `includes/config.php`:
   ```php
   define('BASE_PATH', '');
   ```
3. Configure virtual host to point to `public/` directory
4. Import database
5. Access: `https://yourdomain.com/`

### Scenario 3: Deploying to Shared Hosting (Subdirectory)

1. Upload files via FTP to `public_html/traffic/`
2. Update `includes/config.php`:
   ```php
   define('BASE_PATH', '/traffic');
   ```
3. Import database using hosting control panel
4. Access: `https://yourdomain.com/traffic/`

---

## Verifying the Deployment

### 1. Check Base Path Configuration

Open browser console (F12) and check for:

```javascript
console.log(window.APP_CONFIG.BASE_PATH);
```

This should output your configured base path.

### 2. Test API Endpoints

Try accessing a public page and check the browser console (Network tab) for API calls. They should show:

✅ **Correct:**
- `https://yourdomain.com/tmg/api/insert_citation.php` (if BASE_PATH is `/tmg`)
- `https://yourdomain.com/api/insert_citation.php` (if BASE_PATH is empty)

❌ **Incorrect:**
- `https://yourdomain.com/tmg/tmg/api/...` (double base path)
- `https://yourdomain.com//api/...` (missing base path)

### 3. Test Key Features

Log in and test:

- ✅ Citation creation
- ✅ Payment processing
- ✅ Receipt generation
- ✅ User management
- ✅ Reports

All should work without console errors.

---

## Troubleshooting

### Issue: "Failed to fetch" errors in browser console

**Solution:**
1. Check `includes/config.php` - Ensure `BASE_PATH` matches your hosting path
2. Check browser console - Look for the actual URL being called
3. Verify API files exist in the `api/` directory

### Issue: API calls showing 404 errors

**Solution:**
- Check that the `BASE_PATH` in `config.php` is correct
- Verify that `.htaccess` file (if using Apache) allows URL rewriting
- Check file permissions (API files should be readable)

### Issue: JavaScript config not loading

**Solution:**
1. Ensure `includes/js_config.php` is included in page templates:
   ```php
   <?php include __DIR__ . '/../includes/js_config.php'; ?>
   ```
2. Check browser console for errors
3. Verify `assets/js/config.js` exists and is accessible

### Issue: Mixed content (HTTP/HTTPS) warnings

**Solution:**
Update `includes/config.php` to force HTTPS:

```php
if (!headers_sent()) {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Content-Security-Policy: upgrade-insecure-requests");
    }
}
```

---

## Security Checklist for Production

- [ ] Change `BASE_PATH` in `config.php`
- [ ] Update database credentials
- [ ] Disable error display: `ini_set('display_errors', 0);`
- [ ] Enable HTTPS/SSL certificate
- [ ] Set secure session cookies
- [ ] Change default admin password
- [ ] Set proper file permissions
- [ ] Enable firewall rules
- [ ] Regular database backups
- [ ] Keep system updated

---

## Support

For issues or questions:
1. Check the [browser console](chrome://inspect/) for JavaScript errors
2. Check `php_errors.log` for PHP errors
3. Verify your `BASE_PATH` configuration matches your hosting environment

---

**Last Updated:** December 2025
