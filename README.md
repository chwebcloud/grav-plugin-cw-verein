# CHWEB Verein (cw-verein)

Members, documents and events of a club or association, as three Flex
directories in the Grav admin — with document delivery that actually checks
who is asking.

*Verein* is the Swiss and German word for a club, society or association: a
shooting club, a choir, a hunting association, a village committee. They all
keep the same three lists, and they all run into the same problem: the
statutes and the minutes of the general meeting should be readable by members
but not by the whole internet. Putting those files in the pages folder and
hoping nobody guesses the URL is not an access check. This plugin keeps the
files outside the web root and hands them out through a route that asks first.

## The three directories, and a fourth

| Directory | Stored in |
|---|---|
| `mitglieder` — members | `user/data/cw-verein/mitglieder.json` |
| `termine` — events | `user/data/cw-verein/termine.json` |
| `dokumente` — documents | `user/data/cw-verein/dokumente/<key>/item.json` plus the file |
| `wildkameras` — trail cameras (optional, `wildkameras: true`) | `user/data/wildkameras/wildkameras.json` |

The fourth one is for hunting associations: cameras with owner, location,
coordinates and the page they upload to. It is off by default and stays
invisible until you switch it on.

## Access levels

`user/data/cw-verein/` is closed to direct requests (`.htaccess`, or the
equivalent rule in your web server). Documents are delivered through
`/dokument/<key>/<name>`, and the plugin checks the document's own `zugriff`
level before handing anything over:

| Level | Who gets the file |
|---|---|
| `oeffentlich` | every visitor |
| `mitglieder` | signed-in users |
| `vorstand` | members of the committee group, and super admins |

The committee group is a normal Grav user group; its name is configurable and
defaults to `Vorstand`. Everything else is a 404 — not a redirect to a login
form, which would tell an outsider that the document exists.

## Twig

```twig
{{ verein.dokumente() }}        {# grouped by category, only what this visitor may see #}
{{ verein.termine(signed_in, 4) }}
{{ verein.mitglieder() }}       {# signed in only, and only members marked visible #}
{{ verein.vorstand() }}         {# members who hold a function — public #}
```

The member list is divided into groups you define in the plugin
configuration; the order there is the order on the page, and anyone without a
group appears under the first one.

## Configuration

```yaml
enabled: true
route: /dokument              # where documents are delivered after the check
vorstand_gruppe: Vorstand     # the user group that may see committee documents
wildkameras: false            # the optional trail-camera directory

gruppen:                      # how the member list is divided
  - { schluessel: mitglieder, titel: Vereinsmitglieder }
  - { schluessel: goenner, titel: Gönner }
```

## What this plugin is not

It brings no page templates and no styling. It brings the data, the admin
screens and the Twig functions; how the member list and the calendar look is
your theme's business.

The field labels, the default category names and the three messages a visitor
can meet at `/dokument` are translated into German, English, French and
Italian.

Two things stay German on purpose. The **stored keys** (`mitglieder`,
`vorstand`, `statuten`) — renaming them would break existing data, and nobody
reads them. And the **names of the three directories** in the admin sidebar
("Mitglieder", "Termine", "Dokumente"), because Grav does not translate a
Flex directory's own title; everything inside them is translated.

## Requirements

Grav ≥ 2.0 with `flex-objects` ≥ 1.4 and `login` ≥ 3.9.

## Licence

MIT — see [LICENSE](LICENSE). © CHWEB GmbH — https://chweb.ch
