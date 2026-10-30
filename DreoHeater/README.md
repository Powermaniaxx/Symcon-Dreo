# Dreo 720S – Modulhandbuch

Verbindet einen Dreo 720S / DR-HSH040S mit Symcon ab Version 9.0. Voraussetzung sind ein eigenes Dreo-E-Mail-/Passwort-Konto, ein in der Dreo-App eingerichteter Heizer und ausgehender Internetzugriff über HTTPS/WebSocket. Der normale Zugang ist **App-API**. Die OpenAPI ist experimentell. Eine lokale LAN-Anbindung ist nicht enthalten.

Projekt und Installation: https://github.com/Powermaniaxx/Symcon-Dreo

## Einstellungen

| Einstellung | Bedeutung |
| --- | --- |
| Aktiv | Automatische Statusabfragen und Schalten erlauben |
| Dreo-Konto | E-Mail-Adresse des Dreo-Kontos |
| Dreo-Passwort | Das echte Kontopasswort; kein Hash oder Zugriffstoken |
| Cloud-Zugang | App-API als Standard; OpenAPI ist experimentell |
| Kontoregion | Europa oder Nordamerika; real geprüft ist bisher Europa |
| Gerät | Über Geräte suchen ermittelter DR-HSH040S |
| Statusabfrage | 30 bis 3600 Sekunden, Standard 30 |

Zugangsdaten zunächst speichern, **Geräte suchen** ausführen und anschließend das Gerät auswählen. Aktiv einschalten und erneut Änderungen übernehmen. Die erste Statusabfrage abwarten.

## Variablen

| Ident | Bedeutung |
| --- | --- |
| Power | Ein/Aus, bool |
| Mode | 0 = Nur Lüften, 1 = Heizen, 2 = ECO |
| HeatLevel | H1 bis H3 |
| TargetTemperature | Solltemperatur in ganzen °C, 5 bis 35; wirksam in ECO |
| CurrentTemperature | Raumtemperatur in °C |
| Heating | Gemeldeter Zustand des Heizelements |
| HeatingKnown | Heizstatus tatsächlich in der letzten gültigen Antwort vorhanden |
| Online | Gerät von der Cloud ausdrücklich als online gemeldet |
| Availability | Online, offline oder unbekannt |
| StateValid | Letzte vollständige Statusabfrage gültig |
| LastUpdate / LastUpdateUnix | Letzte erfolgreiche Abfrage als UTC-Text beziehungsweise Unixzeit |
| LastCommand | Fortschritt und Bestätigung des letzten Schaltauftrags |
| LastError | Letzter Fehler |
| ConnectionTestStatus | Fortschritt des anonymen Erreichbarkeitstests |

Erst bei StateValid=true Messwerte als gültig verwenden; für Automationen auch das Alter von LastUpdateUnix prüfen. Eine Cloud-Annahme ist noch keine Bestätigung des Gerätezustands. Änderungen werden anhand eines gelesenen Status übernommen.

## Skriptfunktionen

Alle Funktionen erwarten als ersten Parameter die ID der eigenen Dreo-Instanz. Es gibt keine fest vorgegebene Objekt-ID.

| Funktion | Zweck |
| --- | --- |
| DREO_DiscoverDevices(int InstanceID): bool | Gerätesuche und Aktualisierung der Auswahl |
| DREO_Refresh(int InstanceID): bool | Status abfragen |
| DREO_SetPower(int InstanceID, bool Value): void | Ein/Aus |
| DREO_SetMode(int InstanceID, int Value): void | Betriebsart 0, 1 oder 2 |
| DREO_SetHeatLevel(int InstanceID, int Value): void | Heizstufe 1 bis 3 |
| DREO_SetTargetTemperature(int InstanceID, float Value): void | Ganze °C von 5 bis 35 |
| DREO_ResetSession(int InstanceID): void | Token und wartende Befehle zurücksetzen |
| DREO_TestConnection(int InstanceID): string | Anonymen Hintergrundtest starten; sofortiger Startstatus |
| DREO_GetDiagnostics(int InstanceID): string | Bereinigte Diagnose als JSON |
| DREO_GetFormFeedback(int InstanceID): string | Letzte Formularrückmeldung lesen |

Die Variablen bieten außerdem die üblichen Symcon-Aktionen. Mehrfachklicks werden pro Einstellung zusammengefasst. Eine Skriptfunktion kann zurückkehren, während ihr Auftrag noch wartet; LastCommand und der gemeldete Status bleiben maßgeblich. Wartende Aufträge verfallen nach 30 Sekunden. Konfigurationsänderungen, Sitzungs-Reset und Fehler verwerfen sie. Unklar ausgeführte Befehle werden nicht automatisch wiederholt.

## Diagnose und bekannte Grenzen

Im Formular stehen **Diagnose anzeigen**, **Verbindung prüfen**, **Status aktualisieren** und **Sitzung zurücksetzen** zur Verfügung. Der anonyme Verbindungstest prüft keine Zugangsdaten. Fehlercode 100022 bedeutet abgelehnte Anmeldung; E-Mail und Passwort lokal neu prüfen. Diagnose enthält weder Passwort, Zugriffstoken noch Seriennummer.

Die grundlegende App-API-Anbindung ist am EU-Gerät bestätigt. Separate reale Bestätigungen für Betriebsarten, Heizstufen und einzelne Solltemperaturen stehen noch aus. Weitere Grenzen und Prüfungen stehen in [docs/TESTRESULTS.md](../docs/TESTRESULTS.md).
