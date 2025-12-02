<!-- Application JavaScript Configuration -->
<script>
/**
 * Set BASE_PATH from PHP
 * This ensures JavaScript always has the correct base path for API calls
 */
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.BASE_PATH = '<?php echo defined('BASE_PATH') ? BASE_PATH : '/tmg'; ?>';
window.APP_CONFIG.CURRENT_USER = {
    id: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>,
    username: <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>,
    role: <?php echo isset($_SESSION['role']) ? json_encode($_SESSION['role']) : 'null'; ?>
};

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
    currentUrl: window.location.href,
    buildApiUrl: typeof window.buildApiUrl,
    buildPublicUrl: typeof window.buildPublicUrl
});
</script>
