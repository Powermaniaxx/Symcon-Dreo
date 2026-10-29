# Testprotokoll 0.1.0-beta.6

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
| Echte Anmeldung und Dreo 720S | Erfolgreiche Anmeldung und Geräteabfrage noch nicht bestätigt |
| Echter Symcon-9-Kernel und Visualisierung | beta.5: Diagnose und erster Testschritt ausgeführt; zusätzliche Symcon-Fehlermeldung. beta.6 noch nicht praktisch geprüft |
| GitHub-CI-Matrix PHP 8.2/8.3/8.4 | Status des jeweiligen Commits unter [GitHub Actions](https://github.com/Powermaniaxx/Symcon-Dreo/actions) |

Die Antworten und Heizgerätekonfigurationen sind synthetisch und beruhen auf Community- und Herstellerquellen. Die OpenAPI-Anfragereferenz wurde aus dem originalen Hersteller-Client aufgezeichnet. Sie beweisen nicht, dass jedes reale Konto oder jede Firmware dieselben Werte liefert. Vor öffentlicher stabiler Freigabe die offenen Prüfungen aus TESTPLAN.md durchführen.
