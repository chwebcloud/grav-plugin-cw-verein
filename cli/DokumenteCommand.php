<?php

/**
 * Bestehende Vereinsdokumente so ablegen, dass kein Webserver sie direkt
 * ausliefert.
 *
 *   bin/plugin cw-verein dokumente              # zeigt nur, was geschaehe
 *   bin/plugin cw-verein dokumente --schreiben  # benennt um
 *
 * Wiederholbar: Ein bereits abgesichertes Dokument wird uebersprungen. Der
 * Befehl muss ZWEIMAL laufen -- einmal in der Werkstatt und einmal auf dem
 * Server. user/data gehoert dem Server; ein Lauf hier aendert nur die Kopie,
 * waehrend der Code mit dem naechsten Ausrollen live ist.
 */

namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Grav\Plugin\CwVerein\Dokumente;
use Symfony\Component\Console\Input\InputOption;

require_once __DIR__ . '/../classes/Dokumente.php';

class DokumenteCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this
            ->setName('dokumente')
            ->setDescription('Store the club documents under a name no web server serves (dry run unless --schreiben)')
            ->addOption('schreiben', 's', InputOption::VALUE_NONE, 'Actually rename (without it: show only)');
    }

    protected function serve(): int
    {
        $grav = Grav::instance();
        $grav['config']->init();

        $schreiben = (bool) $this->input->getOption('schreiben');
        $ordner = Dokumente::ordner($grav);
        if ($ordner === '' || !is_dir($ordner)) {
            $this->output->writeln('<error>No document folder at user/data/cw-verein/dokumente.</error>');
            return 1;
        }
        $this->output->writeln('Folder: ' . $ordner);
        $this->output->writeln($schreiben ? '<info>Renaming.</info>' : '<comment>Dry run -- nothing is changed. Add --schreiben.</comment>');
        $this->output->writeln('');

        $getan = 0;
        $schon = 0;
        $fehler = 0;
        foreach (Dokumente::alleSchluessel($grav) as $key) {
            try {
                $tat = Dokumente::absichern($key, $schreiben, $grav);
            } catch (\Throwable $e) {
                $this->output->writeln('<error>FAILED  ' . $key . ': ' . $e->getMessage() . '</error>');
                $fehler++;
                continue;
            }
            if ($tat === null) {
                $schon++;
                continue;
            }
            $this->output->writeln(sprintf('%s  %s  %s -> %s', $schreiben ? '<info>done</info>' : 'would', $key, $tat['alt'], $tat['neu']));
            $getan++;
        }

        $this->output->writeln('');
        $this->output->writeln(sprintf(
            '%d %s, %d already stored safely, %d failed.',
            $getan,
            $schreiben ? 'renamed' : 'would be renamed',
            $schon,
            $fehler
        ));
        if (!$schreiben && $getan > 0) {
            $this->output->writeln('<comment>Run again with --schreiben to apply.</comment>');
        }
        if ($schreiben && $getan > 0) {
            $this->output->writeln('<comment>Remember: user/data belongs to the server. Run this there too.</comment>');
        }

        return $fehler > 0 ? 1 : 0;
    }
}
