# Dreo Modul – Modulhandbuch

Version 1.1.0 verbindet die Geräte aus der [Modellliste](../docs/SUPPORTED_MODELS.md) über das eigene Dreo-Konto mit Symcon ab Version 9.0. Standard ist **App-API**. Pro Gerät eine eigene Instanz anlegen. Keine lokale LAN-Steuerung. Reale Bestätigung liegt bisher nur für die grundlegende App-API-Anbindung des EU-720S vor; zusätzliche Profile sind implementiert und automatisch geprüft, aber noch nicht am echten Gerät bestätigt.

## Einrichtung

E-Mail, echtes Kontopasswort und Region eintragen, speichern und **Geräte suchen** anklicken. Danach ein gefundenes Gerät auswählen, Aktiv einschalten und Änderungen übernehmen. Nach der ersten gültigen Statusabfrage zeigt das Modul die verfügbaren Funktionen an. OpenAPI bleibt experimentell und auf den 720S beschränkt.

| Einstellung | Bedeutung |
| --- | --- |
| Aktiv | Automatische Abfragen und Schalten erlauben |
| Dreo-Konto / Passwort | Eigene App-Anmeldung; kein Passwort-Hash oder Token |
| Cloud-Zugang | App-API als Standard |
| Kontoregion | EU oder Nordamerika; bisher real geprüft: EU |
| Gerät | Aus der Gerätesuche wählen |
| Statusabfrage | 30–3600 Sekunden, Standard 30 |

## Variablen

Optionale Variablen werden anhand des Profils und der gültigen Antwort ein- oder ausgeblendet. Vorhandene Variablen werden nicht gelöscht, damit IDs und Verknüpfungen erhalten bleiben. Ausgeblendete Variablen können alte Werte enthalten; sie sind keine aktuell verfügbaren Funktionen. Die Diagnose nennt unter `availableControls` die momentan angebotenen Einstellungen.

| Ident | Bedeutung |
| --- | --- |
| Power | Ein/Aus, bool |
| Mode | Betriebsart; Zuordnung siehe unten |
| HeatLevel | Heizstufe H1–H3, nur Heizer |
| TargetTemperature | Ganze °C, 5–35; DR-HSH017BS höchstens 29; wirksam in ECO |
| CurrentTemperature | Gemeldete Raumtemperatur in °C, nur enthaltene Heizerprofile |
| Heating / HeatingKnown | Gemeldeter Heizzustand / Heizstatus tatsächlich vorhanden |
| FanSpeed | Native Lüfterstufe, Modellgrenze beachten |
| Oscillation | Horizontales Schwenken, wenn entsprechender boolescher Status vorhanden |
| CurrentHumidity | Gemeldete Luftfeuchtigkeit in Prozent |
| TargetHumidity | Soll-Feuchtigkeit, getrennt für Auto und Schlafen; 30–90 %, gegebenenfalls enger |
| MistLevel | Unveränderter Gerätewert `foglevel` von 0–6; nicht alle Werte bedeuten verschiedene physische Stufen |
| Online / Availability | Ausdrücklich online gemeldet / online, offline oder unbekannt |
| StateValid | Letzte Statusabfrage anhand des Modellprofils gültig |
| LastUpdate / LastUpdateUnix | Letzte erfolgreiche Abfrage als UTC-Text / Unixzeit |
| LastCommand / LastError | Schaltfortschritt / letzter Fehler |
| ConnectionTestStatus | Fortschritt des anonymen Erreichbarkeitstests |

Mode-Zuordnung ist je Geräteart unterschiedlich. Nur im Modellprofil enthaltene Betriebsarten werden angeboten.

| Geräteart | Werte von Mode |
| --- | --- |
| Heizer | 0 Lüften, 1 Heizen, 2 ECO |
| Ventilator | 0 Normal, 1 Natur, 2 Schlafen, 3 Auto, gegebenenfalls 4 Turbo und 5 Benutzerdefiniert |
| Luftreiniger | 0 Auto, 1 Manuell, 2 Schlafen, 3 Turbo |
| Luftbefeuchter mit bekannter Moduszuordnung | 0 Manuell, 1 Auto, 2 Schlafen |

## Skriptfunktionen

Alle Funktionen erwarten als ersten Parameter die ID der eigenen Dreo-Instanz. Funktionen für einen anderen Gerätetyp oder ohne gültigen gemeldeten Wert werden abgelehnt. Die neue Gerätevielfalt ändert die Bedeutung bestehender 720S-Aktionen nicht.

| Funktion | Zweck |
| --- | --- |
| DREO_DiscoverDevices(int InstanceID): bool | Gerätesuche |
| DREO_Refresh(int InstanceID): bool | Status abfragen |
| DREO_SetPower(int InstanceID, bool Value): void | Ein/Aus |
| DREO_SetMode(int InstanceID, int Value): void | Modellabhängige Betriebsart laut Tabelle |
| DREO_SetHeatLevel(int InstanceID, int Value): void | Heizstufe 1–3 |
| DREO_SetTargetTemperature(int InstanceID, float Value): void | Ganze °C innerhalb der Modellgrenze |
| DREO_SetFanSpeed(int InstanceID, int Value): void | Native Lüfterstufe |
| DREO_SetOscillation(int InstanceID, bool Value): void | Horizontales Schwenken |
| DREO_SetTargetHumidity(int InstanceID, int Value): void | Feuchtigkeitsziel des aktuell gemeldeten Modus |
| DREO_SetMistLevel(int InstanceID, int Value): void | Rohwert der Nebelstufe 0–6 |
| DREO_ResetSession(int InstanceID): void | Token und wartende Aufträge zurücksetzen |
| DREO_TestConnection(int InstanceID): string | Anonymen Hintergrundtest starten |
| DREO_GetDiagnostics(int InstanceID): string | Bereinigte Diagnose als JSON |
| DREO_GetFormFeedback(int InstanceID): string | Letzte Formularrückmeldung |

Ein freier Auftrag wird direkt gesendet; Statusbestätigung läuft anschließend separat. Folgeklicks werden je Einstellung zusammengefasst. Die Funktionen können zurückkehren, während ein Auftrag wartet. Wartende Aufträge verfallen nach 30 Sekunden und werden bei Konfigurationswechsel, Sitzungs-Reset oder Fehler verworfen. Eine geänderte Zuordnung vor Versand, etwa Auto zu Schlafen beim Feuchtigkeitsziel, verwirft den alten Auftrag. Unklar ausgeführte Befehle werden nicht automatisch wiederholt.

Erst bei StateValid=true Messwerte als gültig verwenden und zusätzlich das Alter von LastUpdateUnix prüfen. Cloud-Annahme und gelesener Gerätezustand bleiben getrennt. Ein fehlender Online-Wert oder Heizstatus ist kein bestätigtes AUS.

## Diagnose und Grenzen

Diagnose enthält keine Zugangsdaten, Token oder Seriennummern. Der Verbindungstest prüft Erreichbarkeit, keine Anmeldung. Bei Code 100022 E-Mail und Passwort lokal neu prüfen. Das Passwort bleibt eine normale Symcon-Eigenschaft; Sicherungen und Administratoren können darauf zugreifen.

Spezialfunktionen wie RGB, Filterreset, Timer, vertikale Winkel, Klimaanlagen, Deckenventilatoren und Fan/Heater-Kombigeräte sind nicht Bestandteil dieser Version. Die App allein belegt keine Kompatibilität. Weitere Informationen: [Hauptdokumentation](../README.md), [Modellliste](../docs/SUPPORTED_MODELS.md), [Prüfprotokoll](../docs/TESTRESULTS.md).
