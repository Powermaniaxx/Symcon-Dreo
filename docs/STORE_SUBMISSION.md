# Vorbereitete Einreichung im Symcon Module Store

Status: Quelldateien und Texte vorbereitet. Noch keine bestätigte Einreichung oder Store-Freigabe. Die Projektversion 1.1.0 und die Verfügbarkeit im Store sind getrennte Vorgänge.

## Stammdaten

| Feld | Wert |
| --- | --- |
| Bundle ID | io.github.powermaniaxx.dreo |
| Zielkanal | Stable |
| GIT-URL | https://github.com/Powermaniaxx/Symcon-Dreo.git |
| Branch | main |
| GIT-Commit | Den im GitHub-Actions-Lauf geprüften Commit der Version 1.1.0 im Portal auswählen |
| Projektversion | 1.1.0 |
| Autor | Powermaniaxx |
| Lokalisierung | Deutsch (de) |
| Anzeigename | Dreo Modul |
| Dokumentation | https://github.com/Powermaniaxx/Symcon-Dreo/blob/main/DreoHeater/README.md |
| Kategorie | Im Portal eine vorhandene passende Kategorie für Heizung/Klima auswählen; konkrete Portaloption noch nicht geprüft |

Bibliotheks-GUID: `{18DCB570-881D-4816-9F4B-57446659A5DC}`. Modul-GUID: `{8ECB7DF9-A42A-45E5-B868-65E7102A7B38}`. GUIDs bei Veröffentlichung und späteren Updates beibehalten.

## Beschreibung für die deutsche Lokalisierung

Das Dreo Modul verbindet unterstützte Dreo-Geräte über das eigene Dreo-Konto direkt mit Symcon ab Version 9.0. Enthalten sind 39 Modellprofile für Heizgeräte, Ventilatoren, Luftreiniger und Luftbefeuchter. Je nach Modell und gelieferten Statuswerten stehen Ein/Aus, Betriebsarten, Heiz- oder Lüfterstufen, Celsius-Temperaturen, horizontales Schwenken und Feuchtigkeitseinstellungen zur Verfügung.

Die Geräte müssen vorher in der Dreo-App eingerichtet sein. Home Assistant, MQTT oder zusätzliche Dienste sind nicht erforderlich. Pro Gerät wird eine eigene Instanz verwendet. Cloud-Annahme und Bestätigung durch einen gelesenen Gerätestatus werden getrennt angezeigt.

Unterstützt werden die Modelle der verlinkten Dokumentation. Die Nutzung in der Dreo-App allein garantiert keine Kompatibilität. Die grundlegende Anbindung ist am EU-Dreo 720S (DR-HSH040S) bestätigt. Die zusätzlichen Modellprofile sind anhand der Protokollreferenz implementiert und automatisch geprüft; reale Tests stehen noch aus. Internetzugang erforderlich, keine lokale LAN-Steuerung. Unabhängiges Community-Modul, kein offizielles Dreo-Produkt.

## Versionsinformation

Version 1.1.0 erweitert die bisherige 720S-Anbindung auf 39 konkrete Modellprofile aus vier Gerätearten. Verfügbare Funktionen und Wertebereiche werden modellabhängig angezeigt. Neu sind Lüfterstufen, horizontales Schwenken, Luftfeuchtigkeit, Soll-Feuchtigkeit und Nebelstufe. Bestehende 720S-Instanzen bleiben erhalten. Die zusätzlichen Profile sind automatisch geprüft, aber noch nicht am echten Gerät bestätigt. Der reguläre Zugang ist App-API; OpenAPI bleibt experimentell und auf den 720S beschränkt.

## Angaben für das Review

- Die Kommunikation erfolgt direkt per HTTPS und kurzlebigem TLS-WebSocket; es werden keine fremden Symcon-Instanzen oder externen Symcon-Objekte angesprochen.
- Es werden ausschließlich eigene Statusvariablen und RegisterTimer-Timer angelegt. Eigene optionale Variablen werden je nach verfügbarer Funktion ein- oder ausgeblendet; ihre IDs bleiben bestehen. Benutzerprofile, Archivierung, Namen und Positionen bestehender Objekte werden nicht eigenmächtig geändert.
- Die PHP-Dateien lesen im Modulbetrieb nur die eigene form.json. Es werden keine Benutzerdaten in lokale Dateien geschrieben und keine weiteren Bibliotheken zur Laufzeit nachgeladen.
- Passwort und E-Mail sind normale Instanzeigenschaften. Token und kurzlebige DNS-Adressen liegen intern in Puffern. Diagnose ist auf bereinigte Daten beschränkt.
- Sprache der Oberfläche und Store-Lokalisierung: Deutsch. Weitere Sprachen werden bei der ersten Einreichung nicht angeboten.
- Aktuelle Nachweise: EU-Anmeldung, 720S-Erkennung, Statuslesen und grundlegende Ein/Aus-Steuerung am echten Gerät; positive Nutzerrückmeldung zur Reaktion nach beta.9. Betriebsarten, Heizstufen, einzelne Solltemperaturen, weitere Firmwares und Nordamerika sind nicht separat real bestätigt.
- Die 39 Modellprofile und bekannte Grenzen stehen in docs/SUPPORTED_MODELS.md. Für die zusätzlichen Modelle liegen noch keine realen Hardwaretests vor.
- Der offizielle Symcon-Validator und die Protokoll-/Modultests laufen in GitHub Actions. Automatisierte und reale Nachweise sind in TESTRESULTS.md getrennt aufgeführt.

## Einreichung

Über https://account.symcon.de/ den Entwicklerbereich öffnen und das Bundle anlegen. Stable als Zielkanal wählen, den geprüften Git-Commit auswählen und die deutsche Lokalisierung mit den obigen Texten ausfüllen. Vorhandene passende Kategorie auswählen. Erst ein erfolgreicher Portalstatus belegt die Einreichung; erst die Annahme durch das Symcon-Team belegt die Store-Freigabe.

Anforderungen: https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/store/einreichen/
