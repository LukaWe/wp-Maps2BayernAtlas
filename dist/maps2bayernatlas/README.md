# Maps2BayernAtlas WordPress Plugin

WordPress-Plugin zur Umwandlung von Google-Maps- und OpenStreetMap-Links in aktuelle BayernAtlas-Links, inklusive Bulk-Modus und Admin-Einstellungen.

## Shortcode

```text
[maps2bayernatlas]
```

Optionale Attribute:

```text
[maps2bayernatlas zoom="16" title="Kartenkonverter" button="Jetzt umwandeln"]
```

Der Shortcode bietet zwei Modi:

- Einzelumwandlung mit Koordinaten- und Linkausgabe
- Bulk-Umwandlung mit bis zu 10 URLs gleichzeitig, Sammel-Ausgabe und Ergebnisliste pro URL

## Installation

1. Diesen Ordner als Plugin nach `wp-content/plugins/maps2bayernatlas` kopieren.
2. Plugin in WordPress aktivieren.
3. Den Shortcode auf einer Seite oder in einem Beitrag einfügen.

## Technische Punkte

- REST-Endpunkt: `/wp-json/maps2bayernatlas/v1/convert`
- REST-Bulk-Endpunkt: `/wp-json/maps2bayernatlas/v1/convert-batch`
- Unterstützt:
  - Google Maps lang
  - Google Maps Kurzlinks (`maps.app.goo.gl`, `goo.gl`)
  - OpenStreetMap (`#map=...`, `mlat`/`mlon`)
- Admin-Einstellungen unter `Einstellungen -> Maps2BayernAtlas`:
  - Umwandlungen pro Minute pro IP
  - Mindestabstand zwischen Anfragen
  - Sperre für identische Wiederholungsanfragen
  - Maximale Anzahl URLs pro Bulk-Anfrage
- Sicherheitsmaßnahmen:
  - Host-Whitelist für unterstützte Kartendienste
  - sichere HTTP-Auflösung von Kurzlinks über die WordPress HTTP API
  - Rate-Limit und Spam-Schutz per WordPress-Transient
  - keine direkte Dateispeicherung im Temp-Verzeichnis
