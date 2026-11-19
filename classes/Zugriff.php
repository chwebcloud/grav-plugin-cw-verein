<?php
declare(strict_types=1);

namespace Grav\Plugin\CwVerein;

use Grav\Common\Grav;

/**
 * Wer darf was sehen. Drei Stufen: oeffentlich, mitglieder, vorstand.
 */
final class Zugriff
{
    private Grav $grav;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    /** Ist ueberhaupt jemand angemeldet? */
    public function angemeldet(): bool
    {
        $user = $this->grav['user'] ?? null;
        return $user && $user->authenticated && $user->authorized;
    }

    /**
     * Zaehlt der Besucher als Mitglied?
     *
     * Ohne `mitglieder_gruppe` ist das jeder Angemeldete -- so war es bis
     * 1.3.0 ohne Alternative, und auf einer Website mit offener Registrierung
     * durfte damit jeder das Protokoll der Generalversammlung lesen
     * (getgrav/grav#4400). Wer eine Gruppe eintraegt, bekommt die engere
     * Lesart; der Vorstand zaehlt immer dazu.
     */
    public function mitglied(): bool
    {
        if (!$this->angemeldet()) {
            return false;
        }
        $gruppe = trim((string) $this->grav['config']->get('plugins.cw-verein.mitglieder_gruppe', ''));
        if ($gruppe === '') {
            return true;
        }
        return $this->inGruppe($gruppe) || $this->vorstand();
    }

    public function vorstand(): bool
    {
        if (!$this->angemeldet()) {
            return false;
        }
        $gruppe = trim((string) $this->grav['config']->get('plugins.cw-verein.vorstand_gruppe', 'committee'));
        if ($gruppe !== '' && $this->inGruppe($gruppe)) {
            return true;
        }
        $user = $this->grav['user'];
        return (bool) ($user->authorize('admin.super') || $user->authorize('site.vorstand'));
    }

    private function inGruppe(string $gruppe): bool
    {
        $user = $this->grav['user'] ?? null;
        if (!$user) {
            return false;
        }
        return in_array($gruppe, (array) ($user->get('groups') ?? []), true);
    }

    public function erlaubt(string $stufe): bool
    {
        return match ($stufe) {
            'oeffentlich' => true,
            'vorstand'    => $this->vorstand(),
            default       => $this->mitglied(),
        };
    }
}
