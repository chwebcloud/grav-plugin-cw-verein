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

## Where the files are, and why they look odd

An uploaded document is stored under its own name **plus `.bin`** —
`statuten.pdf` becomes `statuten.pdf.bin` on disk. That is deliberate, and it
is the only thing that reliably keeps the file off the open web.

Grav's own web-server rules serve a fixed list of media types straight out of
`user/data` without starting Grav at all — PDF, JPEG, PNG and WebP among them
(the root `.htaccess`, and the same list in the shipped `nginx.conf`). A club
document therefore lay open at its direct address whatever its access level,
and neither unpublishing it nor raising the level took that back.

Three ways around it were measured; two of them do not work:

| Approach | Apache | nginx |
|---|---|---|
| Your own `.htaccess` | works | ignored — and Grav rewrites the file on every core update |
| A dot-folder (`.files/`) | denied | **served** — the rule only looks at the final extension |
| **An extension that is on no allow-list** | denied | denied |

Those rules are allow-lists: what is not on them is refused. So the file gets
an extension that is on neither, and both servers refuse it, on any host,
without a line of server configuration. The real name and the media type live
in the record; delivery happens through `/dokument/<key>` after the access
check, and the visitor gets the original filename back.

Documents from an earlier version are moved by a command that can be run
twice:

```bash
bin/plugin cw-verein dokumente              # show what would change
bin/plugin cw-verein dokumente --schreiben  # rename and update the records
```

If `user/data` is kept outside your deployment — and it should be — run it
once where you develop and once on the server.

## Access levels

Documents are delivered through `/dokument/<key>/<name>`, and the plugin
checks the document's own `zugriff` level before handing anything over:

| Level | Who gets the file |
|---|---|
| `oeffentlich` | every visitor |
| `mitglieder` | signed-in users, or the members group if you name one |
| `vorstand` | members of the committee group, and super admins |

Both groups are normal Grav user groups and both names are yours to choose;
`committee` is only the default, and `mitglieder_gruppe` is empty, which means
*every signed-in person counts as a member*. That is fine on a site where
accounts are handed out by invitation. **On a site where visitors can register
themselves, name a group** — otherwise anyone with a fresh account reads the
minutes of the general assembly.

**Every refusal is the same 404**, with the same sentence: a document that
does not exist, one that is not public, and one reserved for the committee are
indistinguishable from outside. No redirect to a login form — that would tell
an outsider the document exists, and it is the one promise this route makes.
The sentence mentions signing in, so a member still knows what to do.

## Twig

```twig
{{ verein.dokumente() }}        {# grouped by category, only what this visitor may see #}
{{ verein.termine(signed_in, 4) }}
{{ verein.mitglieder() }}       {# members only, and only those marked visible #}
{{ verein.vorstand() }}         {# members who hold a function — public #}
```

`vorstand()` is the one that is public, so it hands out **first name, surname,
function and group, and nothing else** unless the visitor is signed in. Street,
e-mail, telephone and remarks stay in the record. A template cannot leak what
it never receives.

The member list is divided into groups you define in the plugin
configuration; the order there is the order on the page, and anyone without a
group appears under `mitglieder` or, if you did not define it, under the first
group.

## Configuration

```yaml
enabled: true
route: /dokument              # where documents are delivered after the check
vorstand_gruppe: committee    # the user group that may see committee documents
mitglieder_gruppe: ''         # empty: every signed-in person counts as a member
vorstand_reihenfolge: ''      # order of the committee functions; empty: by function and name
kein_vorstand: ''             # functions that are not a committee post; empty: the word for "member"
wildkameras: false            # the optional trail-camera directory

gruppen: []                   # how the member list is divided; empty: one group
#  - { schluessel: mitglieder, titel: Members }
#  - { schluessel: goenner, titel: Patrons }
```

Nothing in here carries a German word or a Swiss habit as its default. The
committee order, the names of the groups and the word that means "no committee
post" are all yours to set; without them the plugin sorts by function and
name, shows a single group, and takes the word for "member" from its own
language files. Dates follow the site's timezone, not the author's.

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
