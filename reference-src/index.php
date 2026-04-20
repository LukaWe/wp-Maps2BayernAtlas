<?php
declare(strict_types=1);

/**
 * Maps to BayernAtlas Converter
 * Main entry point - handles routing and renders the frontend
 * 
 * @author Maps to BayernAtlas Team
 * @version 2.0.0
 */

// Include API handler
require_once __DIR__ . '/includes/api.php';

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/api/convert') !== false) {
    handleApiRequest();
}

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' && strpos($_SERVER['REQUEST_URI'], '/api/convert') !== false) {
    handleOptionsRequest();
}

// Render frontend
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Convert Google Maps and OpenStreetMap links to BayernAtlas URLs">
    <title data-i18n="page_title">Maps zu BayernAtlas Konverter</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="container">
    <header>
        <h1 data-i18n="header_title">Maps zu BayernAtlas</h1>
        <p class="subtitle" data-i18n="header_subtitle">Konvertiert Google Maps & OpenStreetMap Standorte zu offiziellen bayerischen Vermessungsdaten</p>
    </header>

    <!-- Navigation Bar -->
    <div class="nav-bar">
        <div class="tabs">
            <button class="tab-btn active" data-tab="converter" data-i18n="tab_converter">Konverter</button>
            <button class="tab-btn" data-tab="faq" data-i18n="tab_faq">FAQ</button>
        </div>
        <div class="lang-switcher">
            <button class="lang-btn active" data-lang="de">DE</button>
            <button class="lang-btn" data-lang="en">EN</button>
        </div>
    </div>

    <!-- Converter Tab -->
    <div id="tab-converter" class="tab-content active">
        <div class="input-group">
            <input type="text" id="gmapsUrl" data-i18n-placeholder="input_placeholder" placeholder="Google Maps oder OSM URL hier einfügen..." autocomplete="off">
        </div>

        <button id="convertBtn" class="btn-convert">
            <span class="btn-text" data-i18n="btn_convert">Link konvertieren</span>
            <div class="loading-spinner"></div>
        </button>

        <div id="resultArea" class="result-area">
            <div class="result-item">
                <div class="result-label" data-i18n="label_wgs84">Koordinaten (WGS84)</div>
                <div class="result-value" id="resCoords">-</div>
            </div>
            <div class="result-item">
                <div class="result-label" data-i18n="label_utm">UTM Zone 32N (Rechtswert / Hochwert)</div>
                <div class="result-value" id="resUtm">-</div>
            </div>
            <div class="result-item">
                <div class="result-label" data-i18n="label_link">BayernAtlas Link</div>
                <a href="#" class="result-link-box" id="resLink" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15,3 21,3 21,9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                    <span class="result-link-text" id="resUrl">—</span>
                    <button type="button" class="copy-btn" id="copyBtn" data-i18n-title="btn_copy_title" title="Link kopieren" onclick="event.preventDefault(); copyLink();">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </a>
            </div>
        </div>
    </div>

    <!-- FAQ Tab -->
    <div id="tab-faq" class="tab-content">
        <!-- URL Formats Section -->
        <div class="faq-section">
            <h2 data-i18n="faq_url_formats_title">Unterstützte URL-Formate</h2>
            <p data-i18n="faq_url_formats_intro">Der Konverter unterstützt folgende URL-Formate:</p>
            
            <div class="url-format">
                <strong data-i18n="faq_url_short">Google Maps - Gekürzte Links</strong>
                <code data-i18n="faq_url_short_example">https://maps.app.goo.gl/xyz123</code>
            </div>
            
            <div class="url-format">
                <strong data-i18n="faq_url_place">Google Maps - Ortslinks mit Marker</strong>
                <code data-i18n="faq_url_place_example">https://www.google.de/maps/place/Adresse/@lat,lon/data=!3d..!4d..</code>
            </div>
            
            <div class="url-format">
                <strong data-i18n="faq_url_coords">Google Maps - Koordinaten-Links</strong>
                <code data-i18n="faq_url_coords_example">https://www.google.com/maps/@48.137,11.575,15z</code>
            </div>

            <div class="url-format">
                <strong data-i18n="faq_url_osm">OpenStreetMap Links</strong>
                <code data-i18n="faq_url_osm_example">https://www.openstreetmap.org/#map=19/49.021931/12.081882</code>
            </div>
            
            <p><em data-i18n="faq_url_note">Bei Ortslinks wird der präzise Marker (!3d...!4d...) bevorzugt gegenüber der Kartenansicht (@lat,lon).</em></p>
        </div>

        <!-- API Documentation Section -->
        <div class="faq-section">
            <h2 data-i18n="faq_api_title">API Dokumentation</h2>
            <p data-i18n="faq_api_intro">Die REST-API ermöglicht die programmatische Konvertierung von URLs.</p>
            
            <h3 data-i18n="faq_api_endpoint">Endpunkt</h3>
            <div class="code-block"><pre>POST /api/convert</pre></div>
            
            <h3 data-i18n="faq_api_request">Anfrage-Format</h3>
            <div class="code-block"><pre>Content-Type: application/json

