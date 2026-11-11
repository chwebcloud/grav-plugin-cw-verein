<?php
declare(strict_types=1);

namespace Grav\Plugin\CwVerein;

use Grav\Common\Grav;

/**
 * Wer darf was sehen. Drei Stufen: oeffentlich, mitglieder (angemeldet und
 * für die Website berechtigt), vorstand (Gruppe «Vorstand» oder Super-Admin).
 */
final class Zugriff
{
    private Grav $grav;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    public function angemeldet(): bool
    {
        $user = $this->grav['user'] ?? null;
        return $user && $user->authenticated && $user->authorized;
    }

    public function vorstand(): bool
    {
        if (!$this->angemeldet()) {
            return false;
        }
        $user = $this->grav['user'];
        $gruppe = (string) $this->grav['config']->get('plugins.cw-verein.vorstand_gruppe', 'Vorstand');
        $gruppen = (array) ($user->get('groups') ?? []);
        if (in_array($gruppe, $gruppen, true)) {
            return true;
        }
        return (bool) ($user->authorize('admin.super') || $user->authorize('site.vorstand'));
    }

    public function erlaubt(string $stufe): bool
    {
        return match ($stufe) {
            'oeffentlich' => true,
            'vorstand'    => $this->vorstand(),
            default       => $this->angemeldet(),
        };
    }
}
