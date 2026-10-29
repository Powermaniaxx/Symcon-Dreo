# Protokollherkunft

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

