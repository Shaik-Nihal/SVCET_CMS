<?php
// ============================================================
// Security Headers — included automatically via constants.php
// Sends HTTP headers to harden the application against common attacks.
// ============================================================

// Prevent PHP from advertising itself
header_remove('X-Powered-By');

// Prevent MIME-type sniffing (stops browser from interpreting files as different type)
header('X-Content-Type-Options: nosniff');

// Prevent the page from being embedded in iframes (clickjacking protection)
header('X-Frame-Options: DENY');

// Enable XSS filter in older browsers
header('X-XSS-Protection: 1; mode=block');

// Control what referrer info is sent to external sites (protects internal URLs from leaking)
header('Referrer-Policy: strict-origin-when-cross-origin');

// Disable access to browser features this app doesn't need
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

// Prevent search engines from indexing this internal portal
header('X-Robots-Tag: noindex, nofollow');

// Content Security Policy — only allow resources from self + trusted CDNs
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
    . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
    . "font-src 'self' https://cdn.jsdelivr.net; "
    . "img-src 'self' data:; "
    . "connect-src 'self'; "
    . "frame-ancestors 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self';"
);

// When running on HTTPS, uncomment the line below to enable HSTS:
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Prevent caching of sensitive pages (login, forms, etc.)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
