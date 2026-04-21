# Maps2BayernAtlas WordPress Plugin

WordPress plugin for converting Google Maps and OpenStreetMap links into current BayernAtlas links, including bulk mode and admin settings.

## Shortcode

```text
[maps2bayernatlas]
```

Optional attributes:

```text
[maps2bayernatlas zoom="16" title="Map Converter" button="Convert Now"]
```

The shortcode provides two modes:

- Single conversion with coordinate and link output
- Bulk conversion with up to 10 URLs simultaneously, collective output, and result list per URL

## Installation

1. Copy this folder as a plugin to `wp-content/plugins/maps2bayernatlas`.
2. Activate the plugin in WordPress.
3. Insert the shortcode into a page or post.

## Technical Details

- REST endpoint: `/wp-json/maps2bayernatlas/v1/convert`
- REST bulk endpoint: `/wp-json/maps2bayernatlas/v1/convert-batch`
- Supported:
  - Google Maps (long URLs)
  - Google Maps short links (`maps.app.goo.gl`, `goo.gl`)
  - OpenStreetMap (`#map=...`, `mlat`/`mlon`)
- Admin settings under `Settings -> Maps2BayernAtlas`:
  - Conversions per minute per IP
  - Minimum delay between requests
  - Lock for identical repeat requests
  - Maximum number of URLs per bulk request
- Security measures:
  - Host whitelist for supported map services
  - Secure HTTP resolution of short links via the WordPress HTTP API
  - Rate limiting and spam protection via WordPress Transients
  - No direct file storage in temp directories

## License & Acknowledgements

This plugin is licensed under the **MIT License**.

It is based on the [Maps2BayernAtlas](https://github.com/LukaWe/Maps2BayernAtlas) project, which provided the basis for the mathematical coordinate transformation (WGS84 to UTM Zone 32N) and the detection of Bavarian borders. The core logic was ported to object-oriented PHP for this WordPress plugin.

---

# Maps2BayernAtlas WordPress Plugin (Deutsch)

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

## Lizenz & Danksagung

Dieses Plugin steht unter der **MIT-Lizenz**.

Es basiert auf dem [Maps2BayernAtlas](https://github.com/LukaWe/Maps2BayernAtlas) Projekt, welches die Grundlage für die mathematische Koordinatentransformation (WGS84 zu UTM Zone 32N) und die Erkennung der bayerischen Grenzen lieferte. Die Kernlogik wurde für dieses WordPress-Plugin in objektorientiertes PHP portiert.

---

# Maps2BayernAtlas WordPress Plugin (Bayerisch)

Servus! Des is des Maps2BayernAtlas Plugin für WordPress. Damit kannst de ganzen Google-Links und OpenStreetMap-Schmarrn endlich in gscheide BayernAtlas-Links umwandeln. Praktisch, wennst wissen willst, wo genau d'Grenz am Acker is.

## Shortcode

Pack des einfach nei:
```text
[maps2bayernatlas]
```

Wennst es schick haben willst:
```text
[maps2bayernatlas zoom="16" title="Bayern-Finder" button="Auf geht's!"]
```

## Was des Ding alles ko

- Einzelne Links umwandeln (für d'Schnellen)
- Bulk-Modus (wennst glei a ganze Liste hast, bis zu 10 Stück auf oamoi)
- Admin-Kram: Kannst einstellen, wie oft oana rumklicken darf (Spam-Schutz, gell?)

## Einbau

1. Schieb den Ordner nach `wp-content/plugins/maps2bayernatlas`.
2. Druck auf 'Aktivieren' im WordPress-Backend.
3. Pack den Shortcode auf dei Seitn. Passt.

## Technik-G'schmarre

- Host-Whitelist: Mir lassn bloß Google und OSM nei.
- Rate-Limit: Damit dei Server ned ins Schwitzn kummt.
- Koane Dateien im Temp-Ordner: Mir mistsn hintern uns wieder aus.

## Lizenz & Danksagung

Des Ganze lafft unter der **MIT-Lizenz**. Es basiert auf dem [Maps2BayernAtlas](https://github.com/LukaWe/Maps2BayernAtlas) Projekt, des de ganze Rechnerei übernommen hat. 

Habe die Ehre!
