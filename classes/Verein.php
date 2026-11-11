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
        $sprache = $this->grav['language'];
        $code = $sprache->getActive()
            ?: $this->grav['config']->get('system.languages.default_lang')
            ?: $this->grav['config']->get('site.default_lang')
            ?: 'en';
        $sprachen = array_unique([$code, 'en']);
        $marke = static fn (string $m): string => (string) $sprache->translate(
            'PLUGIN_CW_VEREIN.' . $m,
            $sprachen
        );

        return [
            'statuten'  => $marke('KATEGORIE_STATUTEN_OPTION'),
            'gv'        => $marke('KATEGORIE_GV_OPTION'),
            'vertraege' => $marke('KATEGORIE_VERTRAEGE_OPTION'),
            'plaene'    => $marke('KATEGORIE_PLAENE_OPTION'),
            'logo'      => $marke('KATEGORIE_LOGO_OPTION'),
            'sonstiges' => $marke('KATEGORIE_SONSTIGES_OPTION'),
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

    /** Absoluter Pfad der hochgeladenen Datei, oder null. */
    public function dateiPfad(array $d): ?string
    {
        $datei = $d['datei'] ?? null;
        if (!is_array($datei) || !$datei) {
            return null;
        }
        $erste = reset($datei);
        $rel = is_array($erste) ? (string) ($erste['path'] ?? array_key_first($datei)) : (string) array_key_first($datei);
        $rel = str_replace('\\', '/', $rel);
        // Nur innerhalb von user/data/cw-verein -- nichts anderes ausliefern.
        if (!str_starts_with($rel, 'user/data/cw-verein/') || str_contains($rel, '..')) {
            return null;
        }
        return rtrim(GRAV_ROOT, '/\\') . '/' . $rel;
    }

    public function dateiName(array $d): string
    {
        $pfad = $this->dateiPfad($d);
        return $pfad ? basename($pfad) : '';
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
        $angemeldet ??= $this->zugriff->angemeldet();
        $heute = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Zurich')))->format('Y-m-d');
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
        if (!$this->zugriff->angemeldet()) {
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
        return $aus;
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
     * Der Vorstand (Mitglieder mit Funktion ausser «Mitglied»), öffentlich --
     * Name und Funktion sind das, was ein Verein nach aussen zeigt; Kontakt
     * nur für Angemeldete (das entscheidet die Vorlage).
     *
     * @return array<int, array<string, mixed>>
     */
    public function vorstand(): array
    {
        $rang = ['präsident' => 1, 'praesident' => 1, 'vizepräsident' => 2, 'kassier' => 3, 'sekretär' => 4, 'sekretar' => 4, 'hegeobmann' => 5, 'revisor' => 8];
        $aus = [];
        foreach ($this->sammlung('mitglieder') as $m) {
            $f = mb_strtolower(trim((string) ($m['funktion'] ?? '')));
            if ($f === '' || $f === 'mitglied' || empty($m['sichtbar'])) {
                continue;
            }
            $m['_rang'] = 9;
            foreach ($rang as $wort => $r) {
                if (str_contains($f, $wort)) {
                    $m['_rang'] = min($m['_rang'], $r);
                }
            }
            $aus[] = $m;
        }
        usort($aus, static fn(array $a, array $b): int => $a['_rang'] <=> $b['_rang'] ?: strcmp((string) $a['name'], (string) $b['name']));
        return $aus;
    }
}
