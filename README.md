# Dreo Modul für Symcon

Version **1.1.0** verbindet unterstützte Dreo-Geräte direkt über die Dreo-Cloud mit **Symcon ab Version 9.0**. Enthalten sind **39 konkrete Modellprofile** für Heizgeräte, Ventilatoren, Luftreiniger und Luftbefeuchter. Benötigt werden ein eigenes Dreo-Konto, ein bereits in der Dreo-App eingerichtetes Gerät und Internetzugang. Home Assistant, MQTT und weitere Dienste sind nicht erforderlich.

**Die Nutzung in der Dreo-App allein garantiert keine Unterstützung.** Maßgeblich ist die [Modellliste](docs/SUPPORTED_MODELS.md). Bisher sind EU-Anmeldung, Erkennung, Statuslesen und grundlegendes Ein/Aus am echten **720S / DR-HSH040S** bestätigt. Die zusätzlichen Profile sind anhand der Protokollreferenz implementiert und mit synthetischen Daten geprüft; reale Tests dieser Modelle stehen aus. Dies ist ein unabhängiges Community-Modul und kein offizielles Dreo-Produkt.

## Funktionen

| Geräteart | Modellabhängige Funktionen |
| --- | --- |
| Heizgeräte | Ein/Aus, Lüften/Heizen/ECO, H1–H3, Soll- und Raumtemperatur in Celsius, Heizstatus |
| Ventilatoren | Ein/Aus, native Lüfterstufen, Betriebsart, horizontales Schwenken |
| Luftreiniger | Ein/Aus, Lüfterstufen, Auto/Manuell/Schlafen/Turbo |
| Luftbefeuchter | Ein/Aus, Luftfeuchtigkeit, Soll-Feuchtigkeit, Nebelstufe, bekannte Betriebsarten |

Eine Funktion erscheint nur, wenn das Profil sie kennt und das Gerät dafür einen gültigen Status liefert. Fehlende oder unbekannte optionale Werte werden ausgeblendet und sind nicht schaltbar. Ein gültiger Ein/Aus-Status ist erforderlich. Es werden keine Heizbefehle an Ventilatoren oder Luftbefeuchter gesendet. Spezialfunktionen wie RGB, Filterreset, vertikale Winkel, Timer, Klimageräte und Fan/Heater-Kombigeräte sind nicht enthalten.

## Installation und Einrichtung

Der Eintrag im Symcon Module Store wird vorbereitet. Eine Store-Verfügbarkeit besteht erst nach Einreichung und Freigabe durch Symcon. Bis dahin über Module Control installieren.

1. Repository `https://github.com/Powermaniaxx/Symcon-Dreo.git`, Branch `main`, in **Module Control** hinzufügen beziehungsweise aktualisieren.
2. Über **Instanz hinzufügen → Dreo → Dreo Gerät (Cloud)** eine Instanz erstellen. Pro Gerät wird eine eigene Instanz verwendet.
3. E-Mail, Passwort und Kontoregion eintragen. **App-API (Standard)** beibehalten und Änderungen übernehmen.
4. **Geräte suchen**, das gewünschte Gerät auswählen, **Aktiv** einschalten und Änderungen übernehmen.
5. Die erste erfolgreiche Statusabfrage abwarten. Erst dann erscheinen die verfügbaren Funktionen und werden schaltbar.

Vorhandene 720S-Instanzen, Eigenschaften und Variablen bleiben erhalten; Bibliotheks- und Modul-GUID sowie interne Modulnamen werden nicht geändert. Für einen Wechsel von Module Control zu einer späteren Store-Installation das konkrete Verfahren vor dem Entfernen einer Installation prüfen und eine Symcon-Sicherung anlegen.

Das [Modulhandbuch](DreoHeater/README.md) beschreibt Einstellungen, Variablen und Skriptfunktionen.

## Schalten und Bestätigung

Der erste freie Schaltauftrag wird direkt verarbeitet. Nach Cloud-Annahme wird die Statusbestätigung separat gelesen. Die angezeigten Werte ändern sich erst durch einen gemeldeten Gerätestatus. **Letzter Schaltauftrag** unterscheidet Versand, Cloud-Annahme und Gerätebestätigung.

Läuft bereits eine Übertragung, bleibt je Einstellung nur der letzte weitere Wunsch vorgemerkt. Aufträge verfallen nach 30 Sekunden. Konfigurationsänderungen, Sitzungs-Reset und Abfrage- oder Übertragungsfehler verwerfen wartende Aufträge. Modell und verfügbare Funktion werden vor dem Versand nochmals geprüft. Wechselt etwa der Luftbefeuchter von Auto zu Schlafen, wird ein vorgemerkter Auto-Feuchtigkeitswert nicht an die andere Funktion geschickt. Möglicherweise bereits ausgeführte Befehle werden nicht automatisch wiederholt.

Das Modul sendet bei Modus-, Heizstufen-, Feuchtigkeits- und Sollwertänderungen keinen zusätzlichen Einschaltbefehl. Erstellen, Neustarten und Wiederverbinden lösen ebenfalls keinen Einschaltbefehl aus. Das physische Verhalten eines Geräts nach einer einzelnen Einstellung hängt von dessen Firmware ab und ist für die neuen Profile noch nicht real geprüft.