{
  "gmaps_url": "https://maps.app.goo.gl/xyz..."
}</pre></div>
            
            <h3 data-i18n="faq_api_response">Antwort-Format</h3>
            <div class="code-block"><pre>{
  "success": true,
  "bayernatlas_url": "https://atlas.bayern.de/...",
  "coordinates": {
    "lat": 48.137,
    "lon": 11.575,
    "easting": 691567,
    "northing": 5334734
  }
}</pre></div>
            
            <h3 data-i18n="faq_api_example">Beispiel mit cURL</h3>
            <div class="code-block"><pre>curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"gmaps_url":"https://maps.app.goo.gl/xyz"}' \
  https://yourserver.com/api/convert</pre></div>
        </div>

        <!-- Limitations Section -->
        <div class="faq-section">
            <h2 data-i18n="faq_limits_title">Einschränkungen</h2>
            <ul class="info-list">
                <li data-i18n="faq_limits_rate">Rate Limit: 10 Anfragen pro Minute pro IP-Adresse</li>
                <li data-i18n="faq_limits_region">Nur Standorte innerhalb Bayerns werden unterstützt</li>
                <li data-i18n="faq_limits_formats">Nur Google Maps und OpenStreetMap URLs werden erkannt</li>
            </ul>
        </div>

        <!-- Security Section -->
        <div class="faq-section">
            <h2 data-i18n="faq_security_title">Sicherheit</h2>
            <p data-i18n="faq_security_intro">Für sichere API-Nutzung beachten Sie:</p>
            <ul class="info-list">
                <li data-i18n="faq_security_origin">Origin-Whitelist: Die API prüft Origin- und Referer-Header</li>
                <li data-i18n="faq_security_https">Verwenden Sie HTTPS in Produktionsumgebungen</li>
                <li data-i18n="faq_security_rate">Rate Limiting schützt vor Missbrauch</li>
                <li data-i18n="faq_security_cors">CORS ist für zugelassene Ursprünge konfiguriert</li>
            </ul>
        </div>

        <!-- HTTP Status Codes Section -->
        <div class="faq-section">
            <h2 data-i18n="faq_codes_title">HTTP Status Codes</h2>
            <div class="status-codes">
                <div class="status-code success"><code>200</code> <span data-i18n="faq_code_200">Erfolgreiche Konvertierung</span></div>
                <div class="status-code error"><code>400</code> <span data-i18n="faq_code_400">Ungültige Anfrage (fehlende URL, ungültiges Format)</span></div>
                <div class="status-code error"><code>403</code> <span data-i18n="faq_code_403">Zugriff verweigert (nicht autorisierte Herkunft)</span></div>
                <div class="status-code warning"><code>422</code> <span data-i18n="faq_code_422">Standort außerhalb Bayerns</span></div>
                <div class="status-code warning"><code>429</code> <span data-i18n="faq_code_429">Rate Limit überschritten</span></div>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script src="assets/js/app.js"></script>

</body>
</html>
