<?php
/**
 * auth.php
 * Shared session + access-control helpers.
 * Include this AFTER session_start() in every protected page.
 *
 * NOTE: Redirects below use RELATIVE paths (e.g. "login.php" or
 * "../login.php") rather than absolute URLs. This avoids any issue
 * with spaces/encoding in folder names and works regardless of
 * what your project folder is called or where it's deployed.
 */

/**
 * Require any logged-in user. Redirects to login if not authenticated.
 * $depth = how many folders deep the current script is from the project root.
 * e.g. admin/dashboard.php is 1 folder deep, so pass depth=1.
 */
function require_login($depth = 0) {
    if (!isset($_SESSION['user_id'])) {
        $prefix = str_repeat('../', $depth);
        header("Location: {$prefix}login.php");
        exit();
    }
}

/**
 * Require the logged-in user to be an admin.
 * This is a SERVER-SIDE check — critical for real security.
 * Hiding an "Admin" link in HTML is not access control; this is.
 */
function require_admin($depth = 0) {
    require_login($depth);
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die("403 Forbidden: Admins only.");
    }
}

/**
 * Require the logged-in user to be a customer.
 */
function require_customer($depth = 0) {
    require_login($depth);
    if (($_SESSION['role'] ?? '') !== 'customer') {
        http_response_code(403);
        die("403 Forbidden: Customers only.");
    }
}

/**
 * Generate (or reuse) a CSRF token for the current session.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 */
function csrf_verify($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
}

/**
 * Escape output safely for HTML context (XSS prevention).
 */
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}