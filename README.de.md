# CHWEB-Verein (cw-verein)

Mitglieder, Dokumente und Termine eines Vereins als Flex-Verzeichnisse (Grav 2, admin2).

## Datenablage

| Verzeichnis | Ablage |
|---|---|
| `mitglieder` | `user/data/cw-verein/mitglieder.json` |
| `termine` | `user/data/cw-verein/termine.json` |
| `dokumente` | `user/data/cw-verein/dokumente/<key>/item.json` + Datei |
| `wildkameras` (optional, Config `wildkameras: true`) | `user/data/wildkameras/wildkameras.json` |

Eine hochgeladene Datei liegt unter ihrem Namen **plus `.bin`**: aus
`statuten.pdf` wird auf der Platte `statuten.pdf.bin`. Das ist Absicht und
das Einzige, was die Datei zuverlässig vom offenen Netz fernhält.

Gravs eigene Serverregeln liefern unterhalb von `user/data` eine feste Liste
von Medientypen direkt aus, ohne Grav zu starten — PDF, JPEG, PNG und WebP
darunter (Wurzel-`.htaccess` und dieselbe Liste in der mitgelieferten
`nginx.conf`). Ein Vereinsdokument lag damit unter seiner direkten Adresse
offen, egal welche Zugriffsstufe eingestellt war.

Drei Wege wurden gemessen, zwei taugen nicht:

| Weg | Apache | nginx |
|---|---|---|
| eigene `.htaccess` | wirkt | wird nicht gelesen — und Grav schreibt die Datei bei jedem Kernupdate neu |
| Punkt-Ordner (`.dateien/`) | gesperrt | **ausgeliefert** — die Regel sieht nur die letzte Endung |
| **Endung, die auf keiner Liste steht** | gesperrt | gesperrt |

Diese Regeln sind Erlaubnislisten: Was nicht daraufsteht, wird verweigert.
Also bekommt die Datei eine Endung, die auf keiner steht. Der echte Name und
der Medientyp stehen im Datensatz; ausgeliefert wird über `/dokument/<key>`
nach Zugriffsprüfung, und der Besucher bekommt den Originalnamen zurück.

Dokumente aus einer früheren Fassung zieht ein Befehl um, der zweimal laufen
darf:

```bash
bin/plugin cw-verein dokumente              # zeigt nur, was geschähe
bin/plugin cw-verein dokumente --schreiben  # benennt um und zieht die Datensätze nach
```

Weil `user/data` dem Server gehört, muss er **zweimal** laufen: einmal in der
Werkstatt, einmal auf dem Server.

Dokumente kommen über `/dokument/<key>/<name>`; das Plugin prüft dabei die
Stufe `zugriff`: `oeffentlich`, `mitglieder`, `vorstand`.

**Jede Verweigerung ist derselbe 404**, mit demselben Satz: Ein Dokument, das
es nicht gibt, eines, das nicht öffentlich ist, und eines, das dem Vorstand
vorbehalten ist, sind von aussen nicht zu unterscheiden. Keine Weiterleitung
auf die Anmeldung — die verriete, dass es das Dokument gibt. Der Satz nennt
die Anmeldung, damit ein Mitglied trotzdem weiss, was zu tun ist.

## Twig

```twig
{{ verein.dokumente() }}      {# nach Kategorie gruppiert, nur was der Besucher sehen darf #}
{{ verein.termine(angemeldet, 4) }}
{{ verein.mitglieder() }}     {# nur Mitglieder, nur «sichtbar» #}
{{ verein.vorstand() }}       {# Mitglieder mit Funktion, öffentlich #}
```

`vorstand()` ist die öffentliche: Sie gibt **Vorname, Name, Funktion und
Gruppe heraus und sonst nichts**, solange niemand angemeldet ist. Strasse,
Mail, Telefon und Bemerkung bleiben im Datensatz. Eine Vorlage kann nicht
ausplaudern, was sie nie bekommt.

## Konfiguration

```yaml
enabled: true
route: /dokument              # Adresse für den Dokumentabruf
vorstand_gruppe: committee    # Benutzergruppe, die Vorstandsdokumente sieht
mitglieder_gruppe: ''         # leer: jede angemeldete Person zählt als Mitglied
vorstand_reihenfolge: ''      # Reihenfolge der Funktionen; leer: Funktion, dann Name
kein_vorstand: ''             # Funktionen ohne Vorstandsposten; leer: das Wort «Mitglied»
wildkameras: false            # zusätzliches Verzeichnis für Jagdvereine

gruppen: []                   # Gliederung der Mitgliederliste; leer: eine Gruppe
#  - { schluessel: mitglieder, titel: Vereinsmitglieder }
#  - { schluessel: goenner, titel: Gönner }
```

**`mitglieder_gruppe` leer heisst: jede angemeldete Person ist ein Mitglied.**
Das genügt, wo Konten per Einladung vergeben werden. Auf einer Website, wo
sich Besucher selbst registrieren können, gehört hier eine Gruppe hin — sonst
liest jeder mit einem frischen Konto das Protokoll der Generalversammlung.

Keine dieser Vorgaben trägt ein deutsches Wort oder eine Schweizer Gewohnheit.
Daten folgen der Zeitzone der Website, nicht der des Autors.
