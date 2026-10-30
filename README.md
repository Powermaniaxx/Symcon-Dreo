# Dreo 720S

Version **1.0.0** verbindet den **Dreo 720S (DR-HSH040S)** direkt mit **Symcon ab Version 9.0**. Benötigt werden ein eigenes Dreo-Konto, ein bereits in der Dreo-App eingerichteter Heizer und Internetzugang. Home Assistant, MQTT und zusätzliche Dienste sind nicht erforderlich.

Dies ist ein unabhängiges Community-Modul. Es stammt nicht von Dreo. Der reguläre Zugang verwendet die Dreo-App-Cloud; eine lokale LAN-Steuerung ist nicht enthalten.

## Funktionen

| Funktion | Verhalten |
| --- | --- |
| Ein/Aus | Eigener Schalter, unabhängig von Betriebsart und Heizstufe |
| Betriebsart | Nur Lüften, Heizen und ECO |
| Heizstufe | H1, H2 und H3 |
| Solltemperatur | Ganze °C von 5 bis 35; wirksam im ECO-Modus |
| Raumtemperatur | Anzeige in °C mit einer Nachkommastelle |
| Heizstatus | Vom Gerät gemeldeter Zustand des Heizelements; nur lesend |
| Verfügbarkeit | Online, offline oder unbekannt entsprechend der Cloud-Antwort |
| Aktualisierung | Alle 30 Sekunden; einstellbar von 30 bis 3600 Sekunden |
| Mehrfachklicks | Gleiche laufende Befehle werden zusammengefasst; pro Einstellung gilt der zuletzt vorgemerkte Wunsch |
| Rückmeldung | Cloud-Annahme und Bestätigung durch gelesenen Gerätestatus werden getrennt angezeigt |

Unterstützt wird ausschließlich **DR-HSH040S**. Andere Modelle werden ausgefiltert. Pro Instanz wird ein Heizer verwaltet. EU-Anmeldung, Erkennung, Statuslesen und grundlegende Ein/Aus-Steuerung sind am echten Gerät bestätigt. Nordamerika ist implementiert, aber noch nicht am Gerät geprüft.

## Installation und Einrichtung

Der Eintrag im Symcon Module Store wird vorbereitet. Eine Verfügbarkeit im Store besteht erst nach Einreichung und Freigabe durch Symcon. Bis dahin kann das Modul über Module Control installiert werden.

1. In **Module Control** das Repository `https://github.com/Powermaniaxx/Symcon-Dreo.git` mit Branch `main` hinzufügen.
2. Über **Instanz hinzufügen → Dreo → Dreo 720S** eine Instanz erstellen.
3. E-Mail, Passwort und Kontoregion des eigenen Dreo-Kontos eintragen. **App-API (720S, Standard)** beibehalten und Änderungen übernehmen.
4. **Geräte suchen** anklicken. Den gefundenen Heizer auswählen, **Aktiv** einschalten und Änderungen übernehmen.
5. Die erste erfolgreiche Statusabfrage abwarten. **Status gültig** muss wahr sein, bevor Werte für Automationen verwendet werden.

Bei einem Update von einer bisherigen GitHub-Version bleiben die vorhandene Instanz, ihre Einstellungen und die Variablen erhalten. Bibliotheks- und Modul-GUID werden nicht geändert. Für den Wechsel von Module Control zu einer späteren Store-Installation vorher eine Symcon-Sicherung erstellen; das konkrete Verfahren ist vor dem Entfernen einer Installation zu prüfen.

Das [Modulhandbuch](DreoHeater/README.md) beschreibt alle Einstellungen, Variablen und Skriptfunktionen.

## Bedienung

Geschaltet wird über die Statusvariablen unterhalb der Instanz. Ein ausgeschalteter Heizer wird durch eine Änderung von Modus, Heizstufe oder Solltemperatur im Modul nicht zusätzlich eingeschaltet. Der eigene Ein/Aus-Schalter ist dafür zuständig. Beim Erstellen, Neustart oder Wiederverbinden werden keine automatischen Einschaltbefehle angelegt.

Der erste freie Schaltauftrag wird direkt verarbeitet. Nach Cloud-Annahme wird die Statusbestätigung separat gelesen, damit der Klick nicht noch auf eine zusätzliche HTTP-Abfrage warten muss. Ein Variablenwert wird erst aus einem gemeldeten Gerätestatus aktualisiert. **Letzter Schaltauftrag** zeigt den Fortschritt.

Wenn bereits eine Übertragung läuft, wird pro Einstellung nur der letzte weitere Wunsch vorgemerkt. Diese Aufträge gelten höchstens 30 Sekunden. Konfigurationsänderungen, Sitzungs-Reset und Abfrage- oder Übertragungsfehler verwerfen wartende Aufträge. Ein möglicherweise schon ausgeführter Befehl wird nicht automatisch wiederholt.

