# Unterstützte Modellprofile – 1.1.0

39 explizite App-API-Profile. Die bloße Verfügbarkeit in der Dreo-App reicht nicht aus. Zusätzliche Modelle werden erst nach Prüfung ihrer Protokollzuordnung ergänzt; es gibt keine Freigabe nach bloßem Modellpräfix.

**Reale Bestätigung:** Bisher nur grundlegende EU-Anmeldung, Gerätesuche, Statuslesen und Ein/Aus des DR-HSH040S. Alle anderen Zeilen bedeuten „implementiert und mit synthetischen Daten geprüft“, nicht „am echten Gerät bestätigt“. Die verfügbaren Funktionen hängen zusätzlich von den gültigen Statusfeldern ab.

| Modell | Geräteart | Grenzen / Besonderheiten |
| --- | --- | --- |
| DR-HSH003S | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH004S | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH006S | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH009S | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH009AS | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH010S | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH011 | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH011S | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH017S | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH017BS | Heizgerät | H1–H3, 5–29 °C |
| DR-HSH034S | Heizgerät | H1–H3, 5–35 °C |
| DR-HSH040S | Heizgerät | H1–H3, 5–35 °C; grundlegende Anbindung real bestätigt |
| DR-HSH041S | Heizgerät | H1–H3, 5–35 °C |
| DR-HTF007S | Ventilator | Lüfterstufen 1–4; 8 nur bei eindeutiger Gerätekonfiguration, bekannte alte MCU bleibt bei 4 |
| DR-HTF011S | Ventilator | Lüfterstufen 1–9 |
| DR-HTF018S | Ventilator | Lüfterstufen 1–9 |
| DR-HTF017S | Ventilator | Lüfterstufen 1–4 |
| DR-HTF024S | Ventilator | Lüfterstufen 1–9 |
| DR-HTF021S | Ventilator | Lüfterstufen 1–12 |
| DR-HTF021AS | Ventilator | Lüfterstufen 1–12 |
| DR-HTF518S | Ventilator | Lüfterstufen 1–9 |
| DR-HAF001S | Ventilator | Lüfterstufen 1–4 |
| DR-HAF008S | Ventilator | Lüfterstufen 1–9 |
| DR-HPF004S | Ventilator | Lüfterstufen 1–9 |
| DR-HPF005S | Ventilator | Lüfterstufen 1–10 |
| DR-HPF007S | Ventilator | Lüfterstufen 1–10 |
| DR-HPF008S | Ventilator | Lüfterstufen 1–9 |
| DR-HPF020S | Ventilator | Lüfterstufen 1–9 |
| DR-HPF022S | Ventilator | Lüfterstufen 1–9 |
| DR-HAP006S | Luftreiniger | Lüfterstufen 1–4 |
| DR-HAP007S | Luftreiniger | Lüfterstufen 1–4 |
| DR-HAP008S | Luftreiniger | Lüfterstufen 1–4 |
| DR-HAP009S | Luftreiniger | Lüfterstufen 1–4 |
| DR-HAP010S | Luftreiniger | Lüfterstufen 1–4; Stufen nur bei ausdrücklich aktiver Reinigung |
| DR-HHM003S | Luftbefeuchter | Feuchtigkeitsziel 30–90 % oder enger laut Gerätemetadaten; Nebelrohwert 0–6; keine feste Modusauswahl |
| DR-HHM005S | Luftbefeuchter | Feuchtigkeitsziel 30–90 % oder enger laut Gerätemetadaten; Nebelrohwert 0–6 |
| DR-HHM006S | Luftbefeuchter | Feuchtigkeitsziel 30–90 % oder enger laut Gerätemetadaten; Nebelrohwert 0–6 |
| DR-HHM014S | Luftbefeuchter | Feuchtigkeitsziel 30–90 % oder enger laut Gerätemetadaten; Nebelrohwert 0–6 |
| DR-HHM015S | Luftbefeuchter | Feuchtigkeitsziel 30–90 % oder enger laut Gerätemetadaten; Nebelrohwert 0–6; keine feste Modusauswahl |

Nicht enthalten sind unbekannte Modellrevisionen, Fan/Heater-Kombigeräte wie DR-HSH016S, Klimageräte, Deckenventilatoren und weitere Gerätekategorien. Auch bei enthaltenen Profilen werden Spezialfunktionen wie RGB, Filterreset, Timer und vertikale Winkel nicht angeboten. Für 007S ohne gesicherte Acht-Stufen-Metadaten ist die Beschränkung auf vier Stufen beabsichtigt.

Für ein nicht enthaltenes Modell ein GitHub-Issue mit Modellnummer, Geräteart und gewünschten Funktionen eröffnen. Keine Zugangsdaten, Token, Seriennummern oder ungefilterten Cloud-Antworten veröffentlichen. Quellen und feste Referenzversion stehen in [PROVENANCE.md](PROVENANCE.md).
