# Geräteprüfungen und offener Testumfang

## Bisher durchgeführt

- PHP-Syntaxprüfung aller Modul-, Bibliotheks- und Testdateien.
- 194 Prüfungen mit synthetischen Protokollantworten und SDK-Ersatz unter PHP 8.3.6.
- 130 OpenAPI-Prüfungen einschließlich vollständigem Modulablauf und vier native cURL-Anfragen gegen die aufgezeichnete Hersteller-SDK-Referenz.
- Sechs lokale WebSocket-Szenarien: Annahme, Fragmentierung plus Ping/Pong, Ablehnung, falsches Gerät, vorzeitiges Schließen, falscher Handshake.

Die Entwicklungsumgebung verwendet keine echten Dreo-Zugangsdaten oder Geräte. Beim Nutzer sind unter beta.6 am 30.10.2026 in Symcon mit einem EU-Konto und einem DR-HSH040S Anmeldung, Erkennung und Statuslesen über App-API bestätigt. Der letzte vorliegende Statusabruf ist 07:05:40 UTC mit StateValid=true. Die Geräte-Firmware wurde nicht mitgeteilt. Die grundlegende Ein/Aus-Steuerung ist durch Nutzer und Screenshot unter beta.6/beta.7 bestätigt. Nach beta.9 meldet der Nutzer eine gute Reaktion und wünscht die reguläre Projektfreigabe. Eine quantitative Latenzmessung, separate Mehrfachklick-Abnahme, weitere Schaltfunktionen und der Abgleich der Temperaturanzeige sind dadurch nicht automatisch belegt.

- 14 Adresscache-Prüfungen und native TLS-Prüfung von Hostname und Zertifikatsvertrauen.

## Noch offen

| Prüfung | Erwartetes Ergebnis | Ergebnis |
| --- | --- | --- |
| Bibliothek in Symcon 9 installieren | Keine PHP-, Bibliotheks-, Formular- oder API-Registrierungsfehler | Offen |
| Echte EU-Anmeldung und 720S-Suche über App-API | Eigenes Gerät in Auswahl | Bestätigt mit beta.6 am 30.10.2026 |
| Reales Statuslesen über App-API | StateValid=true, aktuelle erfolgreiche Abfrage | Bestätigt mit beta.6 am 30.10.2026 |
| EU-Anmeldung und Suche über OpenAPI | Eigenes Gerät in Auswahl | Nicht bestanden: HTTP 302 im Login unter beta.6 |
| Andere Geräte im selben Konto | Werden als nicht unterstützt nicht angeboten | Offen |
| Nordamerika und Regionskorrektur aus OpenAPI-Token | Anmeldung und Gerätezugriff auf korrekter Region | Offen |
| Kalter Start mit ausgeschaltetem Gerät | Gerät bleibt ausgeschaltet, keine Schaltbefehle | Offen |
| Neustart und Änderungen übernehmen | Gerät bleibt in seinem bisherigen Zustand | Offen |
| Ein/Aus-Grundfunktion | Gerätezustand ändert sich; tatsächlicher Status bestätigt | Nutzerbestätigung und Screenshot vom 30.10.2026; beta.6/beta.7 |
| Klick- und Versandlatenz unter beta.9 | commandTiming und reale Reaktion vergleichen; kalten und vorgewärmten Cache getrennt messen | Nutzerreaktion positiv; quantitative Messung offen |
| Schnelle Mehrfachklicks | Kein Sperr-Fehlerdialog; letzter gewünschter Zustand wird verarbeitet | Lokal mit SDK-Ersatz geprüft; real unter beta.8 offen |
| Wartende Befehle bei Reset, Ablauf und Fehler | Keine verspätete oder wiederholte Ausführung | Lokal mit SDK-Ersatz geprüft; real offen |
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

Nach jeder realen Prüfung Version, Region, Firmware, Datum und Ergebnis ergänzen. Die Projektversion 1.0.0 kennzeichnet die vom Projektinhaber angeforderte reguläre Freigabe. Sie erklärt diese offenen Hardwareprüfungen nicht nachträglich für bestanden und belegt noch keine Annahme im Symcon Module Store. Bei späteren API- oder Firmwareänderungen betroffene Prüfungen wiederholen.
