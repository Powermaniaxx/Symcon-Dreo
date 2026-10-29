# Reale Abnahme vor einer stabilen Veröffentlichung

## Bisher durchgeführt

- PHP-Syntaxprüfung aller Modul-, Bibliotheks- und Testdateien.
- 176 Prüfungen mit synthetischen Protokollantworten und SDK-Ersatz unter PHP 8.3.6.
- 130 OpenAPI-Prüfungen einschließlich vollständigem Modulablauf und vier native cURL-Anfragen gegen die aufgezeichnete Hersteller-SDK-Referenz.
- Sechs lokale WebSocket-Szenarien: Annahme, Fragmentierung plus Ping/Pong, Ablehnung, falsches Gerät, vorzeitiges Schließen, falscher Handshake.

Die Entwicklungsumgebung verwendet keine echten Dreo-Zugangsdaten oder Geräte. Die Installation und das Formular sind inzwischen beim Nutzer in Symcon sichtbar; die Gerätesuche bleibt dort noch leer. Eine anonyme Cloud-Abfrage erreichte beim Nutzer mit beta.5 HTTP 200; erfolgreiche Anmeldung und die Steuerung am echten Gerät sind noch nicht bestätigt.

## Noch offen

| Prüfung | Erwartetes Ergebnis | Ergebnis |
| --- | --- | --- |
| Bibliothek in Symcon 9 installieren | Keine PHP-, Bibliotheks-, Formular- oder API-Registrierungsfehler | Offen |
| Echte EU-Anmeldung und 720S-Suche über OpenAPI | Eigenes Gerät in Auswahl, kein Gerät fremder Konten | Offen |
| Andere Geräte im selben Konto | Werden als nicht unterstützt nicht angeboten | Offen |
| Nordamerika und Regionskorrektur aus OpenAPI-Token | Anmeldung und Gerätezugriff auf korrekter Region | Offen |
| Kalter Start mit ausgeschaltetem Gerät | Gerät bleibt ausgeschaltet, keine Schaltbefehle | Offen |
| Neustart und Änderungen übernehmen | Gerät bleibt in seinem bisherigen Zustand | Offen |
| Ein/Aus in Konsole und Visualisierung | Gerätezustand ändert sich; erst tatsächlicher Status bestätigt | Offen |
| Lüften, Heizen und ECO | Zuordnung korrekt; Moduswechsel schaltet ausgeschaltetes Gerät nicht ein | Offen |
| H1, H2, H3 | Korrekte Leistungsauswahl im Heizmodus | Offen |
| ECO-Sollwerte 5, 17, 20, 21, 29, 35 °C | App und Gerät zeigen denselben Celsius-Sollwert | Offen |
| OpenAPI-Konfiguration des realen DR-HSH040S | Temperatureinheit, Bereich und drei eindeutige Modi entsprechen den echten Gerätedaten | Offen |
| Raumtemperatur und Kalibrierung | Celsius korrekt; Offset nicht doppelt eingerechnet | Offen |
| Heizelement wechselt selbständig | Lesender Heizstatus folgt, keine PTC-Schreibbefehle | Offen |
| Änderung in Hersteller-App | Folgende erfolgreiche Abfrage übernimmt Werte | Offen |
| Gerät ausstecken / Cloud offline | Offline oder unbekannt sichtbar, kein falscher Erfolgszustand | Offen |
| Internet-/DNS-/TLS-Fehler | Zeitlimit, ungültiger Status, Pause, keine Endlosschleife | Offen |
| Token läuft ab | Ein Login-Versuch und eine Wiederholung; kein unendlicher Login | Offen |
| Passwort falsch | Verständlicher Fehler, automatische Login-Versuche stoppen | Offen |
| Cloud akzeptiert, Gerät reagiert nicht | Kein optimistischer Ein-Wert; Auftrag wird nicht bestätigt | Offen |
| Zweites Konto / geteilte Geräte | Tatsächliche Grenzen prüfen und dokumentieren | Offen |
| Zwei gleichzeitig genutzte Instanzen | Keine ungewollten Tokenwechsel oder überhöhte API-Last | Offen |
| Sicherung, Update und Wiederherstellung | GUIDs, Konfiguration und Variablen bleiben passend | Offen |
| Diagnose und Debug | Keine Kontodaten, Passwörter, Token oder Seriennummern | Offen |

Nach jeder realen Prüfung Version, Region, Firmware, Datum und Ergebnis ergänzen. Bis zur erfolgreichen Abnahme bleibt das Paket ausdrücklich eine Vorabversion. Bei späteren API- oder Firmwareänderungen betroffene Prüfungen wiederholen.
