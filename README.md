# Symcon Dreo

Native Anbindung des **Dreo 720S (DR-HSH040S)** an **IP-Symcon 9.0 oder neuer**. Symcon spricht die Dreo-Cloud direkt an. Home Assistant, HACS, MQTT und zusätzliche Container sind dafür nicht erforderlich.

**Automatisierte Tests:** [Aktueller GitHub-Actions-Status](https://github.com/Powermaniaxx/Symcon-Dreo/actions). Reale Gerätetests sind davon getrennt.

**Stand: 0.1.0-beta.8. Vorabversion für kontrollierte Tests.** Der Quellcode wurde mit synthetischen API-Antworten, einem SDK-Ersatz und lokalen HTTP-/WebSocket-Gegenstellen getestet. Die neuen OpenAPI-Anfragen werden außerdem mit aufgezeichneten Anfragen des originalen Hersteller-Clients verglichen. Am 30.10.2026 wurden mit beta.6 am echten EU-Konto und DR-HSH040S Anmeldung, Gerätesuche und Statuslesen über die App-API in Symcon bestätigt. Die grundlegende Ein/Aus-Steuerung wurde mit beta.6/beta.7 am Gerät bestätigt; ein Screenshot zeigt die Bestätigung durch Gerätestatus. Die Doppelklick-Korrektur aus beta.8, Betriebsarten und Temperaturzuordnung sind noch praktisch zu prüfen. Diese Version ist deshalb keine freigegebene stabile oder offiziell von Dreo unterstützte Integration.

## Funktionen

| Funktion | Verhalten |
| --- | --- |
| Ein/Aus | OpenAPI: `power_switch`; App-API: `poweron` |
| Betriebsart | Lüften, Heizen, ECO; bei OpenAPI nur mit eindeutiger Modellkonfiguration |
| Heizstufe | H1 bis H3; wirkt bei Heizen |
| Solltemperatur | Ganze °C, höchstens 5 bis 35 °C; zusätzlich begrenzt durch den bestätigten Modellbereich |
| Raumtemperatur | Anzeige °C; OpenAPI-Einheit aus Modellkonfiguration, App-API beim 720S Fahrenheit |
| Heizelement | `ptcon` nur lesend, soweit geliefert. `HeatingKnown` kennzeichnet einen vorhandenen Heizstatus |
| Status | Standardmäßig alle 30 Sekunden; einstellbar von 30 bis 3600 Sekunden |
| Geräteauswahl | EU-/NA-Anmeldung, Gerätesuche, Auswahl über Seriennummer |
| Rückmeldung | Cloud-Annahme und tatsächliche Statusbestätigung werden getrennt behandelt |

Modus, Heizstufe und Solltemperatur schalten ein ausgeschaltetes Gerät **nicht automatisch ein**. Zum Einschalten ist ein eigener Ein/Aus-Befehl nötig. Das Modul legt keinen Heizplan an und sendet beim Start, bei der Einrichtung oder nach Verbindungsfehlern keine automatischen Einschaltbefehle.

## Unterstützte Geräte

Version 0.1 unterstützt ausschließlich **DR-HSH040S / 720S**. Andere Dreo-Modelle werden bei der Auswahl ausgefiltert und können nicht über dieses Modul gesteuert werden. Zusätzliche Geräteklassen benötigen geprüfte eigene Zustands- und Befehlszuordnungen; die Modularchitektur trennt dafür Protokoll, Transport und Symcon-Oberfläche.

Ein Gerät muss bereits mit dem eigenen Dreo-Konto in der Hersteller-App eingerichtet sein. Die Anmeldung setzt ein E-Mail-/Passwort-Konto voraus. Europa und Nordamerika sind implementiert; andere Kontoregionen sind nicht unterstützt.

## Installation

Öffentliche Beta: https://github.com/Powermaniaxx/Symcon-Dreo

1. In Symcon **Module Control** öffnen und `https://github.com/Powermaniaxx/Symcon-Dreo.git` hinzufügen. Branch: `main`.
2. Über **Instanz hinzufügen → Dreo → Dreo 720S** eine Instanz erstellen.
3. Eigene E-Mail, eigenes Dreo-Passwort und Kontoregion eintragen. Cloud-Zugang **App-API (720S, Standard)** beibehalten und Änderungen übernehmen. Die zunächst deaktivierte Instanz kann schon nach Geräten suchen.
4. **Geräte suchen** anklicken, den eigenen 720S auswählen, **Aktiv** einschalten und Änderungen übernehmen.
5. Erst wenn **Status gültig** wahr ist, die Statuswerte verwenden und Schaltbefehle testen.

Seit beta.7 ist App-API der Standard für neue Instanzen. Bereits gespeicherte Einstellungen bleiben erhalten. Bei einer bestehenden Instanz mit OpenAPI für den 720S auf **App-API (720S, Standard)** umstellen und Änderungen übernehmen. Eine bereits mit App-API arbeitende Instanz braucht für den nächsten Gerätetest kein Update. Zugangstoken werden zwischen diesen Verfahren nicht wiederverwendet.

Die zuvor in beta.6 voreingestellte OpenAPI scheiterte beim Nutzer bereits im Login mit HTTP 302; Gerätezugriffe wurden darüber nicht erreicht. Ein übereinstimmendes Anfragenformat zum Hersteller-SDK beweist keine erfolgreiche Cloud-Anmeldung. Zusätzlich existiert im Herstellerprojekt ein offener [720S-Erkennungsbericht](https://github.com/dreo-team/hass-dreoverse/issues/168); dieser Bericht allein beweist nicht die Ursache des hier beobachteten HTTP 302.

OpenAPI startet die Anmeldung wie der Hersteller-Client am US-Endpunkt. Die tatsächliche Serverregion für Gerätezugriffe wird anschließend aus dem Token übernommen. Dafür ist kein manuelles Umschalten eines EU-Kontos auf Nordamerika nötig. Ein gefundenes Gerät bestätigt noch keine erfolgreiche Statusauswertung. Fehlt die Temperatureinheit in `config.heater_entity_config`, wird die Statusauswertung mit einer konkreten Fehlermeldung beendet. Bei fehlender oder mehrdeutiger Zuordnung der drei Modi wird deren Steuerung gesperrt. Die realen Metadaten des 720S müssen noch im Nutzertest bestätigt werden.

Wenn Schaltflächen scheinbar nichts auslösen: Das Formular schließen, in Module Control aktualisieren und das Formular erneut öffnen. Oben muss **0.1.0-beta.8** stehen. **Oberfläche testen** muss sofort einen Dialog mit Versionsnummer und Instanz-ID öffnen; dieser Knopf benötigt weder Modulaufruf noch Netzwerk. Bleibt auch dieser Dialog aus, liegt noch kein Nachweis vor, dass die Konsole das onClick-Skript ausführt. Die übrigen Knöpfe führen intern eine Aktion ohne Textausgabe aus; der Formularaufrufer zeigt die gespeicherte Rückmeldung oder eine Fehlermeldung im Dialog an. Diagnose wird direkt als Rückgabewert gelesen. Suche und manuelle Statusabfrage starten über einen Timer im Hintergrund. Nach Schließen des Dialogs steht das Ergebnis im Formular; notfalls Formular neu öffnen. Diagnose enthält den zuletzt empfangenen Formularauftrag mit Zeitstempel. Ein Klick auf **Änderungen übernehmen** verwirft noch wartende Formularaufträge.

Bei einer Meldung wie **cURL 28** zuerst **Verbindung prüfen** anklicken. Sofort erscheint „Verbindungstest gestartet“. Die zwei Abfragen laufen anschließend einzeln im Hintergrund; nach etwa 20 Sekunden stehen das Ergebnis im Formular und die Details unter **Diagnose anzeigen**. Der aktuelle Stand bleibt zusätzlich in der Statusvariable **Verbindungstest** sichtbar. Der Test sendet keine Zugangsdaten und verändert keine Gerätezustände. Er vergleicht die automatische Verbindung mit IPv4. Auch HTTP 401, 404 oder 405 kann dabei eine erreichbare Gegenstelle belegen; der Test prüft ausdrücklich keine erfolgreiche Anmeldung. Die Ausgabe enthält Laufzeiten und Fehlercodes, keine URLs mit Token oder IP-Adressen. Sie wird auch von **Diagnose anzeigen** erfasst. `httpResponseReceived=true` bestätigt lediglich eine HTTP-Antwort. `loginTested=false` stellt klar, dass dieser Test keine Anmeldung ausführt. Auch HTTP 200 mit API-Code 100000 aus der absichtlich ohne Zugangsdaten gestellten Anfrage ist kein Nachweis eines falschen Passworts.

Die Rückmeldung der Suche steht direkt bei der Geräteauswahl. Falls die Liste leer bleibt, **Diagnose anzeigen** anklicken: HTTP-/API-Codes und anonymisierte Anzahlen unterscheiden Verbindungsfehler, leere Antworten und nicht verwendbare Geräteeinträge. Zugangsdaten, Token und Seriennummern werden dabei nicht ausgegeben. Eine noch fehlende Auswahl wird als Wartezustand angezeigt und löst keine automatische Fehlerabfrageschleife aus.

Die Bibliothek enthält keine persönlichen Zugangsdaten, festen Objekt-IDs, Zima-Pfade oder Abhängigkeit von einer bestimmten Hausstatus-Visualisierung. Es wird noch kein Module-Store-Eintrag angeboten. Installation und Updates erfolgen zunächst über dieses Repository. `main` ist während dieser Testphase ausdrücklich der Beta-Stand.

Für eine lokale Entwicklerinstallation die Bibliothek gemäß der eigenen Symcon-Entwicklungsumgebung in deren Modulverzeichnis einbinden. Ein Installationsskript, das bestehende Container, Instanzen oder Dateien auf fremden Systemen verändert, ist nicht enthalten.

## Weitergabe und Entwicklung

`library.json` muss direkt im Repository-Stamm liegen. Die vorhandenen Bibliotheks- und Modul-GUIDs bei Updates oder der Veröffentlichung neuer Versionen beibehalten. Die MIT-Lizenz, `docs/LICENSE-hass-dreo.txt` und `docs/LICENSE-pydreo-client.txt` bei Weitergabe beilegen. Für eine stabile Veröffentlichung müssen die offenen Prüfungen in `docs/TESTPLAN.md` erfolgreich abgeschlossen und dokumentiert sein.

Pro Instanz wird ein Gerät verwaltet. Zugangsdaten und Sitzung sind pro Instanz getrennt. Mehrere gleichzeitige Instanzen und geteilte Dreo-Geräte sind noch nicht praktisch getestet.

## Statuswerte richtig verwenden

**Status gültig** bedeutet, dass der letzte vollständige Cloud-Status erfolgreich gelesen wurde. Bei Fehlern bleiben letzte Werte sichtbar, werden aber mit `StateValid=false` als ungültig gekennzeichnet. Anfangswerte vor der ersten erfolgreichen Abfrage sind keine Messwerte.

**Online ausdrücklich bestätigt** ist nur wahr, wenn die Cloud ein positives `connected`-Merkmal liefert. Fehlt dieses Merkmal, steht im **Verbindungsstatus** ausdrücklich „Unbekannt“. Ein erfolgreicher REST-Abruf allein beweist keine aktuelle Verbindung zum physischen Gerät. Bei ausdrücklich gemeldetem Offline-Status sind die Schaltaktionen gesperrt.

Bei unklarem Online-Status ist ein manueller Schaltversuch möglich, dessen Ergebnis wiederum bestätigt werden muss. Die Raumtemperatur wird nicht um die Kalibrierung doppelt korrigiert: Beim 720S enthält die gemeldete Temperatur den Offset bereits.

Bei nativen Fahrenheit-Werten wird der Sollwert wie in der App auf ganze Fahrenheit aufgerundet. Meldet die OpenAPI-Modellkonfiguration Celsius, wird der Sollwert in ganzen Celsius übertragen. Die Anzeige rechnet dies auf die entsprechende Celsius-Einstellung zurück; z. B. wird eine Einstellung von 21 °C als 70 °F übertragen und als 21 °C angezeigt. Eine gemessene Raumtemperatur von 70 °F wird dagegen als 21,1 °C angezeigt.

Das Modul bestätigt einen Schaltauftrag erst, wenn die abgefragten Gerätewerte zum Auftrag passen. Diese Rückmeldung stammt weiterhin aus der Cloud, nicht aus einer unabhängigen physikalischen Messung. Bleibt die Bestätigung 60 Sekunden lang aus, wird das angezeigt; das Modul wiederholt den Schaltbefehl nicht automatisch. Änderungen in der Dreo-App werden spätestens bei einer folgenden erfolgreichen Abfrage sichtbar.

Für Automationen `StateValid` und das Alter von `LastUpdateUnix` prüfen. Ein ausgeschalteter Kernel oder ein ausbleibender Timer kann einen alten Variablenwert nicht von selbst ungültig machen. Das Modul ist kein unabhängiger Temperaturbegrenzer.

## Öffentliche Skriptfunktionen

`InstanceID` bezeichnet jeweils die vom Nutzer angelegte Dreo-Instanz. Es gibt keine vorgegebene Objekt-ID.

| Funktion | Zweck |
| --- | --- |
| `DREO_DiscoverDevices(int InstanceID): bool` | Unterstützte Geräte suchen und Auswahl aktualisieren |
| `DREO_Refresh(int InstanceID): bool` | Status lesen; false bei Fehler oder bereits laufender Abfrage |
| `DREO_SetPower(int InstanceID, bool Value): void` | Ein-/Ausschalten |
| `DREO_SetMode(int InstanceID, int Value): void` | 0 Lüften, 1 Heizen, 2 ECO |
| `DREO_SetHeatLevel(int InstanceID, int Value): void` | 1 bis 3 |
| `DREO_SetTargetTemperature(int InstanceID, float Value): void` | Ganze Grad, 5 bis 35 °C |
| `DREO_ResetSession(int InstanceID): void` | Token verwerfen; bei aktiver Instanz neue Abfrage anstoßen |
| `DREO_TestConnection(int InstanceID): string` | Startet einen anonymen Hintergrundtest, liefert sofort den Startstatus; Ergebnisse anschließend über GetDiagnostics |
| `DREO_GetFormFeedback(int InstanceID): string` | Letzte Formularrückmeldung ohne Netzwerkaufruf lesen |
| `DREO_GetDiagnostics(int InstanceID): string` | Diagnose-JSON ohne Konto, Passwort, Token oder Seriennummer |

Schnelle Folgeklicks werden pro Einstellung zusammengefasst: Ein identischer bereits laufender Befehl wird nicht nochmals gesendet; bei einer wartenden Einstellung gilt der letzte angeforderte Wert. Unterschiedliche Einstellungen werden nacheinander verarbeitet. Eine laufende Statusabfrage löst dabei keinen Fehlerdialog aus. Der letzte gewünschte Wert ist noch kein bestätigter Gerätestatus; maßgeblich bleiben die gelesenen Variablen und **Letzter Schaltauftrag**.

Wartende Befehle gelten höchstens 30 Sekunden. Konfigurationsänderungen, Sitzungs-Reset und Abfrage-/Übertragungsfehler verwerfen sie. Ein möglicherweise schon ausgeführter Befehl wird nicht automatisch wiederholt. Skript-Schaltfunktionen können daher zurückkehren, während ihr Auftrag noch wartet. Diagnose nennt wartende Einstellungen unter `queuedControls`.

Schaltfunktionen werfen weiterhin bei ungültigen Werten, gesperrten oder unmittelbar fehlgeschlagenen Aufträgen eine Exception. Fehler aus einem späteren Timer-Aufruf stehen in **Letzter Fehler** und im ungültigen Status; sie werden dort nicht als unbehandelte Exception weitergereicht. Die normalen Symcon-Variablenaktionen sind ebenfalls vorhanden. GetDiagnostics liest nur lokale Diagnoseinformationen.

## Verbindung und Zugangsdaten

Im standardmäßigen App-API-Zugang laufen Anmeldung und Abfragen über HTTPS, Schaltbefehle über eine kurzlebige TLS-WebSocket-Verbindung. Die experimentelle OpenAPI verwendet ausschließlich HTTPS. TLS-Zertifikats- und Hostnamenprüfung bleiben eingeschaltet. Das Modul braucht die PHP-Erweiterungen cURL und OpenSSL sowie ausgehenden HTTPS-Zugriff auf die Dreo-Server der Kontoregion.

Das Passwortfeld ist in der Konsole maskiert. Es ist damit nicht automatisch verschlüsselt gespeichert: Es ist eine normale Symcon-Instanzeigenschaft. Symcon-Administratoren und Sicherungen können Zugangsdaten enthalten. Zugriffstoken liegen im Instanzpuffer; sie werden nicht als Statusvariable veröffentlicht. Eingaben, HTTP-Antworten und WebSocket-Adressen mit Token werden nicht protokolliert.

HTTP 401/403 führt zu höchstens einem erneuten Login pro Operation. Nach fehlgeschlagenen Abfragen steigt die Wartezeit bis auf mindestens fünf Minuten; ein größer gewähltes Abfrageintervall bleibt erhalten. Bei API-Code 100022 werden automatische Anmeldeversuche bis zur manuellen Korrektur oder zum Zurücksetzen gestoppt. Überlappende Operationen derselben Instanz werden gesperrt. Netzwerkoperationen einer Aktion haben zusammen ein Zeitbudget von 22 Sekunden. Ein Verbindungsaufbau erhält höchstens 8 Sekunden, eine einzelne HTTP-Abfrage höchstens 15 Sekunden, jeweils begrenzt durch das verbleibende Budget. Der anonyme Verbindungstest besteht aus zwei getrennten Timer-Schritten von jeweils höchstens 8 Sekunden. Wiederholte Klicks starten innerhalb einer Minute keinen zweiten laufenden Test. Übernehmen einer geänderten Konfiguration bricht wartende Tests ab.

Der Standardzugang verwendet die mobile App-API. Die experimentelle Alternative folgt der OpenAPI des Hersteller-Clients. Dreo kann diese Schnittstellen verändern. Eine rein lokale Steuerung ist nicht implementiert. Es werden keine Nutzerdaten an den Modulherausgeber gesendet.

## Tests

```sh
php tests/run.php
php tests/websocket-integration.php
php tests/http-integration.php
php tests/openapi.php
php tests/openapi-wire.php
```

Die erste Testsuite nutzt ausschließlich synthetische Antworten und einen SDK-Ersatz. Die zweite startet lokale TCP-WebSocket-Gegenstellen und prüft Handshake, maskierte Client-Rahmen, fragmentierte Antworten, Ping/Pong, Ablehnungen, Antworten für falsche Geräte und Verbindungsabbrüche. Für die lokalen Integrations-Testsuiten wird `pcntl` benötigt, für den Modulbetrieb nicht. `tests/http-integration.php` verwendet zusätzlich echtes cURL gegen lokale Gegenstellen für einen erzwungenen Timeout und einen anonymen IPv4-Test. Die lokale Gegenstelle ersetzt nur in den Tests TLS; im Modulbetrieb bleibt TLS zwingend aktiv. `openapi.php` prüft Anmeldung, Tokenregion, Modellkonfiguration, Geräte-/Statuszuordnung und den vollständigen Modulablauf mit ersetztem Transport. `openapi-wire.php` sendet vier echte cURL-Anfragen an einen lokalen Server und vergleicht sie mit Anfragen des originalen Python-CloudTransport. Es werden keine realen Zugangsdaten oder Heizgeräte verwendet.

GitHub Actions führt die Tests bei Pushes und Pull Requests unter PHP 8.2, 8.3 und 8.4 aus. Den tatsächlichen Status zeigt der Actions-Reiter des Repositories. Lokal durchgeführt wurde der Test mit PHP 8.3.6; reale Kernel- und Gerätetests bleiben davon getrennt.

Protokollherkunft und Lizenzhinweise: `docs/PROVENANCE.md`. Änderungen: `CHANGELOG.md`. Fehlerberichte: `CONTRIBUTING.md`.
