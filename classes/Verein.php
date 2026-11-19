<?php
declare(strict_types=1);

namespace Grav\Plugin\CwVerein;

use Grav\Common\Grav;

/**
 * Lesender Zugang zu Mitgliedern, Dokumenten und Terminen -- für Twig.
 * Alles, was die Website zeigt, kommt hier durch; die Zugriffsprüfung
 * (Zugriff) filtert, was der Besucher sehen darf.
 */
final class Verein
{
    private Grav $grav;
    private Zugriff $zugriff;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->zugriff = new Zugriff($grav);
    }

    /** @return array<int, array<string, mixed>> */
    private function sammlung(string $typ): array
    {
        $flex = $this->grav['flex'] ?? null;
        if (!$flex) {
            return [];
        }
        try {
            $dir = $flex->getDirectory($typ);
            if (!$dir) {
                return [];
            }
            $aus = [];
            foreach ($dir->getCollection() as $obj) {
                $d = $obj->jsonSerialize();
                if (isset($d['published']) && !$d['published']) {
                    continue;
                }
                $d['key'] = $obj->getKey();
                $aus[] = $d;
            }
            return $aus;
        } catch (\Throwable $e) {
            $this->grav['log']->warning('cw-verein ' . $typ . ': ' . $e->getMessage());
            return [];
        }
    }

    public function angemeldet(): bool
    {
        return $this->zugriff->angemeldet();
    }

    /** Zaehlt der Besucher als Mitglied? Siehe Zugriff::mitglied(). */
    public function mitglied(): bool
    {
        return $this->zugriff->mitglied();
    }

    /**
     * Eine Sprachmarke in der Sprache der Website. Ohne `languages.supported`
     * ist keine Sprache aktiv und Grav faellt auf Englisch zurueck; dann gilt
     * die Standardsprache der Website, zuletzt Englisch (so haelt es Grav).
     */
    private function marke(string $name): string
    {
        $sprache = $this->grav['language'];
        $code = $sprache->getActive()
            ?: $this->grav['config']->get('system.languages.default_lang')
            ?: $this->grav['config']->get('site.default_lang')
            ?: 'en';

        return (string) $sprache->translate('PLUGIN_CW_VEREIN.' . $name, array_unique([$code, 'en']));
    }

    public function vorstandRecht(): bool
    {
        return $this->zugriff->vorstand();
    }

    /**
     * Dokumente, die der Besucher sehen darf, nach Kategorie gruppiert und
     * innerhalb nach Datum (neueste zuerst).
     *
     * @return array<string, array{titel: string, dokumente: array<int, array<string, mixed>>}>
     */
    public function dokumente(): array
    {
        $kategorien = $this->kategorien();
        $gruppen = [];
        foreach ($this->sammlung('dokumente') as $d) {
            if (!$this->zugriff->erlaubt((string) ($d['zugriff'] ?? 'mitglieder'))) {
                continue;
            }
            $d['url'] = $this->dokumentUrl($d);
            $d['datei_name'] = $this->dateiName($d);
            $d['datei_groesse'] = $this->dateiGroesse($d);
            $d['datei_typ'] = strtoupper((string) pathinfo($d['datei_name'], PATHINFO_EXTENSION));
            $k = (string) ($d['kategorie'] ?: 'sonstiges');
            $gruppen[$k]['titel'] = $kategorien[$k] ?? ucfirst($k);
            $gruppen[$k]['dokumente'][] = $d;
        }
        foreach ($gruppen as &$g) {
            usort($g['dokumente'], static fn(array $a, array $b): int => strcmp((string) ($b['datum'] ?? ''), (string) ($a['datum'] ?? '')) ?: strcmp((string) $a['titel'], (string) $b['titel']));
        }
        unset($g);
        // Reihenfolge der Kategorien wie im Blueprint.
        $sortiert = [];
        foreach ($kategorien as $k => $t) {
            if (isset($gruppen[$k])) {
                $sortiert[$k] = $gruppen[$k];
            }
        }
        return $sortiert + $gruppen;
    }

    /** @return array<string,string> */
    public function kategorien(): array
    {
        $eigene = $this->grav['config']->get('plugins.cw-verein.kategorien');
        if (is_array($eigene) && $eigene !== []) {
            return $eigene;
        }

        // Vorgabe aus den Sprachdateien, nicht fest auf Deutsch: Ein Verein
        // in Lausanne bekaeme sonst «Statuten und Reglemente» auf seiner
        // franzoesischen Seite. Dieselben Marken wie im Blueprint.
        return [
            'statuten'  => $this->marke('KATEGORIE_STATUTEN_OPTION'),
            'gv'        => $this->marke('KATEGORIE_GV_OPTION'),
            'vertraege' => $this->marke('KATEGORIE_VERTRAEGE_OPTION'),
            'plaene'    => $this->marke('KATEGORIE_PLAENE_OPTION'),
            'logo'      => $this->marke('KATEGORIE_LOGO_OPTION'),
            'sonstiges' => $this->marke('KATEGORIE_SONSTIGES_OPTION'),
        ];
    }

    /** Ein Dokument nach Schlüssel (ohne Zugriffsprüfung -- die macht der Aufrufer). */
    public function dokument(string $key): ?array
    {
        foreach ($this->sammlung('dokumente') as $d) {
            if ($d['key'] === $key) {
                return $d;
            }
        }
        return null;
    }

    public function dokumentUrl(array $d): string
    {
        $route = (string) $this->grav['config']->get('plugins.cw-verein.route', '/dokument');
        $name = $this->dateiName($d);
        return rtrim((string) $this->grav['base_url_relative'], '/') . $route . '/' . $d['key'] . ($name !== '' ? '/' . rawurlencode($name) : '');
    }

    /**
     * Absoluter Pfad der hochgeladenen Datei, oder null.
     *
     * Gebaut aus dem EIGENEN Ordner des Dokuments und dem blossen Dateinamen,
     * abgesichert mit realpath(). Bis 1.4.0 wurde der Pfad genommen, wie er
     * im Datensatz stand, und nur darauf geprueft, dass er mit
     * «user/data/cw-verein/» beginnt. Das hatte zwei Fehler: Es haengt davon
     * ab, dass der Admin einen vollen Pfad hineinschreibt -- admin2 legt dort
     * womoeglich nur den nackten Dateinamen ab, und dann fand diese Methode
     * nichts und jedes Dokument meldete «Datei fehlt» --, und es liess ein
     * Dokument auf die Datei eines anderen zeigen (getgrav/grav#4400, Punkt 3).
     *
     * Jetzt zaehlt nur der Dateiname, und er muss im Ordner dieses Dokuments
     * liegen. Beides zusammen macht beide Fragen gegenstandslos.
     */
    public function dateiPfad(array $d): ?string
    {
        $key   = (string) ($d['key'] ?? '');
        $datei = $d['datei'] ?? null;
        if ($key === '' || !is_array($datei) || !$datei) {
            return null;
        }
        $erste = reset($datei);
        $roh = is_array($erste)
            ? (string) ($erste['path'] ?? $erste['name'] ?? array_key_first($datei))
            : (string) array_key_first($datei);

        $name = basename(str_replace('\\', '/', $roh));
        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }

        $ordner = Dokumente::ordner($this->grav) . '/' . $key;
        $echt   = realpath($ordner . '/' . $name);
        $heim   = realpath($ordner);
        if ($echt === false || $heim === false
            || strpos($echt, rtrim($heim, '/\\') . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        return $echt;
    }

    /**
     * Der Name, den ein Besucher zu sehen bekommt: der echte Name aus dem
     * Datensatz, nicht der auf der Platte. Dort traegt die Datei eine Endung,
     * die kein Webserver ausliefert (siehe Dokumente).
     */
    public function dateiName(array $d): string
    {
        $datei = $d['datei'] ?? null;
        if (is_array($datei) && $datei) {
            $erste = reset($datei);
            $name = is_array($erste) ? trim((string) ($erste['name'] ?? '')) : '';
            if ($name !== '') {
                return Dokumente::anzeigeName(basename(str_replace('\\', '/', $name)));
            }
        }
        $pfad = $this->dateiPfad($d);

        return $pfad ? Dokumente::anzeigeName(basename($pfad)) : '';
    }

    /**
     * Der Medientyp aus dem Datensatz. Die Platte taugt dafuer nicht mehr:
     * dort heisst die Datei «…​.bin», und ein Blick auf die Endung ergaebe
     * application/octet-stream. Erkannt wird sonst am Inhalt, was auch geht,
     * aber langsamer ist und bei manchem Format danebenliegt.
     */
    public function dateiMime(array $d): string
    {
        $datei = $d['datei'] ?? null;
        if (is_array($datei) && $datei) {
            $erste = reset($datei);
            $typ = is_array($erste) ? trim((string) ($erste['type'] ?? '')) : '';
            // Nur ein plausibler Typ, und keine Kopfzeile aus zwei Zeilen.
            if ($typ !== '' && preg_match('~^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$~i', $typ)) {
                return $typ;
            }
        }
        $pfad = $this->dateiPfad($d);

        return $pfad && is_file($pfad) ? (mime_content_type($pfad) ?: 'application/octet-stream') : 'application/octet-stream';
    }

    public function dateiGroesse(array $d): int
    {
        $pfad = $this->dateiPfad($d);
        return $pfad && is_file($pfad) ? (int) filesize($pfad) : 0;
    }

    /**
     * Termine ab heute, aufsteigend. Nicht angemeldete Besucher sehen nur
     * öffentliche Termine.
     *
     * @return array<int, array<string, mixed>>
     */
    public function termine(?bool $angemeldet = null, int $limit = 0, bool $vergangene = false): array
    {
        $angemeldet ??= $this->zugriff->mitglied();
        // Die Zeitzone der Website, nicht die unsere: «ab heute» faengt in
        // Lissabon eine Stunde spaeter an als in Zuerich, und bis 1.3.0 stand
        // Europe/Zurich fest im Code (getgrav/grav#4400, Punkt 5).
        $zone = trim((string) $this->grav['config']->get('system.timezone', ''));
        $heute = (new \DateTimeImmutable('today', $zone !== '' ? new \DateTimeZone($zone) : null))->format('Y-m-d');
        $aus = [];
        foreach ($this->sammlung('termine') as $t) {
            if (!$angemeldet && empty($t['oeffentlich'])) {
                continue;
            }
            $datum = substr((string) ($t['datum'] ?? ''), 0, 10);
            if ($datum === '') {
                continue;
            }
            if (!$vergangene && $datum < $heute) {
                continue;
            }
            $t['datum'] = $datum;
            $aus[] = $t;
        }
        usort($aus, static fn(array $a, array $b): int => strcmp($a['datum'], $b['datum']) ?: strcmp((string) ($a['zeit'] ?? ''), (string) ($b['zeit'] ?? '')));
        if ($vergangene) {
            $aus = array_reverse($aus);
        }
        return $limit > 0 ? array_slice($aus, 0, $limit) : $aus;
    }

    /**
     * Mitglieder mit «sichtbar» -- nur für Angemeldete; sonst leer.
     * Vorstand zuerst (Funktion gesetzt), dann alphabetisch nach Name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mitglieder(): array
    {
        if (!$this->zugriff->mitglied()) {
            return [];
        }
        $aus = array_values(array_filter($this->sammlung('mitglieder'), static fn(array $m): bool => !empty($m['sichtbar'])));
        usort($aus, static fn(array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')) ?: strcmp((string) ($a['vorname'] ?? ''), (string) ($b['vorname'] ?? '')));
        return $aus;
    }

    /**
     * Die Gruppen aus der Plugin-Konfiguration, Schlüssel => Titel.
     * Ein Verein gliedert seine Leute unterschiedlich -- Jagdgruppe,
     * Vereinsmitglieder, Gönner --, darum steht die Gliederung nicht im
     * Code, sondern in der Konfiguration.
     *
     * @return array<string, string>
     */
    public function gruppenNamen(): array
    {
        $aus = [];
        foreach ((array) ($this->grav['config']->get('plugins.cw-verein.gruppen') ?: []) as $g) {
            $k = trim((string) ($g['schluessel'] ?? ''));
            if ($k !== '') {
                $aus[$k] = trim((string) ($g['titel'] ?? '')) ?: $k;
            }
        }
        if ($aus) {
            return $aus;
        }

        // Ohne eigene Gliederung: eine Gruppe, benannt aus den Sprachdateien.
        // Bis 1.3.0 standen «Vereinsmitglieder» und «Gönner» als Vorgabe in
        // der Konfiguration und erschienen damit auf der oeffentlichen Seite
        // jeder Website, die das Plugin einschaltet -- auf Deutsch, und mit
        // einer Gliederung, die sich der Verein nicht ausgesucht hat
        // (getgrav/grav#4400, Punkt 5).
        return ['mitglieder' => $this->marke('GRUPPE_MITGLIEDER_OPTION')];
    }

    /**
     * Dasselbe für das Auswahlfeld im Admin (Blueprint, data-options@).
     *
     * @return array<string, string>
     */
    public static function gruppenOptionen(): array
    {
        return (new self(Grav::instance()))->gruppenNamen();
    }

    /**
     * Mitglieder nach Gruppen, in der Reihenfolge der Konfiguration.
     * Wer keine oder eine unbekannte Gruppe hat, landet bei «mitglieder»
     * (sonst in der ersten Gruppe) -- so geht niemand verloren.
     * Leere Gruppen fallen weg.
     *
     * Mit $ohneVorstand bleiben die Vorstandsmitglieder aussen vor: die
     * Seite zeigt sie schon in einem eigenen Block, und niemand soll
     * zweimal auf derselben Seite stehen.
     *
     * @return array<int, array{schluessel: string, titel: string, leute: array<int, array<string, mixed>>}>
     */
    public function gruppen(bool $ohneVorstand = false): array
    {
        $namen = $this->gruppenNamen();
        if (!$namen) {
            return [];
        }
        $auffang = isset($namen['mitglieder']) ? 'mitglieder' : (string) array_key_first($namen);
        $koerbe = array_fill_keys(array_keys($namen), []);
        $raus = [];
        if ($ohneVorstand) {
            foreach ($this->vorstand() as $v) {
                $raus[] = ($v['key'] ?? '') ?: ($v['vorname'] ?? '') . '|' . ($v['name'] ?? '');
            }
        }
        foreach ($this->mitglieder() as $m) {
            if ($raus && in_array(($m['key'] ?? '') ?: ($m['vorname'] ?? '') . '|' . ($m['name'] ?? ''), $raus, true)) {
                continue;
            }
            $g = trim((string) ($m['gruppe'] ?? ''));
            $koerbe[isset($namen[$g]) ? $g : $auffang][] = $m;
        }
        $aus = [];
        foreach ($namen as $k => $titel) {
            if ($koerbe[$k]) {
                $aus[] = ['schluessel' => $k, 'titel' => $titel, 'leute' => $koerbe[$k]];
            }
        }
        return $aus;
    }

    /**
     * Der Vorstand (Mitglieder mit Funktion ausser «Mitglied»), öffentlich.
     *
     * Was ein Verein nach aussen zeigt, ist Name und Funktion. Bis 1.3.0 gab
     * diese Methode den GANZEN Datensatz heraus -- Strasse, Mail, Telefon,
     * Bemerkung, Jagdpatent -- und ueberliess es der Vorlage, das zu
     * verstecken. Eine Vorlage, die es vergisst, stellt damit die
     * Wohnadressen des Vorstands ins Netz, und dem Plugin faellt es nicht auf
     * (getgrav/grav#4400, Punkt 4). Jetzt gibt es diese Angaben gar nicht
     * erst heraus, solange niemand angemeldet ist.
     *
     * @return array<int, array<string, mixed>>
     */
    public function vorstand(): array
    {
        $angemeldet = $this->zugriff->mitglied();
        $reihenfolge = $this->vorstandReihenfolge();
        $aus = [];
        foreach ($this->sammlung('mitglieder') as $m) {
            $f = mb_strtolower(trim((string) ($m['funktion'] ?? '')));
            if ($f === '' || empty($m['sichtbar']) || in_array($f, $this->keinVorstand(), true)) {
                continue;
            }
            // Es gewinnt das GENAUESTE Wort, nicht das erste: «präsident»
            // steckt in «vizepräsident», und wer zuerst trifft, gewinnt, haette
            // den Vize zum Praesidenten gemacht. (Genau das tat die Fassung bis
            // 1.3.0 mit ihrer festen Liste.)
            $m['_rang'] = count($reihenfolge) + 1;
            $laenge = 0;
            foreach ($reihenfolge as $i => $wort) {
                if (str_contains($f, $wort) && mb_strlen($wort) > $laenge) {
                    $laenge = mb_strlen($wort);
                    $m['_rang'] = $i + 1;
                }
            }
            $aus[] = $angemeldet ? $m : self::nurOeffentlich($m);
        }
        usort($aus, static fn(array $a, array $b): int => $a['_rang'] <=> $b['_rang']
            ?: strcmp((string) ($a['funktion'] ?? ''), (string) ($b['funktion'] ?? ''))
            ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $aus;
    }

    /**
     * Die Felder, die ein nicht angemeldeter Besucher vom Vorstand sieht.
     * Bewusst kurz: Was darueber hinaus gezeigt werden soll, gehoert in eine
     * eigene, oeffentliche Angabe und nicht in den Mitgliederdatensatz.
     *
     * @param array<string, mixed> $m
     * @return array<string, mixed>
     */
    private static function nurOeffentlich(array $m): array
    {
        return array_intersect_key($m, array_flip(['key', 'vorname', 'name', 'funktion', 'gruppe', '_rang']));
    }

    /**
     * Die Reihenfolge der Vorstandsfunktionen, in Kleinschreibung.
     *
     * Bis 1.3.0 standen hier deutsche Woerter fest im Code («präsident»,
     * «kassier», «sekretär»); auf einer franzoesischen oder italienischen
     * Vereinsseite griff davon keines, und der Vorstand stand alphabetisch da.
     * Ohne Angabe wird jetzt nach Funktion und Name sortiert -- das ist
     * sprachlos richtig, wenn auch nicht hierarchisch.
     *
     * @return string[]
     */
    private function vorstandReihenfolge(): array
    {
        $roh = $this->grav['config']->get('plugins.cw-verein.vorstand_reihenfolge');
        $teile = is_array($roh) ? $roh : preg_split('/\s*,\s*/', (string) $roh);

        return array_values(array_filter(
            array_map(static fn ($w): string => mb_strtolower(trim((string) $w)), (array) $teile),
            'strlen'
        ));
    }

    /**
     * Funktionsbezeichnungen, die KEINEN Vorstandsposten bedeuten. «Mitglied»
     * stand fest im Code; in vier Sprachen steht es in den Sprachdateien.
     *
     * @return string[]
     */
    private function keinVorstand(): array
    {
        $roh = $this->grav['config']->get('plugins.cw-verein.kein_vorstand')
            ?: $this->marke('FUNKTION_MITGLIED_WORT');
        $teile = is_array($roh) ? $roh : preg_split('/\s*,\s*/', (string) $roh);

        return array_values(array_filter(
            array_map(static fn ($w): string => mb_strtolower(trim((string) $w)), (array) $teile),
            'strlen'
        ));
    }
}
