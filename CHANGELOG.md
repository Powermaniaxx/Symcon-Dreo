# Änderungen

## 0.1.0-beta.6 – 30.10.2026

- OpenAPI aus dem Herstellerprojekt pydreo-client ist der neue Standard: Login am US-Endpunkt, Regionswahl aus Token-Suffix, neue Clientkennung, grant_type openapi und dreover 1.0.0.
- Offizielle v2-Geräteliste, v2-Status und HTTP-Steuerung mit desired-Objekt. Der bisherige App-Zugang bleibt auswählbar; Sitzungen werden nach Verfahren getrennt.
- Modellkonfiguration für Temperatureinheit, Sollwertgrenzen und Betriebsart wird ausgewertet. Fehlende Einheit, mehrdeutige Modi und unbekannte Zusatzbefehle werden nicht geraten. Moduswechsel übernimmt keine impliziten Einschaltbefehle aus der Konfiguration.
- Nur tatsächliche Statusdaten dürfen Befehle bestätigen; desired-Werte allein reichen nicht. Fehlendes ptcon wird über HeatingKnown=false kenntlich gemacht.
- Zusätzliche OpenAPI- und Modulablauftests sowie vier native cURL-Anfragen gegen die aus dem originalen Python-SDK aufgezeichnete Referenz. Konkrete 720S-Metadaten bleiben real zu prüfen.

- Interne Formularaktionen geben keinen Text mehr aus. Der onClick-Aufrufer liest die Rückmeldung anschließend als Rückgabewert und zeigt sie selbst an. Dies trennt Aktion und Dialogausgabe nach der beobachteten Kombination aus ausgeführtem Aufruf und Symcon-Fehlermeldung.
- Diagnose wird direkt als Zeichenfolge abgerufen; kein JSON-echo innerhalb von IPS_RequestAction.
- Der anonyme Netzwerktest kennzeichnet erhaltene HTTP-Antworten als Erreichbarkeitsnachweis und meldet ausdrücklich, dass keine Anmeldung getestet wurde. API-Code 100000 bleibt in der Diagnose erhalten.
- Nutzertest mit beta.5: Formularaktionen und erster Timer-Aufruf wurden ausgeführt; Dreo antwortete mit HTTP 200/cURL 0. DNS benötigte etwa fünf Sekunden. Anmeldung und Geräteabfrage sind dadurch noch nicht bestätigt.

## 0.1.0-beta.5 – 29.10.2026

- Eigenständiger Oberfläche-testen-Knopf bestätigt einen Klick ohne Modulaufruf oder Netzwerk.
- Alle Modul-Schaltflächen verwenden IPS_RequestAction und geben Text oder Fehler im Dialog aus; automatische Link-Erkennung ist ausgeschaltet.
- Gerätesuche und manuelle Statusabfrage quittieren den Klick sofort und arbeiten anschließend im Timer.
- Diagnose zeigt den letzten empfangenen Formularauftrag und wartende Arbeit.
- Reale Ausführung der Buttons beim Nutzer bleibt zu prüfen; Ursache des Netzwerk-Timeouts weiterhin offen.

## 0.1.0-beta.4 – 29.10.2026

- Der Verbindungstest startet ohne blockierenden Netzwerkaufruf im Formular und zeigt sofort einen sichtbaren Status.
- Automatische Verbindung und IPv4 werden in getrennten Timer-Aufrufen von jeweils höchstens sechs Sekunden geprüft. Teilergebnisse bleiben erhalten.
- Der Button und sein Timer verwenden den direkten Symcon-Kernelaufruf IPS_RequestAction statt einer zusätzlichen exportierten Hilfsfunktion.
- Testende und Fehler stehen direkt im Formular sowie in der Statusvariable Verbindungstest.
- Wiederholte Klicks duplizieren keinen laufenden Test; Konfigurationsänderungen brechen wartende Tests ab.
- Die tatsächliche Ursache des zuvor gemeldeten Verbindungs-Timeouts ist weiterhin offen.

## 0.1.0-beta.3 – 29.10.2026

- Verbindungsaufbau bis zu 8 statt 4 Sekunden und einzelne HTTP-Anfragen bis zu 15 statt 8 Sekunden; das gemeinsame Budget von 22 Sekunden bleibt bestehen.
- Auch bei cURL-Fehlern werden Fehlercode, HTTP-Status und DNS-/TCP-/TLS-/Antwortzeiten gespeichert. Zuvor blieb der HTTP-Status bei Transportfehlern leer.
- Neuer Knopf „Verbindung prüfen“: anonyme GET-Abfragen mit automatischer Adressauswahl und IPv4, ohne Konto, Passwort oder Token; verändert keine Gerätezustände.
- Tests mit echtem cURL gegen lokale Gegenstellen prüfen Zeitüberschreitung und anonymen IPv4-Test.
- Ob das beim Nutzer beobachtete cURL-28-Zeitlimit an DNS, Verbindung, Serverantwort oder den bisherigen Limits liegt, ist noch offen.

## 0.1.0-beta.2 – 29.10.2026

- Ersteinrichtung: eine noch fehlende Geräteauswahl ist ein eigener Wartezustand statt einer ungültigen Konfiguration.
- Suchergebnisse und konkrete Fehlermeldungen stehen direkt bei der Geräteauswahl und bleiben nach erneutem Öffnen sichtbar.
- Keine automatischen Fehlerabfragen, solange noch kein Gerät ausgewählt ist.
- Diagnoseknopf mit HTTP-/API-Status, abgefragten Routen und anonymisierten Geräteanzahlen; keine Kontodaten, Token oder Seriennummern.
- Zusätzliche Regressionstests für Suche vor Geräteauswahl, leere Antworten und Formularrückmeldungen.
- Die Ursache einer beim Nutzer leeren Geräteliste ist noch nicht abschließend bestätigt; dieses Update verbessert Einrichtung und Diagnose.

## 0.1.0-beta.1 – 29.10.2026

- Erste eigenständige Symcon-9-Modulbibliothek für DR-HSH040S / Dreo 720S.
- Eigene Nutzerkonfiguration und Gerätesuche mit EU-/NA-Region sowie Familien- und Standard-Geräteabfrage.
- Ein/Aus, Betriebsart, Heizstufe, Celsius-Sollwert, Celsius-Raumtemperatur und lesbarer Heizstatus.
- Keine automatische Einschaltung bei Einrichtung, Neustart oder Wiederverbindung.
- Cloud-Annahme und tatsächliche Statusbestätigung getrennt; keine optimistischen Schaltwerte.
- Zeitlimits, Sitzungserneuerung, Fehlerpausen, konkurrierende Zugriffe und bereinigte Diagnoseausgabe.
- Dokumentation und automatisierte Tests; noch kein praktischer Symcon-/Dreo-Gerätetest.
