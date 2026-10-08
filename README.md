# local_kikursbauer – Moodle-Kurse mit einem KI-Assistenten bauen

Moodle-Plugin, das einen eingeschränkten Webservice „KI-Kursbauer“ bereitstellt.
Freigegebene Lehrkräfte können damit ihre Kurse von einem KI-Assistenten
(z. B. Claude Code über eine MCP-Brücke) aufbauen lassen – nur im eigenen
Kursbereich und nur mit den Rechten, die sie ohnehin haben.

Der Assistent läuft nicht in Moodle, sondern am Rechner der Lehrkraft und
ruft den Dienst per REST mit einem persönlichen Token auf.

## Was das Plugin bei der Installation einrichtet

- **Externer Dienst „KI-Kursbauer“** mit fester Funktionsliste, aktiviert.
  Plugin-Updates pflegen die Liste mit.
- **Recht** `local/kikursbauer:use` – ohne dieses Recht ist der Dienst gesperrt.
- **Systemrolle „KI-Kursbauer“** mit `local/kikursbauer:use`,
  `webservice/rest:use` und `moodle/webservice:createtoken`.

## Was der Assistent darf

Erlaubt:

- Kurse im eigenen Kursbereich anlegen
- in eigenen Kursen Abschnitte, Seiten, Textfelder, Links, Bücher, Aufgaben,
  Foren, Tests (GIFT-Import), Glossare, Dateien, Ordner, H5P-Inhalte,
  Feedbacks und Bilder anlegen und ändern
- Kursinhalte lesen, auf die die Lehrkraft Zugriff hat

Gesperrt:

- Kurse und Kursbereiche anderer Lehrkräfte (Einstellung „Nur eigener
  Kursbereich“, standardmäßig an)
- Löschen jeglicher Inhalte
- Teilnehmende, Abgaben, Bewertungen und Beiträge lesen
- Tests ändern, in denen es schon Versuche gibt; Feedbacks umbauen, die schon
  Antworten haben

Neue Kurse, Abschnitte und Aktivitäten entstehen verborgen. Alle Aufrufe
stehen im Log (*Berichte › Logs*, Quelle „ws“).

## Einrichtung

1. Plugin nach `local/kikursbauer` kopieren (ab Moodle 5.1:
   `public/local/kikursbauer`) und die Aktualisierung durchführen.
   **Nur eigener Kursbereich** eingeschaltet lassen.
2. *Website-Administration › Allgemein › Erweiterte Funktionen*:
   **Webservices aktivieren**.
3. *Website-Administration › Server › Webservices › Protokolle verwalten*:
   **REST** aktivieren.
4. Kontrolle: *Website-Administration › Plugins › Lokale Plugins ›
   KI-Kursbauer* zeigt oben vier Häkchen.
5. Optional: Kursformat [Tiles](https://moodle.org/plugins/format_tiles)
   für Kachelkurse; dann steht zusätzlich `format_tiles_set_image` im Dienst.

Pro Lehrkraft: *Nutzer/innen › Rechte › Systemrollen zuweisen › KI-Kursbauer*.
Voraussetzung ist, dass die Person in ihrem Kursbereich Kursersteller/in ist.
Den Token holt sie sich selbst unter *Profil › Einstellungen ›
Sicherheitsschlüssel*, Dienst „KI-Kursbauer“.

## Sperren und entfernen

| Umfang | Vorgehen |
|---|---|
| Eine Lehrkraft | Systemrolle „KI-Kursbauer“ entziehen – der Token ist sofort ungültig |
| Alle | *Server › Webservices › Externe Dienste* › „KI-Kursbauer“ deaktivieren |
| Vollständig | Plugin deinstallieren – Dienst und Rolle verschwinden, angelegte Inhalte bleiben |

## Hinweise

- **Andere offene Dienste prüfen:** Das Recht „Token erstellen“ erzeugt Tokens
  für alle Dienste, die nicht auf berechtigte Nutzer/innen beschränkt sind.
  Unter *Externe Dienste* nachsehen, ob es außer „KI-Kursbauer“ und dem Dienst
  der Moodle-App weitere solche Dienste gibt.
- **Kursersteller auf höherer Ebene:** Wer auf einer übergeordneten Kategorie
  Kursersteller/in oder Manager/in ist, gilt dort überall als „eigener
  Kursbereich“.
- **Datenschutz:** Der Dienst enthält keine Funktion, die Daten von
  Schülerinnen und Schülern liest. Kursinhalte werden aber vom Assistenten an
  dessen Anbieter übertragen. Die oder der Datenschutzbeauftragte sollte den
  Einsatz freigeben.
- **H5P:** Pakete werden ohne Bibliotheken hochgeladen; die Inhaltstypen müssen
  auf der Instanz bereits installiert sein. `local_kikursbauer_site_info`
  listet sie auf.

## Funktionen im Dienst

| Funktion | Zweck |
|---|---|
| `local_kikursbauer_create_course` | Kurs im eigenen Kursbereich anlegen |
| `local_kikursbauer_update_sections` | Abschnitte anlegen, benennen, beschreiben |
| `local_kikursbauer_add_module` | Aktivität anlegen |
| `local_kikursbauer_update_module` | Aktivität ändern (feste Liste erlaubter Felder) |
| `local_kikursbauer_move_module` | Aktivität verschieben |
| `local_kikursbauer_add_quiz_questions` | Testfragen importieren (GIFT) |
| `local_kikursbauer_add_glossary_entries` | Glossareinträge anlegen |
| `local_kikursbauer_upload_file` | Bild in Aktivität oder Abschnitt speichern, Kachelfoto (Tiles) |
| `local_kikursbauer_site_info` | Aktivitätstypen und installierte H5P-Bibliotheken auflisten |
| `local_kikursbauer_get_tools` | Funktionsliste für den Assistenten |

Dazu lesende Core-Funktionen (`core_course_get_contents`,
`core_course_get_categories`, `mod_*_get_*_by_courses` u. a.) – ohne Abgaben
und Versuche.

## Kompatibilität

Entwickelt und getestet auf Moodle 5.1.3. Kompatibilität mit 4.5 wurde am
Quellcode geprüft. Reifegrad: Alpha.

## Lizenz

GNU GPL v3 oder später, siehe [LICENSE](LICENSE). Erstellt mit Unterstützung
von Claude (KI).
