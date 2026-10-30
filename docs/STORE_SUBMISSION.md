# Vorbereitete Einreichung im Symcon Module Store

Status: Quelldateien und Texte vorbereitet. Noch keine bestätigte Einreichung oder Store-Freigabe. Die Projektversion 1.0.0 und die Verfügbarkeit im Store sind getrennte Vorgänge.

## Stammdaten

| Feld | Wert |
| --- | --- |
| Bundle ID | io.github.powermaniaxx.dreo |
| Zielkanal | Stable |
| GIT-URL | https://github.com/Powermaniaxx/Symcon-Dreo.git |
| Branch | main |
| GIT-Commit | Den im GitHub-Actions-Lauf geprüften Commit der Version 1.0.0 im Portal auswählen |
| Projektversion | 1.0.0 |
| Autor | Powermaniaxx |
| Lokalisierung | Deutsch (de) |
| Anzeigename | Dreo 720S |
| Dokumentation | https://github.com/Powermaniaxx/Symcon-Dreo/blob/main/DreoHeater/README.md |
| Kategorie | Im Portal eine vorhandene passende Kategorie für Heizung/Klima auswählen; konkrete Portaloption noch nicht geprüft |

Bibliotheks-GUID: `{18DCB570-881D-4816-9F4B-57446659A5DC}`. Modul-GUID: `{8ECB7DF9-A42A-45E5-B868-65E7102A7B38}`. GUIDs bei Veröffentlichung und späteren Updates beibehalten.

## Beschreibung für die deutsche Lokalisierung

Bindet den Dreo 720S Smart-Heizlüfter (DR-HSH040S) direkt über die Dreo-Cloud an Symcon ab Version 9.0 an. Das Modul stellt Ein/Aus, die Betriebsarten Lüften/Heizen/ECO, Heizstufe H1–H3, Solltemperatur sowie Raumtemperatur in Celsius und Gerätestatus bereit. Home Assistant, MQTT und zusätzliche Dienste sind nicht erforderlich.

Die Einrichtung erfolgt mit dem eigenen Dreo-Konto und einer Gerätesuche. Schaltaufträge und die Bestätigung durch gelesene Gerätewerte werden getrennt angezeigt; schnelle Folgeklicks werden pro Einstellung zusammengefasst. Voraussetzung sind ein bereits in der Dreo-App eingerichteter Heizer und Internetzugang. Unterstützt wird ausschließlich DR-HSH040S. Der reguläre Zugang ist App-API; OpenAPI ist experimentell. Keine lokale LAN-Steuerung. Unabhängiges Community-Modul, kein offizielles Dreo-Produkt.

## Versionsinformation

Version 1.0.0: Erste reguläre Projektfreigabe für Dreo 720S / DR-HSH040S. Direkte Cloud-Anbindung mit Gerätesuche, Celsius-Anzeige, getrennten Betriebsarten und Heizstufen sowie Bestätigung von Schaltaufträgen anhand des gemeldeten Status. Folgeklicks werden verarbeitet, ohne parallele Übertragungen zu starten. Statusbestätigung und Vorbereitung der Serveradressen laufen im Hintergrund. Unterstützung ab Symcon 9.0.

## Angaben für das Review

- Die Kommunikation erfolgt direkt per HTTPS und kurzlebigem TLS-WebSocket; es werden keine fremden Symcon-Instanzen oder externen Symcon-Objekte angesprochen.
- Es werden ausschließlich eigene Statusvariablen und RegisterTimer-Timer angelegt. Benutzerprofile, Archivierung, Namen und Positionen bestehender Objekte werden nicht eigenmächtig geändert.
- Die PHP-Dateien lesen im Modulbetrieb nur die eigene form.json. Es werden keine Benutzerdaten in lokale Dateien geschrieben und keine weiteren Bibliotheken zur Laufzeit nachgeladen.
- Passwort und E-Mail sind normale Instanzeigenschaften. Token und kurzlebige DNS-Adressen liegen intern in Puffern. Diagnose ist auf bereinigte Daten beschränkt.
- Sprache der Oberfläche und Store-Lokalisierung: Deutsch. Weitere Sprachen werden bei der ersten Einreichung nicht angeboten.
- Aktuelle Nachweise: EU-Anmeldung, 720S-Erkennung, Statuslesen und grundlegende Ein/Aus-Steuerung am echten Gerät; positive Nutzerrückmeldung zur Reaktion nach beta.9. Betriebsarten, Heizstufen, einzelne Solltemperaturen, weitere Firmwares und Nordamerika sind nicht separat real bestätigt.
- Der offizielle Symcon-Validator und die Protokoll-/Modultests laufen in GitHub Actions. Automatisierte und reale Nachweise sind in TESTRESULTS.md getrennt aufgeführt.

## Einreichung

Über https://account.symcon.de/ den Entwicklerbereich öffnen und das Bundle anlegen. Stable als Zielkanal wählen, den geprüften Git-Commit auswählen und die deutsche Lokalisierung mit den obigen Texten ausfüllen. Vorhandene passende Kategorie auswählen. Erst ein erfolgreicher Portalstatus belegt die Einreichung; erst die Annahme durch das Symcon-Team belegt die Store-Freigabe.

Anforderungen: https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/store/einreichen/
