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
            // Die hochgeladene Datei so benennen, dass kein Webserver sie
            // ausliefert -- zweimal, weil Upload und Speichern in Admin 2.0
            // zwei getrennte Schritte sind. Der erste Haken schliesst das
            // Zeitfenster zwischen beiden, der zweite richtet danach den
            // Datensatz aus.
            'onAdminAfterAddMedia'   => ['onDokumentBeruehrt', 0],
            'onFlexObjectAfterSave'  => ['onDokumentBeruehrt', 0],
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

    /**
     * Ein Dokument wurde beruehrt (Upload oder Speichern): die Datei so
     * benennen, dass kein Webserver sie ausliefert, und den Datensatz
     * darauf ausrichten.
     *
     * Hier und nicht vorher: Grav legt die Datei erst in saveUpdatedMedia()
     * ab, und das laeuft nach onBeforeSave. Zu diesem Zeitpunkt liegt sie
     * also auf der Platte, unter ihrem Originalnamen -- und damit einen
     * Augenblick lang offen, wenn es ein PDF oder ein Bild ist. Lang ist
     * dieser Augenblick nicht, aber er ist der Grund, warum das Umbenennen
     * hier geschieht und nicht in einem naechtlichen Lauf.
     *
     * Geschrieben wird direkt in item.json, nicht ueber $objekt->save() --
     * das riefe dieses Ereignis erneut auf.
     */
    public function onDokumentBeruehrt(Event $event): void
    {
        $objekt = $event['object'] ?? null;
        if (!is_object($objekt) || !method_exists($objekt, 'getFlexType')
            || $objekt->getFlexType() !== 'dokumente') {
            return;
        }
        try {
            $key = method_exists($objekt, 'getKey') ? (string) $objekt->getKey() : '';
            if ($key === '') {
                return;
            }
            $tat = CwVerein\Dokumente::absichern($key, true, $this->grav);
            if ($tat) {
                $this->grav['log']->info(sprintf(
                    'cw-verein: document "%s" stored as "%s" so that no web server serves it directly',
                    $tat['alt'],
                    $tat['neu']
                ));
            }
        } catch (\Throwable $e) {
            $this->grav['log']->error('cw-verein: ' . $e->getMessage());
        }
    }

    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig_vars['verein'] = new CwVerein\Verein($this->grav);
    }

    /**
     * /dokument/<key>[/<dateiname>] -- Datei eines Dokuments ausliefern,
     * nach Zugriffspruefung.
     *
     * JEDE Verweigerung antwortet gleich: 404 mit demselben Satz. Bis 1.3.0
     * waren es drei verschiedene Antworten -- 404 fuer einen unbekannten
     * Schluessel, 302 auf die Anmeldung fuer einen abgemeldeten Besucher, 403
     * fuer ein angemeldetes Nicht-Vorstandsmitglied. Damit verriet die Route
     * zweierlei: dass es diesen Schluessel gibt, und welche Stufe er hat. Und
     * sie widersprach dem README, dessen Versprechen «keine Weiterleitung»
     * das Erste ist, was man darueber liest (getgrav/grav#4400, Punkt 2).
     *
     * Der Satz nennt die Anmeldung, damit ein Mitglied weiss, was zu tun ist;
     * weil er in beiden Faellen derselbe ist, verraet er trotzdem nichts.
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
            $this->antwort(404, $this->text('DOKUMENT_NICHT_GEFUNDEN'));
        }
        $datei = $verein->dateiPfad($dok);
        if ($datei === null || !is_file($datei)) {
            // Hierher kommt nur, wer das Dokument sehen duerfte -- diese
            // Meldung verraet also nichts, und sie ist die einzige, die dem
            // Vorstand sagt, dass eine Datei fehlt.
            $this->antwort(404, $this->text('DATEI_FEHLT'));
        }
        // Der NAME aus dem Datensatz, nicht der von der Platte: dort traegt
        // die Datei eine Endung, die kein Webserver ausliefert.
        $name = $verein->dateiName($dok) ?: CwVerein\Dokumente::anzeigeName(basename($datei));
        $mime = $verein->dateiMime($dok);
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($datei));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; ' . self::dateiAngabe($name));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('X-Robots-Tag: noindex, nofollow');
        // Ohne das raet der Browser bei einem falsch hinterlegten Typ selbst,
        // und ein geratenes text/html waere eine Seite im Namen der Website.
        header('X-Content-Type-Options: nosniff');
        readfile($datei);
        exit;
    }

    /**
     * Der Dateiname fuer Content-Disposition nach RFC 6266: eine reine
     * ASCII-Fassung fuer alle, und daneben der echte Name als filename*.
     * Vorher stand der Name roh zwischen Anfuehrungszeichen -- ein Umlaut
     * kam als Buchstabensalat an, und ein Anfuehrungszeichen im Namen haette
     * die Kopfzeile zerrissen.
     */
    private static function dateiAngabe(string $name): string
    {
        // Je Zeichen ein Unterstrich, nicht je Byte: «Grüsse» soll
        // «Gr_sse» werden und nicht «Gr__sse».
        $ascii = (string) preg_replace('/[^\x20-\x7E]+/u', '_', $name);
        $ascii = str_replace(['"', '\\'], '_', $ascii);
        if (trim($ascii, '_ ') === '') {
            $ascii = 'download';   // ein Name ganz ohne ASCII-Zeichen
        }

        return sprintf('filename="%s"; filename*=UTF-8\'\'%s', $ascii, rawurlencode($name));
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
