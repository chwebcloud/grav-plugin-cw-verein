<?php
declare(strict_types=1);

namespace Grav\Plugin\CwVerein;

use Grav\Common\Grav;

/**
 * Wo die hochgeladene Datei eines Dokuments liegt -- und warum sie dort
 * heisst, wie sie heisst.
 *
 * DAS PROBLEM: Gravs eigene Serverregeln liefern unterhalb von user/data eine
 * feste Liste von Medientypen direkt aus, ohne Grav zu starten -- unter
 * anderem PDF, JPEG, PNG und WebP (Wurzel-.htaccess, und ebenso die
 * nginx-Vorlage). Ein Vereinsdokument lag damit unter seiner direkten Adresse
 * offen, egal welche Zugriffsstufe eingestellt war, und weder das
 * Zurueckziehen noch eine strengere Stufe nahm das zurueck
 * (getgrav/grav#4400, Punkt 1).
 *
 * WAS NICHT HILFT, gemessen und verworfen:
 *   - Eine eigene .htaccess: Grav schreibt diese Dateien bei jedem Kernupdate
 *     neu, und nginx liest sie gar nicht.
 *   - Ein Punkt-Ordner (.dateien/): Apache sperrt den ganzen Pfad, die
 *     nginx-Vorlage sieht aber nur die letzte Endung und liefert das PDF aus.
 *   - Ein anderer Ordner unter user/: Es gibt keine Auffangregel dafuer.
 *     Gesperrt sind namentlich nur config, env, accounts und data.
 *
 * WAS HILFT: Die Regeln sind Erlaubnislisten -- was nicht daraufsteht, wird
 * verweigert. Die abgelegte Datei bekommt darum eine Endung, die auf keiner
 * der Listen steht. Apache und nginx verweigern sie beide, auf jedem Hosting,
 * ohne eine Zeile Serverkonfiguration. Der echte Name und der Medientyp
 * stehen im Datensatz; ausgeliefert wird ohnehin nur ueber /dokument/<key>
 * nach Zugriffspruefung.
 */
final class Dokumente
{
    /**
     * Die Endung, die kein Webserver ausliefert. Angehaengt, nicht ersetzt:
     * «statuten.pdf.bin» sagt einem Menschen im Dateimanager noch, was es ist.
     */
    public const SPERRE = '.bin';

    /** Der Ordner, in dem die Dokumente liegen (ohne Schluessel). */
    public static function ordner(?Grav $grav = null): string
    {
        $grav ??= Grav::instance();
        $pfad = $grav['locator']->findResource('user-data://cw-verein/dokumente', true);

        return is_string($pfad) ? rtrim(str_replace('\\', '/', $pfad), '/') : '';
    }

    /** Traegt der Name schon die Sperre? */
    public static function gesperrt(string $name): bool
    {
        return substr($name, -strlen(self::SPERRE)) === self::SPERRE;
    }

    /** Der Name, unter dem abgelegt wird. */
    public static function sicherName(string $name): string
    {
        return self::gesperrt($name) ? $name : $name . self::SPERRE;
    }

    /** Der Name, den ein Besucher zu sehen bekommt. */
    public static function anzeigeName(string $name): string
    {
        return self::gesperrt($name) ? substr($name, 0, -strlen(self::SPERRE)) : $name;
    }

