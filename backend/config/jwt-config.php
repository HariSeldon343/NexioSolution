<?php
/**
 * JWT Configuration
 * Configurazione per autenticazione JWT mobile
 */

return [
    // Chiave segreta per firmare i token (in produzione usare variabile ambiente)
    'secret_key' => $_ENV['JWT_SECRET_KEY'] ?? 'nexio_jwt_secret_key_2025_mobile_calendar_app_secure',
    
    // Algoritmo di firma
    'algorithm' => 'HS256',
    
    // Durata token in secondi
    'access_token_expiry' => 900, // 15 minuti
    'refresh_token_expiry' => 2592000, // 30 giorni
    
    // Issuer e audience
    'issuer' => 'nexio-platform',
    'audience' => 'nexio-mobile-app',
    
    // Headers CORS per PWA
    'cors' => [
        'allowed_origins' => [
            'http://localhost',
            'http://localhost:3000',
            'http://localhost:8080',
            'https://app.nexiosolution.it', // Cloudflare tunnel production
            'https://nexio.app' // Alternative domain
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'max_age' => 86400, // 24 ore
        'credentials' => true
    ],
    
    // Rate limiting
    'rate_limit' => [
        'login_attempts' => 5, // Tentativi login per IP
        'login_window' => 900, // Finestra temporale in secondi (15 min)
        'api_requests_per_minute' => 60,
        'api_requests_per_hour' => 1000
    ],
    
    // Push notifications (Web Push Protocol)
    'push' => [
        'vapid_public_key' => $_ENV['VAPID_PUBLIC_KEY'] ?? '',
        'vapid_private_key' => $_ENV['VAPID_PRIVATE_KEY'] ?? '',
        'vapid_subject' => 'mailto:admin@nexio.app'
    ]
];