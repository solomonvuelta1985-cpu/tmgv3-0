/**
 * Application Configuration
 * This file contains application-wide configuration settings
 * The BASE_PATH is dynamically set from PHP to ensure correct paths in all environments
 */

// Base path will be set by PHP (see includes/config.php)
window.APP_CONFIG = window.APP_CONFIG || {};

// Default to '/tmg' for development if not set
if (!window.APP_CONFIG.BASE_PATH) {
    // Auto-detect base path from current URL
    const path = window.location.pathname;
    const segments = path.split('/').filter(s => s);

    // If we're in /tmg/ directory structure, use '/tmg'
    // Otherwise use root '/'
    if (segments.length > 0 && segments[0] === 'tmg') {
        window.APP_CONFIG.BASE_PATH = '/tmg';
    } else {
        window.APP_CONFIG.BASE_PATH = '';
    }
}

/**
 * Helper function to build API URLs
 * @param {string} endpoint - API endpoint path (e.g., 'api/insert_citation.php')
 * @returns {string} - Full API URL
 */
window.buildApiUrl = function(endpoint) {
    // Remove leading slash if present
    endpoint = endpoint.replace(/^\/+/, '');

    return window.APP_CONFIG.BASE_PATH + '/' + endpoint;
};

/**
 * Helper function to build public URLs
 * @param {string} path - Public path (e.g., 'public/receipt.php')
 * @returns {string} - Full public URL
 */
window.buildPublicUrl = function(path) {
    // Remove leading slash if present
    path = path.replace(/^\/+/, '');

    return window.APP_CONFIG.BASE_PATH + '/' + path;
};

/**
 * Helper function to build relative URLs from current location
 * @param {string} path - Relative path
 * @returns {string} - Relative URL
 */
window.buildRelativeUrl = function(path) {
    // This maintains relative paths (../) for same-level navigation
    return path;
};

// Log configuration for debugging
console.log('App Config Loaded:', {
    basePath: window.APP_CONFIG.BASE_PATH,
    currentUrl: window.location.href
});