    /**
     * Ein Dokument absichern: jede Datei im Ordner umbenennen, die ein
     * Webserver ausliefern wuerde, und den Datensatz darauf ausrichten.
     *
     * Warum ueber den ganzen Ordner und nicht nur ueber den Datensatz: Upload
     * und Speichern sind in Admin 2.0 zwei getrennte Schritte. Die Datei
     * liegt schon da, wenn der Datensatz sie noch nicht kennt -- und
     * umgekehrt nennt der Datensatz nach dem Speichern einen Namen, den es
     * auf der Platte nicht mehr gibt, weil der Upload-Haken sie bereits
     * umbenannt hat. Beides muss hier zusammenfinden, in jeder Reihenfolge
     * und beliebig oft.
     *
     * Gibt zurueck, was geschehen ist -- oder null, wenn nichts zu tun war.
     *
     * @param string $key       Schluessel des Dokuments (= Ordnername)
     * @param bool   $schreiben false: nur sagen, was geschaehe
     * @return array{alt:string,neu:string}|null
     */
    public static function absichern(string $key, bool $schreiben = true, ?Grav $grav = null): ?array
    {
        $grav ??= Grav::instance();
        $ordner = self::ordner($grav) . '/' . $key;
        $json   = $ordner . '/item.json';
        if (!is_dir($ordner)) {
            return null;
        }

        // PHP merkt sich Dateizustaende. Hier wird im selben Lauf umbenannt
        // und danach gefragt, ob die neue Datei da ist -- ohne das kaeme die
        // Antwort aus dem Gedaechtnis von vorher.
        clearstatcache();

        // 1. Alles umbenennen, was offen laege.
        $erste = null;
        foreach ((array) scandir($ordner) as $e) {
            if ($e === '.' || $e === '..' || $e === 'item.json' || self::gesperrt($e)
                || !is_file("$ordner/$e")) {
                continue;
            }
            $neu = self::sicherName($e);
            if ($schreiben && !@rename("$ordner/$e", "$ordner/$neu")) {
                throw new \RuntimeException("$key: \"$e\" konnte nicht umbenannt werden");
            }
            $erste ??= ['alt' => $e, 'neu' => $neu];
        }

        // 2. Den Datensatz darauf ausrichten -- auch dann, wenn Schritt 1
        //    nichts zu tun hatte: Nach einem Speichern steht dort womoeglich
        //    der Name von vor dem Umbenennen.
        $geaendert = $schreiben ? self::datensatzRichten($key, $ordner, $json, $grav) : false;

        if ($erste !== null) {
            return $erste;
        }

        return $geaendert ? ['alt' => '(Datensatz)', 'neu' => '(nachgezogen)'] : null;
    }

    /**
     * Den Datensatz auf die Datei ausrichten, die wirklich dort liegt.
     * Der echte Name (`name`) bleibt unangetastet -- er ist das, was der
     * Besucher zu sehen bekommt.
     */
    private static function datensatzRichten(string $key, string $ordner, string $json, Grav $grav): bool
    {
        if (!is_file($json)) {
            return false;
        }
        $daten = json_decode((string) file_get_contents($json), true);
        if (!is_array($daten) || !is_array($daten['datei'] ?? null) || !$daten['datei']) {
            return false;
        }

        $schluessel = (string) array_key_first($daten['datei']);
        $eintrag    = is_array($daten['datei'][$schluessel]) ? $daten['datei'][$schluessel] : [];

        $roh  = (string) ($eintrag['path'] ?? $eintrag['name'] ?? $schluessel);
        $name = basename(str_replace('\\', '/', $roh));
        if ($name === '' || $name === '.' || $name === '..') {
            return false;
        }
        $soll = self::sicherName($name);
        if (!is_file("$ordner/$soll")) {
            // Die Datei liegt nicht da -- vielleicht nur auf dem Server. Dann
            // wird hier nichts umgeschrieben.
            return false;
        }

        $relativ = self::relativ($ordner, $grav) . '/' . $soll;
        if ($schluessel === $relativ && ($eintrag['path'] ?? null) === $relativ) {
            return false;   // steht schon richtig
        }

        $eintrag['path'] = $relativ;
        $eintrag['name'] = self::anzeigeName((string) ($eintrag['name'] ?? $name));
        $daten['datei'] = [$relativ => $eintrag];

        $temp = $json . '.unfertig';
        $text = json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($text === false || @file_put_contents($temp, $text) === false || !@rename($temp, $json)) {
            @unlink($temp);
            throw new \RuntimeException("$key: item.json konnte nicht geschrieben werden");
        }

        return true;
    }

    /** Der Ordner als Pfad relativ zur Grav-Wurzel, wie ihn der Datensatz fuehrt. */
    private static function relativ(string $ordner, Grav $grav): string
    {
        $wurzel = rtrim(str_replace('\\', '/', GRAV_ROOT), '/');
        $ordner = str_replace('\\', '/', $ordner);

        return strpos($ordner, $wurzel . '/') === 0 ? substr($ordner, strlen($wurzel) + 1) : $ordner;
    }

    /** Alle Schluessel, die es im Dokumentordner gibt. @return string[] */
    public static function alleSchluessel(?Grav $grav = null): array
    {
        $ordner = self::ordner($grav);
        if ($ordner === '' || !is_dir($ordner)) {
            return [];
        }
        $aus = [];
        foreach ((array) scandir($ordner) as $e) {
            if ($e !== '.' && $e !== '..' && is_file("$ordner/$e/item.json")) {
                $aus[] = $e;
            }
        }
        sort($aus);

        return $aus;
    }
}
