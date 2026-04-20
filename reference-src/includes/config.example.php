<?php
declare(strict_types=1);

/**
 * Application Configuration
 * Contains constants, Bavaria polygon, and security settings
 */

// Bavaria polygon boundary (expanded ~5km beyond actual border for full coverage)
// BayernAtlas also provides data for surrounding areas, so we include a buffer
// Coordinates in [lon, lat] format (WGS84)
const BAVARIA_POLYGON = [
    [8.90, 50.10],     // Northwest - beyond Aschaffenburg
    [9.35, 50.30],     // North - beyond Bad Kissingen
    [9.85, 50.50],     // North - beyond Coburg
    [10.40, 50.50],    // North - beyond Sonneberg
    [10.85, 50.60],    // Northeast - beyond Hof
    [11.50, 50.55],    // North border
    [12.00, 50.50],    // Near Czech border
    [12.15, 50.40],    // Fichtelgebirge
    [12.35, 50.15],    // Beyond Marktredwitz
    [12.60, 50.00],    // Czech border
    [12.85, 50.00],    // Beyond Waldsassen
    [13.10, 49.80],    // Oberpfalz
    [13.25, 49.60],    // Beyond Furth im Wald
    [13.50, 49.40],    // Czech border
    [13.60, 49.20],    // Beyond Cham
    [13.65, 49.00],    // Bavarian Forest
    [13.85, 48.85],    // Beyond Zwiesel
    [13.95, 48.70],    // Beyond Grafenau
    [13.90, 48.55],    // Bavarian Forest south
    [13.85, 48.40],    // Beyond Passau
    [13.55, 48.20],    // Austrian border
    [13.10, 48.20],    // Beyond Simbach
    [12.95, 48.15],    // Inn river
    [12.80, 47.90],    // Beyond Burghausen
    [13.10, 47.75],    // Austrian border
    [12.95, 47.60],    // Beyond Salzburg border
    [12.80, 47.55],    // Chiemsee area
    [12.50, 47.55],    // Beyond Traunstein
    [12.25, 47.50],    // Beyond Ruhpolding
    [12.00, 47.35],    // Beyond Berchtesgaden
    [11.50, 47.38],    // Alps - beyond Garmisch
    [11.20, 47.28],    // Beyond Mittenwald
    [10.90, 47.28],    // Alps
    [10.70, 47.40],    // Beyond Füssen
    [10.40, 47.45],    // Allgäu Alps
    [10.10, 47.38],    // Beyond Oberstdorf
    [10.00, 47.45],    // Kleinwalsertal
    [9.90, 47.43],     // Beyond Lindau approach
    [9.60, 47.43],     // Lake Constance
    [9.40, 47.45],     // Bodensee extended
    [9.45, 47.70],     // Northwest of Kempten
    [9.60, 47.85],     // Allgäu north
    [9.65, 48.00],     // Beyond Memmingen
    [9.75, 48.20],     // Beyond Illertissen
    [9.90, 48.35],     // Neu-Ulm area
    [9.95, 48.55],     // Extended Ulm area
    [9.85, 48.75],     // Franconia
    [9.80, 48.95],     // Beyond Nördlingen
    [9.60, 49.15],     // Beyond Dinkelsbühl
    [9.35, 49.30],     // Beyond Rothenburg
    [9.30, 49.50],     // Beyond Würzburg
    [9.25, 49.70],     // Spessart
    [9.10, 49.80],     // Beyond Lohr
    [8.95, 49.95],     // Beyond Aschaffenburg
    [8.90, 50.10],     // Close polygon
];

// Rate limiting configuration
const API_RATE_LIMIT = 10; // Requests per minute

// API Security: Whitelist of allowed origins/referers for API access
// Add your allowed domains here. Empty array = no restrictions (not recommended for production)
const API_ALLOWED_ORIGINS = [
    'http://localhost',
    'http://localhost:8080',
    'https://localhost',
];

// IP Whitelist - IPs that bypass rate limiting
const IP_WHITELIST = [
    '127.0.0.1',
    '::1',
];
