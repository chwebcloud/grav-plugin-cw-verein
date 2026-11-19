# v1.5.0
## 2026-11-19

The last two points of the directory review ([getgrav/grav#4400](https://github.com/getgrav/grav/issues/4400)) — and, found while confirming them, the reason the document upload never worked in Admin 2.0 at all.

1. [](#bugfix)
    * **Uploading a document works in Admin 2.0 again.** It failed outright with *"Cannot resolve 'self@:' path: scope '' is not a supported owner"*. The admin client picks the upload route from the field: with a `destination` it posts to `/blueprint-upload`, whose resolver only knows the scopes `plugins/`, `themes/`, `pages/` and `users/` — a Flex object arrives with an empty scope. Without a `destination` it posts to `/flex-objects/<type>/<key>/media`, which stores the file exactly where it belongs, so `destination: self@` is gone. No other blueprint in a stock install uses `self@`, which is why nobody had hit this.
    * A **new** document must be saved once before its file can be added: without a key the upload address has no `<key>` segment, lands on the `{type}/{key}` route and answers *"Method 'POST' is not allowed"*. For pages the admin uses an `__unresolved__` placeholder; Flex has nothing of the sort. The field's help text says so now.

    * **1. The document files are no longer served by the web server.** Grav's own rules hand out a fixed list of media types straight from `user/data` without starting Grav — PDF, JPEG, PNG and WebP among them — so a club document lay open at its direct address whatever its access level, and unpublishing it did not take that back. An uploaded file is now stored under its own name plus `.bin`, which is on neither the Apache allow-list nor the one in the shipped `nginx.conf`, so both refuse it on any host without a line of server configuration. Measured on a plain Grav with no rules of its own: the same file answers 200 as `probe.pdf` and 403 as `probe.pdf.bin`. The visitor still gets the original filename and the right media type, because both live in the record.
        * Two other approaches were measured and dropped: a dot-folder is denied by Apache but **served** by nginx, whose rule only looks at the final extension; and a folder of one's own under `user/` has no catch-all rule at all — only `config`, `env`, `accounts` and `data` are named.
        * `bin/plugin cw-verein dokumente [--schreiben]` moves documents from an earlier version. It is repeatable and refuses to do anything twice. Since `user/data` belongs on the server, it has to run there as well.
    * **3. The path to a file is built from the document's own folder and the bare filename**, confined with `realpath()`. It used to be taken from the record as written and only checked for the `user/data/cw-verein/` prefix. That depended on whatever the admin wrote into the field — if Admin 2.0 stores only the bare filename there, as the review suspects, nothing was found and every document answered "file missing" — and it let one document point at another's file. Only the filename counts now, and it must lie in this document's folder.

# v1.4.0
## 2026-11-19

Three of the five points from the directory review ([getgrav/grav#4400](https://github.com/getgrav/grav/issues/4400)), plus two of the smaller ones. Points 1 (where the files are stored) and 3 (what Admin 2.0 writes into a file field) are still open and need a release of their own.

1. [](#bugfix)
    * **2. Every refusal answers the same way: 404.** A signed-out visitor was redirected to the login page, a signed-in non-committee member got a 403, and only an unknown key was a 404. So the route told an outsider two things it should not: that this key exists, and which level it carries. It also contradicted the README, whose first promise is that this is not a redirect. One answer now, with one sentence — and the sentence mentions signing in, so a member still knows what to do.
    * **4. `vorstand()` hands out first name, surname, function and group, and nothing else** unless the visitor is signed in. It used to return the whole record — street, e-mail, telephone, remarks, hunting licence — and leave the hiding to the template. A template that forgets puts the committee's home addresses online, and the plugin never notices. Checked on a real site: the public page shows names and functions, and none of the contact fields.
    * The downloaded filename follows RFC 6266 (`filename*=UTF-8''…`). An umlaut used to arrive as mojibake, and a quotation mark in a filename would have torn the header apart. Responses also carry `X-Content-Type-Options: nosniff`.

2. [](#improved)
    * **5. No default carries a German word or a Swiss habit any more.**
        * `termine()` used `Europe/Zurich`, hardcoded. It uses the site's timezone.
        * The order of the committee was a fixed list of German words ("präsident", "kassier", "sekretär"); on a French or Italian site none of them matched and the committee stood in alphabetical order. The order is a setting now (`vorstand_reihenfolge`), empty by default, and when several entries match a function the **most specific** one wins — so "vizepräsident" beats "präsident", which the old list got wrong.
        * The default group titles "Vereinsmitglieder" and "Gönner" appeared on the public page of every site that switched the plugin on. The default is one group now, named from the language files.
        * The committee user group defaulted to `Vorstand`; it defaults to `committee`.
    * **A members group can be named** (`mitglieder_gruppe`). Empty, as before, means every signed-in person counts as a member — which is fine where accounts come by invitation, and wrong where visitors can register themselves. The README says so.

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

