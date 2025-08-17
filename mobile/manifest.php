<?php
/**
 * Manifest dinamico per PWA
 * Genera il manifest.json con percorsi relativi corretti
 */

require_once 'config.php';

// Set content type per JSON
header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');

// Genera il manifest dinamicamente
$manifest = [
    "name" => "Nexio Mobile",
    "short_name" => "Nexio",
    "description" => "Piattaforma Collaborativa Mobile - Gestione documenti, eventi e attività",
    "start_url" => "./",
    "scope" => "./",
    "display" => "standalone",
    "background_color" => "#ffffff",
    "theme_color" => "#2563eb",
    "orientation" => "portrait",
    "icons" => [
        [
            "src" => "icons/icon-72x72.png",
            "sizes" => "72x72",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "icons/icon-96x96.png",
            "sizes" => "96x96",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "icons/icon-128x128.png",
            "sizes" => "128x128",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "icons/icon-144x144.png",
            "sizes" => "144x144",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "icons/icon-152x152.png",
            "sizes" => "152x152",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "icons/icon-192x192.png",
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "icons/icon-384x384.png",
            "sizes" => "384x384",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "icons/icon-512x512.png",
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "icons/icon-512x512-maskable.png",
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any maskable"
        ]
    ],
    "screenshots" => [
        [
            "src" => "screenshots/dashboard.png",
            "sizes" => "540x720",
            "type" => "image/png",
            "label" => "Dashboard principale"
        ],
        [
            "src" => "screenshots/documents.png",
            "sizes" => "540x720",
            "type" => "image/png",
            "label" => "Gestione documenti"
        ]
    ],
    "categories" => ["business", "productivity"],
    "lang" => "it-IT",
    "dir" => "ltr",
    "prefer_related_applications" => false,
    "related_applications" => [],
    "shortcuts" => [
        [
            "name" => "Dashboard",
            "short_name" => "Dashboard",
            "description" => "Vai alla dashboard",
            "url" => "./?page=dashboard",
            "icons" => [["src" => "icons/icon-96x96.png", "sizes" => "96x96", "type" => "image/png"]]
        ],
        [
            "name" => "Documenti",
            "short_name" => "Documenti",
            "description" => "Gestione documenti",
            "url" => "./documenti.php",
            "icons" => [["src" => "icons/icon-96x96.png", "sizes" => "96x96", "type" => "image/png"]]
        ],
        [
            "name" => "Calendario",
            "short_name" => "Calendario",
            "description" => "Visualizza calendario",
            "url" => "./?page=calendario",
            "icons" => [["src" => "icons/icon-96x96.png", "sizes" => "96x96", "type" => "image/png"]]
        ]
    ],
    "launch_handler" => [
        "client_mode" => "focus-existing"
    ]
];

// Output JSON
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);