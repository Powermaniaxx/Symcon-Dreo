# Protokollherkunft

In 0.1.0-beta.6 war der OpenAPI-Zugang aus dem Herstellerprojekt voreingestellt. Seit beta.7 ist App-API der Standard, nachdem Anmeldung, Gerätesuche und Statuslesen damit real bestätigt wurden; der OpenAPI-Login war beim Nutzer mit HTTP 302 gescheitert. Die experimentelle Implementierung orientiert sich an:
https://github.com/dreo-team/pydreo-client/tree/5e4fa54abe795a557e95e2537b7573656a9e9ab7

Gelesene und gegen den festen Commit geprüfte Dateien (lokale Kopien unterscheiden sich lediglich in abschließenden Leerzeilen):
- pydreo/cloud/auth.py: 2b7d38f3884a249d2e595aadb963307e39f6bffa
- pydreo/cloud/const.py: 685db7b1a6bcd8e8f36e0205c30ddca166c2311f
- pydreo/cloud/transport.py: 9a1c1f58ed281c97cb489351ca7aeaf31aa91e9b

Der originale Python-CloudTransport wurde mit einer aufzeichnenden Session, synthetischen Zugangsdaten und einem Ersatz für die requests-Exceptiontypen ausgeführt. Dabei fand kein öffentlicher Netzwerkzugriff statt. Vier resultierende Anfragen ohne variablen Zeitstempel liegen in tests/fixtures/openapi-sdk.json. Der Test openapi-wire.php vergleicht echte PHP-cURL-Anfragen an eine lokale Gegenstelle mit diesen aufgezeichneten Anfragen: Produktions-URL, Methode, Query-Parameter, Protokollheader und JSON-Inhalt. Das prüft die Übereinstimmung mit dem SDK, nicht dessen Akzeptanz durch ein reales Konto.

Anmeldung erfolgt wie im SDK zuerst über open-api-us, danach bestimmt der Token-Suffix EU oder NA den Server. Der Suffix wird vor Verwendung als Bearer entfernt. Unbekannte Suffixe lehnt diese Implementierung ab; der SDK würde dort NA annehmen. Die Konsole erwartet das echte Klartextpasswort und hasht es immer. Die historische SDK-Ausnahme für bereits als MD5 eingegebene Werte wird bewusst nicht übernommen, damit ein echtes Passwort aus 32 Hex-Zeichen ebenfalls korrekt behandelt wird.

Heizgeräte-Felder und das Schema der vom Server gelieferten Modellkonfiguration wurden zusätzlich in https://github.com/dreo-team/hass-dreoverse gelesen: const.py, climate.py, coordinator.py und entity.py. Insbesondere power_switch, hvacmode, ecolevel und heater_entity_config stammen daraus. Konkrete Konfigurationswerte eines echten DR-HSH040S liegen noch nicht vor. Die Tests verwenden dafür ausdrücklich synthetische Konfigurationen. Fehlende Temperatureinheit und mehrdeutige Moduszuordnungen werden deshalb nicht geraten.

Lizenz des Hersteller-Clients: docs/LICENSE-pydreo-client.txt, Copyright 2025 Dreo Team, MIT. Es wird keine offizielle Unterstützung oder Prüfung dieses Symcon-Moduls durch Dreo behauptet. Der im SDK vorbereitete lokale Provider enthält keine nutzbare echte LAN-Anbindung.

## App-API als regulärer Zugang

Die eigenständige PHP-Implementierung orientiert sich an öffentlich dokumentiertem Verhalten der MIT-lizenzierten Community-Integration hass-dreo. Die Bibliothek benötigt deren Python-Code nicht zur Laufzeit.

Referenz: https://github.com/JeffSteinbok/hass-dreo
Beim Abruf am 29.10.2026 war der main-Stand ae10a5ff727d1696c9d6da7876e631601ebe37c6.

Gelesene Datei-Blobs:
- pydreo/constant.py: 7992921ee940f097e1adc8e04edaa21afcbfc558
- pydreo/models.py: 155dbf371457361af49e83a516777ef12001af30
- pydreo/__init__.py: 9fe728e36a94b920c186f296965239c33a0bfac2
- pydreo/helpers.py: a762be57090ae3de72b5c182515bb942afff73bf
- pydreo/commandtransport.py: 89e6fd5f7e5edec2007bc6185f80c58e2ae05663

Die Login-Felder und öffentlich bekannten App-Kennungen, Regionsserver, REST-Abfragen, WebSocket-URL, Befehlsstruktur und 720S-spezifischen Werte stammen aus diesen Referenzen. Die inverse Celsius-Sollwertzuordnung folgt dem Verhalten in custom_components/dreo/dreoheater.py. Bei der 720S-Geräteabfrage wird zusätzlich die im konkreten EU-Fall erfolgreich beobachtete Route /api/app/index/family/room/devices berücksichtigt. Sie wird neben der Standard-Geräteliste unterstützt; daraus folgt keine Zusage, dass sie für jedes Dreo-Konto unverändert funktioniert.

Lizenz der Referenz: docs/LICENSE-hass-dreo.txt, Copyright 2023 Jeff Steinbok, Andy Cooper. Diese Hinweise bleiben bei Weitergabe erhalten.

Symcon-SDK-Referenzen:
- Bibliotheken: https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/bibliotheken/
- Module: https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/
- Struktur: https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/struktur/
- RequestAction: https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/requestaction/
- Darstellungen: https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen/

Die Referenz bestätigt das Protokolldesign, ersetzt aber keinen Test dieses PHP-Moduls im echten Symcon-Kernel und am Gerät.


## Mehrere Modelle ab Version 1.1.0

Die expliziten Profile und ihre Befehle wurden am 30.10.2026 gegen denselben festen Community-Commit ae10a5ff727d1696c9d6da7876e631601ebe37c6 geprüft:

https://github.com/JeffSteinbok/hass-dreo/tree/ae10a5ff727d1696c9d6da7876e631601ebe37c6/custom_components/dreo/pydreo

Zusätzlich gelesen: models.py, pydreofanbase.py, pydreotowerfan.py, pydreoaircirculator.py, pydreoairpurifier.py, pydreohumidifier.py und pydreoheater.py. Die vorhandenen MIT-Lizenzhinweise bleiben erhalten. Die PHP-Implementierung lädt keinen Python-Code zur Laufzeit nach. Die Quelle belegt die Protokollzuordnung, nicht die Funktion dieses Symcon-Moduls am jeweiligen realen Gerät.

Wesentliche Abweichungen von einer pauschalen Übernahme: nur explizite Modelle; keine Präfix-Freigabe für unbekannte Geräte; keine Klimageräte, Deckenventilatoren oder HSH016S-Kombigeräte; keine Winkel/RGB/Timer/Filterresets; keine impliziten Einschaltbefehle. Für HTF007S bleibt ohne eindeutige Metadaten die gemeinsame Vier-Stufen-Grenze statt acht als Standard. Schlaf- und Auto-Feuchtigkeitsziele werden vor Versand anhand des zuletzt gelesenen Modus überprüft. Die bekannten HPF007S/008S/020S-Modekorrekturen Natur=2 und Auto=4 werden berücksichtigt.
