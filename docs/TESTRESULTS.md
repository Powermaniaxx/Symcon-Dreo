# Testprotokoll 0.1.0-beta.2

Datum: 29.10.2026. Lokale Laufzeit: PHP CLI 8.3.6.

| Prüfung | Ergebnis |
| --- | --- |
| PHP-Syntax sämtlicher PHP-Dateien | Bestanden |
| JSON-Parsing aller Konfigurationsdateien | Bestanden |
| Protokoll-/Modultests mit synthetischen Antworten und SDK-Ersatz | 127 Prüfungen bestanden |
| Lokaler WebSocket: Annahme | Bestanden |
| Lokaler WebSocket: fragmentierte Antwort, Ping/Pong | Bestanden |
| Lokaler WebSocket: abgelehnter Befehl | Bestanden |
| Lokaler WebSocket: Antwort für ein anderes Gerät | Bestanden, keine falsche Annahme |
| Lokaler WebSocket: vorzeitiges Schließen | Bestanden, Fehler erkannt |
| Lokaler WebSocket: falsche Handshake-Prüfsumme | Bestanden, Verbindung abgelehnt |
| Echte Anmeldung, Cloud und Dreo 720S | Nicht durchgeführt |
| Echter Symcon-9-Kernel und Visualisierung | Formular beim Nutzer sichtbar; vollständige Funktionsabnahme noch offen |
| GitHub-CI-Matrix PHP 8.2/8.3/8.4 | Beta 2 bestanden: [Testlauf 40966734184](https://github.com/Powermaniaxx/Symcon-Dreo/actions/runs/40966734184) |

Die synthetischen Antworten beruhen auf dem gelesenen Community-Protokoll. Sie beweisen nicht, dass jedes reale Konto oder jede Firmware dieselben Werte liefert. Vor öffentlicher stabiler Freigabe die offenen Prüfungen aus TESTPLAN.md durchführen.
