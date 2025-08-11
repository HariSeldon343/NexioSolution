<?php
/**
 * CSP Fix for External Resources
 * This file provides a temporary fix for CSP issues with external CDNs
 * Include this file after config.php if you're experiencing CSP blocking issues
 */

// Only apply in production environment
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'app.nexiosolution.it') !== false) {
    
    // Remove any existing CSP header
    header_remove('Content-Security-Policy');
    
    // Set a more permissive CSP that allows required CDNs
    $csp = [
        "default-src" => "'self' https://app.nexiosolution.it",
        "script-src" => "'self' 'unsafe-inline' 'unsafe-eval' https://app.nexiosolution.it https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com",
        "style-src" => "'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "font-src" => "'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",
        "img-src" => "'self' data: blob: https:",
        "connect-src" => "'self' https://app.nexiosolution.it wss://app.nexiosolution.it https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "frame-ancestors" => "'self'",
        "base-uri" => "'self'",
        "form-action" => "'self'"
    ];
    
    // Build CSP string
    $csp_string = '';
    foreach ($csp as $directive => $value) {
        $csp_string .= $directive . ' ' . $value . '; ';
    }
    
    // Apply the fixed CSP header
    header('Content-Security-Policy: ' . trim($csp_string));
    
    // Also ensure other security headers are set
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
?>