<?php
declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Events\FlexRegisterEvent;
use RocketTheme\Toolbox\Event\Event;

/**
 * CHWEB-Verein -- Mitglieder, Dokumente und Termine eines Vereins als
 * Flex-Verzeichnisse, gepflegt im Grav-Admin (admin2), gezeigt vom Theme.
 *
 * Dokumente liegen unter user/data/cw-verein/dokumente/<key>/ und sind
 * NICHT direkt abrufbar (.htaccess/Caddy sperren den Ordner). Der Abruf
 * läuft über /dokument/<key>, und dort wird der Zugriff geprüft:
 * öffentlich, Mitglieder (angemeldet) oder Vorstand (Gruppe «Vorstand»).
 */
class CwVereinPlugin extends Plugin
{
    /** @var array<string,int> */
    public $features = ['blueprints' => 1000];

    private const FLEX_TYPES = ['mitglieder', 'dokumente', 'termine'];
    /** Optional (Config wildkameras: true): Kameraverzeichnis eines Jagdvereins. */
    private const FLEX_OPTIONAL = ['wildkameras'];

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized'   => ['onPluginsInitialized', 0],
            FlexRegisterEvent::class => ['onRegisterFlex', 100],
        ];
    }

    public function onPluginsInitialized(): void
    {
        // Klassen des Plugins ohne Composer: zwei Dateien, ein Namespace.
        spl_autoload_register(static function (string $klasse): void {
            $prefix = 'Grav\\Plugin\\CwVerein\\';
            if (str_starts_with($klasse, $prefix)) {
                $datei = __DIR__ . '/classes/' . substr($klasse, strlen($prefix)) . '.php';
                if (is_file($datei)) {
                    require_once $datei;
                }
            }
        });

        if ($this->isAdmin()) {
            return;
        }
        $this->enable([
            'onTwigInitialized'   => ['onTwigInitialized', 0],
            'onPagesInitialized'  => ['onPagesInitialized', 0],
        ]);
    }

    public function onRegisterFlex(FlexRegisterEvent $event): void
    {
        $typen = self::FLEX_TYPES;
        foreach (self::FLEX_OPTIONAL as $opt) {
            if ($this->config->get('plugins.cw-verein.' . $opt)) {
                $typen[] = $opt;
            }
        }
        foreach ($typen as $type) {
            $event->flex->addDirectoryType($type, "plugins://cw-verein/blueprints/flex-objects/{$type}.yaml");
        }
    }

    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig_vars['verein'] = new CwVerein\Verein($this->grav);
    }

    /**
     * /dokument/<key>[/<dateiname>] -- Datei eines Dokuments ausliefern,
     * nach Zugriffsprüfung. Unbekannt oder nicht erlaubt: 404 bzw. 403.
     */
    public function onPagesInitialized(): void
    {
        $route = (string) $this->config->get('plugins.cw-verein.route', '/dokument');
        $pfad = rtrim((string) $this->grav['uri']->path(), '/');
        if ($pfad !== $route && !str_starts_with($pfad, $route . '/')) {
            return;
        }
        $rest = trim(substr($pfad, strlen($route)), '/');
        $key = explode('/', $rest)[0] ?? '';
        if (!preg_match('/^[a-z0-9]{8,64}$/i', $key)) {
            $this->antwort(404, $this->text('DOKUMENT_NICHT_GEFUNDEN'));
        }
        $verein = new CwVerein\Verein($this->grav);
        $dok = $verein->dokument($key);
        if (!$dok) {
            $this->antwort(404, $this->text('DOKUMENT_NICHT_GEFUNDEN'));
        }
        $zugriff = new CwVerein\Zugriff($this->grav);
        if (!$zugriff->erlaubt((string) ($dok['zugriff'] ?? 'mitglieder'))) {
            if (!$zugriff->angemeldet()) {
                $this->grav->redirect((string) $this->config->get('plugins.login.route', '/login'), 302);
            }
            $this->antwort(403, $this->text('NUR_VORSTAND'));
        }
        $datei = $verein->dateiPfad($dok);
        if ($datei === null || !is_file($datei)) {
            $this->antwort(404, $this->text('DATEI_FEHLT'));
        }
        $name = basename($datei);
        $mime = $dok['datei_mime'] ?? (mime_content_type($datei) ?: 'application/octet-stream');
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($datei));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('X-Robots-Tag: noindex, nofollow');
        readfile($datei);
        exit;
    }

    /**
     * Meldung in der Sprache der Website. Diese drei Saetze liest ein
     * BESUCHER -- bis 1.3.0 standen sie fest auf Deutsch im Code, auch auf
     * einer franzoesischen Vereinsseite. Ohne `languages.supported` ist keine
     * Sprache aktiv und Grav faellt auf Englisch zurueck; dann gilt die
     * Standardsprache der Website, zuletzt Englisch (so haelt es Grav selbst).
     */
    private function text(string $schluessel): string
    {
        $sprache = $this->grav['language'];
        $code = $sprache->getActive()
            ?: $this->grav['config']->get('system.languages.default_lang')
            ?: $this->grav['config']->get('site.default_lang')
            ?: 'en';

        return (string) $sprache->translate('PLUGIN_CW_VEREIN.' . $schluessel, array_unique([$code, 'en']));
    }

    private function antwort(int $code, string $text): never
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $text;
        exit;
    }
}
