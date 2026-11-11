# CHWEB-Verein (cw-verein)

Mitglieder, Dokumente und Termine eines Vereins als Flex-Verzeichnisse (Grav 2, admin2).

## Datenablage

| Verzeichnis | Ablage |
|---|---|
| `mitglieder` | `user/data/cw-verein/mitglieder.json` |
| `termine` | `user/data/cw-verein/termine.json` |
| `dokumente` | `user/data/cw-verein/dokumente/<key>/item.json` + Datei |
| `wildkameras` (optional, Config `wildkameras: true`) | `user/data/wildkameras/wildkameras.json` |

Der Ordner `user/data/cw-verein/` ist für den direkten Abruf gesperrt
(.htaccess/Caddy). Dokumente kommen über `/dokument/<key>/<name>`; das Plugin
prüft dabei die Stufe `zugriff`: `oeffentlich`, `mitglieder` (angemeldet),
`vorstand` (Gruppe «Vorstand» oder Super-Admin).

## Twig

```twig
{{ verein.dokumente() }}      {# nach Kategorie gruppiert, nur was der Besucher sehen darf #}
{{ verein.termine(angemeldet, 4) }}
{{ verein.mitglieder() }}     {# nur angemeldet, nur «sichtbar» #}
{{ verein.vorstand() }}       {# Mitglieder mit Funktion, öffentlich #}
```
