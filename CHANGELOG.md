# v1.3.0
## 2026-11-11

1. [](#improved)
    * **The three messages a visitor can see are translated.** Document delivery answers with "not found", "reserved for the committee" or "file missing" — in German, whatever language the club's site runs in. They come from the language files now, in German, English, French and Italian.
    * **The document categories no longer fall back to German.** Without a `kategorien` setting the plugin used "Statuten und Reglemente", "Generalversammlung" and so on — on every site, including a French-speaking one. The defaults are read from the language files; an explicit `kategorien` setting still wins.
    * This changelog is in English with ISO dates.

    Checked against the points that came back from the directory review of cw-spamschutz and cw-cookie ([getgrav/grav#4395](https://github.com/getgrav/grav/issues/4395), [#4396](https://github.com/getgrav/grav/issues/4396)) before submitting.

# v1.2.1
## 2026-11-09

1. [](#improved)
    * **Fuer die Anmeldung bei getgrav.org vorbereitet.** Beschreibung, Schlagworte und README auf Englisch -- das Verzeichnis wird international gelesen. Die deutsche Fassung steht als `README.de.md` daneben. `bugs` und `docs` zeigen auf das oeffentliche Repository, der Autor einheitlich auf die Firma.
    * `LICENSE` ergaenzt -- die Datei fehlte, obwohl das Plugin seit jeher MIT ist.
    * Die Auswahlwerte der **Listenspalten** (Kategorie, Zugriff, Status) standen fest auf Deutsch, waehrend das Bearbeitungsformular schon Sprachmarken trug. Jetzt beide; `normalizeOptionLabels()` in FlexApiController uebersetzt sie.

# v1.2.0
## 2026-11-09

1. [](#improved)
    * **Die Beschriftungen im Admin sprechen vier Sprachen.** 106 Beschriftungen, Hilfetexte, Platzhalter und Auswahlwerte standen fest verdrahtet auf Deutsch in den Blueprints; jetzt stehen sie als Sprachmarken in `languages/de|en|fr|it.yaml`. Auf einer deutschen Installation aendert sich nichts Sichtbares.
    * Das Plugin hatte bisher gar keine Sprachdateien.
    * Die Angaben am Dateianfang (Name, Beschreibung, Autor) bleiben bewusst Text: Das Grav-Verzeichnis liest sie woertlich, eine Sprachmarke stuende dort im Katalog.

# v1.1.0
## 2026-10-21

1. [](#improved)
    * **Admin-Listen deutsch beschriftet und lesbar.** Jede Spalte der Flex-Listen traegt Beschriftung, Typ und Auswahlwerte aus dem Formular (`config.admin.list.fields.<feld>.field`). Vorher fand flex-objects die Felder in Reitern nicht: admin2 zeigte Feldnamen wie «Customer Company», Datumswerte roh (2026-10-21) und Status-Schluessel statt Woertern. Jetzt deutsche Titel, Daten im Format TT.MM.JJJJ, Status als Wort. Alle Spalten sortierbar (Klick auf den Kopf).

