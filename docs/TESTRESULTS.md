# Testprotokoll 0.1.0-beta.7

Datum: 30.10.2026. Lokale Laufzeit: PHP CLI 8.3.6.

| Prüfung | Ergebnis |
| --- | --- |
| PHP-Syntax sämtlicher PHP-Dateien | Bestanden |
| JSON-Parsing aller Konfigurationsdateien | Bestanden |
| Protokoll-/Modultests mit synthetischen Antworten und SDK-Ersatz | 176 Prüfungen bestanden |
| OpenAPI-Protokoll und vollständiger Modulablauf | 130 Prüfungen mit synthetischen Heizgerätedaten bestanden |
| Natives cURL gegen aufgezeichnete Hersteller-SDK-Anfragen | Vier Anfragen: Login, Liste, Status, Steuerung stimmen überein |
| Lokaler WebSocket: Annahme | Bestanden |
| Lokaler WebSocket: fragmentierte Antwort, Ping/Pong | Bestanden |
| Lokaler WebSocket: abgelehnter Befehl | Bestanden |
| Lokaler WebSocket: Antwort für ein anderes Gerät | Bestanden, keine falsche Annahme |
| Lokaler WebSocket: vorzeitiges Schließen | Bestanden, Fehler erkannt |
| Lokaler WebSocket: falsche Handshake-Prüfsumme | Bestanden, Verbindung abgelehnt |
| Stumme interne Aktionen, Dialogausgabe im Aufrufer, abgelehnter Kernelaufruf und anonymes HTTP 200/API 100000 | Bestanden mit SDK-Ersatz |
| Ausführung der JSON-onClick-Skripte: Dialogtexte, Fehlerpfad, direkte Klickprobe, Hintergrundaufträge | Bestanden mit SDK-Ersatz; reale Konsole noch offen |
| Asynchroner Teststart, sichtbare Rückmeldung und getrennte Timer-Schritte | Bestanden mit SDK-Ersatz |
| Teilresultate, wiederholter Klick, Abbruch bei Konfigurationsänderung | Bestanden mit SDK-Ersatz |
| Natives cURL: Timeout mit erhaltenen Fehler-/Laufzeitdaten | Bestanden |
| Natives cURL: anonymer IPv4-Test mit HTTP 405 | Bestanden |
| Anonyme Cloud-Abfrage beim Nutzer mit beta.5 | HTTP 200, API 100000, cURL 0; DNS etwa 5 s. Dies war kein Anmeldetest |
| Reale App-API-Anmeldung und 720S-Geräteliste, EU, beta.6 | HTTP 200/API 0, genau ein unterstütztes Gerät mit Seriennummer |
| Reales App-API-Statuslesen, beta.6 | HTTP 200/API 0, StateValid=true, letzter bestätigter Abruf 30.10.2026 07:05:40 UTC; Firmware nicht gemeldet |
| Reale OpenAPI-Anmeldung, beta.6 | HTTP 302 beim Login; kein Gerätelistenaufruf |
| Reale Schaltbefehle, Modi und Temperaturzuordnung | Noch offen |
| Echter Symcon-9-Kernel und Visualisierung | beta.6: Installation, Auswahl und Statuslesen in Symcon beim Nutzer bestätigt; neue Voreinstellung in beta.7 noch nicht real geprüft |
| GitHub-CI-Matrix PHP 8.2/8.3/8.4 | Status des jeweiligen Commits unter [GitHub Actions](https://github.com/Powermaniaxx/Symcon-Dreo/actions) |

Die Antworten und Heizgerätekonfigurationen sind synthetisch und beruhen auf Community- und Herstellerquellen. Die OpenAPI-Anfragereferenz wurde aus dem originalen Hersteller-Client aufgezeichnet. Sie beweisen nicht, dass jedes reale Konto oder jede Firmware dieselben Werte liefert. Vor öffentlicher stabiler Freigabe die offenen Prüfungen aus TESTPLAN.md durchführen.