Ein interner DNS-Cache und die Hintergrundauflösung der Server verkürzen wiederholte Namensauflösungen. Einträge gelten höchstens 120 Sekunden. TLS-Zertifikats- und Hostnamenprüfung bleiben aktiv. Ein kalter Cache und Cloud- oder Internetprobleme können weiterhin Wartezeiten verursachen.

## Status, Temperaturen und Grenzen

**Status gültig** bedeutet, dass die letzte Cloud-Abfrage anhand des ausgewählten Profils ausgewertet werden konnte. Bei Fehlern bleiben bisherige Messwerte gespeichert, sind aber nicht aktuell gültig. Für Automationen auch das Alter von **Letzte erfolgreiche Abfrage (Unixzeit)** prüfen. Ausgeblendete Variablen können alte Werte enthalten und dürfen nicht als unterstützte Funktion interpretiert werden.

**Online ausdrücklich bestätigt** ist nur wahr, wenn die Cloud dies positiv meldet. Ein erfolgreicher HTTP-Abruf beweist nicht, dass das physische Gerät erreichbar ist. Fehlende optionale Werte sind kein Beleg für den ausgeschalteten Zustand der betreffenden Funktion.

Bei den enthaltenen App-API-Heizerprofilen werden Fahrenheit-Werte in Celsius umgerechnet. 70 °F Raumtemperatur werden als 21,1 °C angezeigt. Ein Sollwert von 21 °C wird entsprechend der App als 70 °F übertragen und als 21 °C zurückgelesen. Beim 720S wird der bereits eingerechnete Temperatur-Offset nicht nochmals addiert. DR-HSH017BS ist auf ganze 5–29 °C beschränkt. Andere enthaltene Heizprofile erlauben 5–35 °C.

Beim Nomad One S / DR-HTF007S unterstützen ältere Hardwareversionen nur vier Stufen. Ohne eindeutige Gerätemetadaten bleiben vier Stufen verfügbar; acht werden nur mit passender gemeldeter Konfiguration und ohne bekannte alte MCU freigegeben. Bei DR-HAP010S wird die Lüfterstufe nur bei ausdrücklich aktiv gemeldeter Reinigung angeboten; das Modul aktiviert die Reinigung nicht automatisch. Bei Luftbefeuchtern werden Auto- und Schlaf-Feuchtigkeitsziele getrennt behandelt. Nebelstufe ist der unveränderte Gerätewert 0–6, keine Zusage von sieben physisch verschiedenen Stufen.

## Diagnose und Zugangsdaten

**Diagnose anzeigen** enthält Version, erkanntes Modell, Geräteart, verfügbare Funktionen, anonymisierte Geräteanzahlen und Abfragezeiten. Kontodaten, Passwort, Token und Seriennummer werden nicht ausgegeben. `commandTiming` zeigt Verbindungszeit, Zeitpunkt des geschriebenen Befehls und Gesamtdauer in Millisekunden.

**Verbindung prüfen** vergleicht automatische Verbindung und IPv4 ohne Zugangsdaten. Es ist kein Anmeldetest. Code **100022** bedeutet abgelehnte Anmeldung; gespeicherte E-Mail und Passwort lokal erneut prüfen. Automatische Anmeldeversuche stoppen bei diesem Code. Andere Fehler verlängern den Abfrageabstand.

Das Passwort ist eine normale Symcon-Eigenschaft mit maskiertem Eingabefeld. Administratoren und Sicherungen können diese Daten enthalten. Token und DNS-Cache liegen in Instanzpuffern. Kontodaten werden zur Anmeldung an Dreo übermittelt; es gibt keine Übertragung an den Modulherausgeber.

Die zusätzliche **OpenAPI ist experimentell und nur für den 720S implementiert**. Im bisherigen Nutzertest scheiterte bereits der Login mit HTTP 302. Für alle regulären Modellprofile **App-API** verwenden. Keine lokale LAN-Steuerung. Cloud, Firmware und Kontoberechtigungen können die Verwendbarkeit verändern. Nordamerika, geteilte Konten und der Parallelbetrieb vieler Instanzen sind noch nicht praktisch bestätigt.

## Entwicklung und Lizenz

[GitHub Actions](https://github.com/Powermaniaxx/Symcon-Dreo/actions) prüft die Tests unter PHP 8.2 bis 8.5 sowie Bibliothek und Modul mit dem offiziellen Symcon-Validator. Die Tests verwenden synthetische API-Daten und lokale HTTP-, WebSocket- und TLS-Gegenstellen. Sie ersetzen keine Hardwaretests. Details stehen in [TESTRESULTS.md](docs/TESTRESULTS.md), Quellen in [PROVENANCE.md](docs/PROVENANCE.md).

MIT-Lizenz, siehe [LICENSE](LICENSE). Die Referenzprojekte und ihre Lizenzhinweise sind im Verzeichnis `docs` dokumentiert.
