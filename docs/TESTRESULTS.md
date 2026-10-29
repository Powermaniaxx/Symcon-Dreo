# Testprotokoll 0.1.0-beta.4

Datum: 29.10.2026. Lokale Laufzeit: PHP CLI 8.3.6.

| Prüfung | Ergebnis |
| --- | --- |
| PHP-Syntax sämtlicher PHP-Dateien | Bestanden |
| JSON-Parsing aller Konfigurationsdateien | Bestanden |
| Protokoll-/Modultests mit synthetischen Antworten und SDK-Ersatz | 170 Prüfungen bestanden |
| Lokaler WebSocket: Annahme | Bestanden |
| Lokaler WebSocket: fragmentierte Antwort, Ping/Pong | Bestanden |
| Lokaler WebSocket: abgelehnter Befehl | Bestanden |
| Lokaler WebSocket: Antwort für ein anderes Gerät | Bestanden, keine falsche Annahme |
| Lokaler WebSocket: vorzeitiges Schließen | Bestanden, Fehler erkannt |
| Lokaler WebSocket: falsche Handshake-Prüfsumme | Bestanden, Verbindung abgelehnt |
| Ausführung der JSON-onClick-Skripte: Dialogtexte, Fehlerpfad, direkte Klickprobe, Hintergrundaufträge | Bestanden mit SDK-Ersatz; reale Konsole noch offen |
| Asynchroner Teststart, sichtbare Rückmeldung und getrennte Timer-Schritte | Bestanden mit SDK-Ersatz |
| Teilresultate, wiederholter Klick, Abbruch bei Konfigurationsänderung | Bestanden mit SDK-Ersatz |
| Natives cURL: Timeout mit erhaltenen Fehler-/Laufzeitdaten | Bestanden |
| Natives cURL: anonymer IPv4-Test mit HTTP 405 | Bestanden |
| Echte Anmeldung, Cloud und Dreo 720S | Beim Nutzer bisher cURL 28; erfolgreiche Anmeldung noch nicht bestätigt |
| Echter Symcon-9-Kernel und Visualisierung | Formular beim Nutzer sichtbar; vollständige Funktionsabnahme noch offen |
| GitHub-CI-Matrix PHP 8.2/8.3/8.4 | Status des jeweiligen Commits unter [GitHub Actions](https://github.com/Powermaniaxx/Symcon-Dreo/actions) |

Die synthetischen Antworten beruhen auf dem gelesenen Community-Protokoll. Sie beweisen nicht, dass jedes reale Konto oder jede Firmware dieselben Werte liefert. Vor öffentlicher stabiler Freigabe die offenen Prüfungen aus TESTPLAN.md durchführen.
