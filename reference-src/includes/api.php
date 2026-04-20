<?php
declare(strict_types=1);

/**
 * API Endpoint Handler
 * Processes POST requests to /api/convert
 */

// Require dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes.php';

/**
 * Check if origin/referer is allowed
 */
function isOriginAllowed(): bool {
    // If whitelist is empty, allow all (for development)
    if (empty(API_ALLOWED_ORIGINS)) {
        return true;
    }

    // Check Origin header
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!empty($origin)) {
        foreach (API_ALLOWED_ORIGINS as $allowed) {
            if (strpos($origin, $allowed) === 0) {
                return true;
            }
        }
    }

    // Check Referer header as fallback
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer)) {
        foreach (API_ALLOWED_ORIGINS as $allowed) {
            if (strpos($referer, $allowed) === 0) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Handle API request
 */
function handleApiRequest(): void {
    header('Content-Type: application/json; charset=utf-8');
    
    // CORS Headers
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!empty($origin) && (empty(API_ALLOWED_ORIGINS) || in_array($origin, API_ALLOWED_ORIGINS, true))) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Origin/Referer Whitelist Check
    if (!isOriginAllowed()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access from this origin not allowed']);
        exit;
    }

    // Rate Limiting
    $limiter = new RateLimiter();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!$limiter->check($ip)) {
        http_response_code(429);
        header('Retry-After: 60');
        echo json_encode(['success' => false, 'message' => 'Rate limit exceeded']);
        exit;
    }

    // Content-Type validation
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Content-Type must be application/json']);
        exit;
    }

    // Input Parsing
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['gmaps_url'] ?? '';

    if (empty($url)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing gmaps_url']);
        exit;
    }

    // Sanitize URL
    $url = filter_var($url, FILTER_SANITIZE_URL);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
        exit;
    }

    // Processing
    $coords = LinkParser::extractCoordinates($url);
    
    if (!$coords) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Could not extract coordinates from URL']);
        exit;
    }

    if (!GeoConverter::isInsideBavaria($coords['lat'], $coords['lon'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false, 
            'message' => 'Location is outside Bavaria',
            'coordinates' => $coords
        ]);
        exit;
    }

    $utm = GeoConverter::wgs84ToUtm32($coords['lat'], $coords['lon']);
    
    // Build BayernAtlas URL
    $baUrl = sprintf(
        'https://atlas.bayern.de/?c=%d,%d&z=16&r=0&l=atkis&crh=true&mid=1',
        $utm['easting'], 
        $utm['northing']
    );

    echo json_encode([
        'success' => true,
        'bayernatlas_url' => $baUrl,
        'coordinates' => [
            'lat' => $coords['lat'],
            'lon' => $coords['lon'],
            'easting' => $utm['easting'],
            'northing' => $utm['northing']
        ]
    ]);
    exit;
}

/**
 * Handle OPTIONS preflight request
 */
function handleOptionsRequest(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}
