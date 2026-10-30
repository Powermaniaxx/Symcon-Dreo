# Entwicklung und Fehlerberichte

Vor Änderungen alle sieben eigenständigen PHP-Testsuiten ausführen. Die CI prüft zusätzlich mit dem offiziellen Symcon-Validator und PHPUnit die Bibliotheks- und Modulstruktur. Neue Gerätemodelle nur mit passenden Protokollzuordnungen, Tests und einer klaren Aussage zum realen Teststand hinzufügen. Unbekannte Modelle dürfen nicht automatisch Befehle eines vermeintlich ähnlichen Heizers erhalten.

Bei Fehlerberichten angeben: Symcon-Version, Modulversion, Modell, Kontoregion, betroffene Aktion, Verhalten der Dreo-App und bereinigte Ausgabe von `DREO_GetDiagnostics`. Keine Passwörter, Zugriffstoken, vollständigen Geräte-Seriennummern oder unveränderten HTTP-Mitschnitte veröffentlichen.

Die `library.json`- und Modul-GUIDs bei Updates beibehalten. Neue öffentliche Funktionen brauchen vollständige Typen. Keine festen Objekt-IDs, Container-Namen oder Kontozugangsdaten in den Modulcode aufnehmen. Passwort- oder Tokenwerte dürfen nicht in Logs oder Diagnoseausgaben geraten.

HTTP- und WebSocket-Verkehr dürfen TLS-Prüfungen nicht umgehen. Eine Server-Annahme ist keine Gerätebestätigung. UI-Werte dürfen erst anhand des abgefragten Gerätestatus aktualisiert werden.

Für Releases Versionsangaben in `library.json`, Formular, Diagnoseausgabe und Changelog zusammen anpassen. Die reale Kernel-/Geräteprüfung in `docs/TESTPLAN.md` dokumentieren. Automatisierte Tests, Nutzerbestätigungen und noch offene reale Prüfungen müssen getrennt und wahrheitsgetreu dokumentiert werden.
