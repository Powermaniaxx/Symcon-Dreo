# Testprotokoll 1.1.0

Datum: 30.10.2026. Lokale Laufzeit: PHP CLI 8.3.6.

| Prüfung | Ergebnis |
| --- | --- |
| PHP-Syntax sämtlicher PHP-Dateien | Bestanden |
| JSON-Parsing aller Konfigurationsdateien | Bestanden |
| Protokoll-/Modultests mit synthetischen Antworten und SDK-Ersatz | 194 Prüfungen bestanden |
| 39 Modellprofile, gemischtes Konto und beide Geräteabfrage-Routen | 241 Prüfungen in tests/models.php bestanden; synthetische Daten |
| Geräteartabhängige Aktionen, Grenzen, unbekannte Werte und getrennte Feuchtigkeitsziele | Bestanden mit synthetischen Daten und SDK-Ersatz |
| Alte 720S-Variablen, neue Variablen bei ApplyChanges, Typwechsel und Folgeklicks für Lüfter | Bestanden mit SDK-Ersatz |
| Zusätzliche Modelle am physischen Gerät | Noch offen; keine reale Bestätigung aus den automatischen Tests abgeleitet |
| Adresscache und unabhängige Hintergrundauflösung | 14 Prüfungen ohne öffentliches DNS bestanden |
| Natives TLS mit zwischengespeicherter IP | Passender Host akzeptiert, falscher Host und nicht vertrauenswürdiges Zertifikat abgelehnt |
| Kein HTTP-Statuslesen im Klick, Wiederaufnahme nach abgelaufenem Folgeauftrag | Bestanden mit SDK-Ersatz |
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
| Natives cURL: CURLOPT_RESOLVE und Cache-Invalidierung nach Timeout | Bestanden |
| Reale Reaktion nach beta.9 | Nutzer meldet „sehr gut“ und wünscht reguläre Freigabe; keine quantitative commandTiming-Messung geliefert |
| Anonyme Cloud-Abfrage beim Nutzer mit beta.5 | HTTP 200, API 100000, cURL 0; DNS etwa 5 s. Dies war kein Anmeldetest |
| Reale App-API-Anmeldung und 720S-Geräteliste, EU, beta.6 | HTTP 200/API 0, genau ein unterstütztes Gerät mit Seriennummer |
| Reales App-API-Statuslesen, beta.6 | HTTP 200/API 0, StateValid=true, letzter bestätigter Abruf 30.10.2026 07:05:40 UTC; Firmware nicht gemeldet |
| Reale OpenAPI-Anmeldung, beta.6 | HTTP 302 beim Login; kein Gerätelistenaufruf |
| Reale Ein/Aus-Grundfunktion unter beta.6/beta.7 | Nutzer bestätigt Schalten; Screenshot zeigt „Durch Gerätestatus bestätigt“. Mehrfachklick erzeugte bisher eine unbehandelte Sperr-Exception |
| Warteschlange: gleiche/gegenläufige Klicks, letzter Wunsch, Ablauf, Reset und Fehler | Bestanden mit SDK-Ersatz und simuliertem Wiedereintritt; reale Prüfung von beta.8 offen |
| Reale Modi, Heizstufen und Temperaturzuordnung | Noch offen |
| Echter Symcon-9-Kernel und Visualisierung | beta.6: Installation, Auswahl und Statuslesen in Symcon beim Nutzer bestätigt; neue Voreinstellung in beta.7 noch nicht real geprüft |
| GitHub-CI-Matrix PHP 8.2/8.3/8.4/8.5 und offizieller Symcon-Validator | Status des jeweiligen Commits unter [GitHub Actions](https://github.com/Powermaniaxx/Symcon-Dreo/actions) |

Die Antworten und Heizgerätekonfigurationen sind synthetisch und beruhen auf Community- und Herstellerquellen. Die OpenAPI-Anfragereferenz wurde aus dem originalen Hersteller-Client aufgezeichnet. Sie beweisen nicht, dass jedes reale Konto oder jede Firmware dieselben Werte liefert. Die offene Hardwareprüfung in TESTPLAN.md bleibt von der Projektfreigabe 1.1.0 und einer späteren Store-Prüfung getrennt.

Ab 1.1.0 steht ein erfolgreicher Status für die Auswertung des jeweiligen Profils, nicht für die Vollständigkeit aller denkbaren Gerätefunktionen. Ein gültiger Power-Wert ist Pflicht. Unbekannte oder fehlende optionale Werte werden ausgeblendet und sind nicht schaltbar; die alte 720S-Pflichtprüfung bleibt erhalten. Tests von tatsächlicher Haptik, Beschriftungen auf Geräten, Firmwarevarianten und physischer Wirkung der neuen Modelle stehen aus.
