# Änderungen

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
