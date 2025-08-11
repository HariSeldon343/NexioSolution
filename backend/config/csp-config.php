<?php
/**
 * Content Security Policy Configuration
 * 
 * This file manages CSP headers for the Nexio platform
 * to control which external resources can be loaded
 */

// Detect if we're in production (app.nexiosolution.it)
$isProduction = isset($_SERVER['HTTP_HOST']) && 
                strpos($_SERVER['HTTP_HOST'], 'nexiosolution.it') !== false;

// Only apply CSP in production to avoid local development issues
if ($isProduction) {
    
    // Define allowed sources
    $cspDirectives = [
        "default-src" => "'self'",
        
        "script-src" => implode(' ', [
            "'self'",
            "'unsafe-inline'",
            "'unsafe-eval'",
            "https://app.nexiosolution.it",
            "https://cdn.jsdelivr.net",
            "https://cdnjs.cloudflare.com",
            "https://code.jquery.com",
            "https://stackpath.bootstrapcdn.com",
            "https://unpkg.com"
        ]),
        
        "style-src" => implode(' ', [
            "'self'",
            "'unsafe-inline'",
            "https://fonts.googleapis.com",
            "https://cdn.jsdelivr.net",
            "https://cdnjs.cloudflare.com",
            "https://stackpath.bootstrapcdn.com",
            "https://use.fontawesome.com"
        ]),
        
        "font-src" => implode(' ', [
            "'self'",
            "https://fonts.gstatic.com",
            "https://cdnjs.cloudflare.com",
            "https://use.fontawesome.com",
            "data:"
        ]),
        
        "img-src" => implode(' ', [
            "'self'",
            "data:",
            "https:",
            "blob:"
        ]),
        
        "connect-src" => implode(' ', [
            "'self'",
            "https://app.nexiosolution.it",
            "wss://app.nexiosolution.it"
        ]),
        
        "frame-ancestors" => "'none'",
        "base-uri" => "'self'",
        "form-action" => "'self'"
    ];
    
    // Build CSP header string
    $cspHeader = "";
    foreach ($cspDirectives as $directive => $sources) {
        $cspHeader .= $directive . " " . $sources . "; ";
    }
    
    // Set the CSP header
    header("Content-Security-Policy: " . $cspHeader);
    
    // Also set report-only header for monitoring (optional)
    // header("Content-Security-Policy-Report-Only: " . $cspHeader);
    
    // Additional security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
} else {
    // Local development - more permissive or no CSP
    // This avoids issues during development
    
    // Optional: Set a very permissive CSP for local development
    // header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval' data: blob:;");
}

// Function to add additional CSP sources dynamically if needed
function addCSPSource($directive, $source) {
    // This function can be used to dynamically add sources
    // Implementation would require modifying headers before they're sent
}

// Log CSP violations (optional)
if ($isProduction && isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/csp-report') {
    $input = file_get_contents('php://input');
    if ($input) {
        $report = json_decode($input, true);
        if ($report) {
            error_log("CSP Violation: " . json_encode($report));
        }
    }
    http_response_code(204);
    exit;
}
?>