Ein interner DNS-Cache und die Hintergrundauflösung der Dreo-Server verkürzen wiederholte Namensauflösungen. Einträge gelten höchstens 120 Sekunden. TLS-Zertifikats- und Hostnamenprüfung bleiben aktiv. Bei kaltem Cache oder Problemen im Internet beziehungsweise in der Cloud sind weiterhin Wartezeiten möglich.

## Status und Temperatur

**Status gültig** bedeutet, dass die letzte vollständige Cloud-Abfrage erfolgreich ausgewertet wurde. Bei Fehlern bleiben alte Messwerte sichtbar, sind jedoch nicht mehr als aktuell gültig gekennzeichnet. Für Automationen zusätzlich das Alter von **Letzte erfolgreiche Abfrage (Unixzeit)** prüfen; ein gestoppter Kernel kann gespeicherte Werte nicht selbständig ungültig machen.

**Online ausdrücklich bestätigt** ist nur wahr, wenn die Cloud dies positiv meldet. Ein erfolgreicher HTTP-Abruf allein beweist nicht, dass der physische Heizer erreichbar ist. Bei fehlendem Online-Merkmal steht der Verbindungsstatus auf unbekannt. **Heizstatus geliefert** kennzeichnet, ob der Heizstatus tatsächlich in der Antwort vorhanden war.

Beim App-API-Zugang des 720S werden Fahrenheit-Werte nach Celsius umgerechnet. Raumtemperatur 70 °F wird als 21,1 °C angezeigt. Ein eingestellter Sollwert von 21 °C wird entsprechend der App als 70 °F übertragen und wieder als 21 °C angezeigt. Der bereits in der Gerätemeldung berücksichtigte Temperatur-Offset wird nicht erneut angewendet.

## Fehlerdiagnose

**Diagnose anzeigen** liefert Version, verwendeten Cloud-Zugang, Fehlercodes, Abfragezeiten und anonymisierte Geräteanzahlen. Kontodaten, Passwort, Token und Geräte-Seriennummer werden nicht ausgegeben. Unter `commandTiming` stehen Verbindungszeit, Zeit bis zum Schreiben des letzten WebSocket-Befehls und Gesamtdauer in Millisekunden.

**Verbindung prüfen** vergleicht die automatische Verbindung mit IPv4, ohne Zugangsdaten zu senden. Dieser Test prüft keine Anmeldung. Auch eine HTTP-Antwort mit einem API-Fehler kann die Erreichbarkeit bestätigen. Für eine Gerätesuche müssen die Zugangsdaten vorher mit **Änderungen übernehmen** gespeichert sein.

Bei Code **100022** hat Dreo die Anmeldung abgelehnt; gespeicherte E-Mail und Passwort lokal erneut prüfen. Automatische Anmeldeversuche werden bei diesem Code gestoppt. Andere Fehler führen zu einer längeren Pause zwischen automatischen Abfragen.

Die zusätzliche OpenAPI ist experimentell und für den 720S nicht als funktionierender Zugang bestätigt. Im bisherigen Nutzertest blieb sie bereits im Login bei HTTP 302 stehen. Für den normalen Betrieb **App-API** verwenden.

## Zugangsdaten und Grenzen

Das Passwortfeld ist maskiert, der gespeicherte Wert jedoch eine normale Symcon-Eigenschaft. Symcon-Administratoren und Sicherungen können diese Daten enthalten. Zugriffstoken und der interne Adresscache liegen in Instanzpuffern. Das Modul übermittelt Kontodaten an Dreo zur Anmeldung; es sendet keine Nutzerdaten an den Modulherausgeber.

Die Cloud-API kann sich unabhängig vom Modul ändern. Firmware, geteilte Konten und mehrere gleichzeitige Instanzen sind noch nicht vollständig praktisch geprüft. Der Nutzer hat die grundlegende Steuerung und die Reaktion nach beta.9 bestätigt. Für Betriebsarten, Heizstufen und den Abgleich einzelner Solltemperaturen liegt bisher keine separate reale Bestätigung vor. Diese Grenzen stehen im [Prüfprotokoll](docs/TESTRESULTS.md).

## Entwicklung und Lizenz

[GitHub Actions](https://github.com/Powermaniaxx/Symcon-Dreo/actions) prüft die eigenständigen Testsuiten unter PHP 8.2 bis 8.5 sowie die Bibliotheks- und Modulstruktur mit dem offiziellen Symcon-Validator. Die Tests verwenden synthetische API-Daten und lokale HTTP-, WebSocket- und TLS-Gegenstellen. Sie ersetzen keinen Hardwaretest.

Änderungen: [CHANGELOG.md](CHANGELOG.md). Fehlerberichte: [CONTRIBUTING.md](CONTRIBUTING.md). Sicherheit: [SECURITY.md](SECURITY.md). Herkunft der Protokolle und Lizenzhinweise: [docs/PROVENANCE.md](docs/PROVENANCE.md).

MIT-Lizenz. Bei Weitergabe auch die beiliegenden Lizenztexte von hass-dreo und pydreo-client beibehalten. Informationen zur vorbereiteten Store-Einreichung: [docs/STORE_SUBMISSION.md](docs/STORE_SUBMISSION.md).
