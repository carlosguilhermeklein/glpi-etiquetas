<?php

/**
 * Compiles gettext .po files into .mo files, without msgfmt.
 *
 * Usage: php tools/compile-mo.php            (all locales/*.po)
 *        php tools/compile-mo.php locales/pt_BR.po
 *
 * @copyright 2026 cgklein
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$files = array_slice($argv, 1) ?: glob(dirname(__DIR__) . '/locales/*.po');
foreach ($files as $po) {
    $entries = parse_po($po);
    $mo      = preg_replace('/\.po$/', '.mo', $po);
    file_put_contents($mo, build_mo($entries));
    printf("%s: %d messages -> %s\n", basename($po), count($entries) - 1, basename($mo));
}

/** @return array<string, string> original => translation (plural forms joined with NUL) */
function parse_po(string $file): array
{
    $entries = [];
    $entry   = [];
    $last    = null;

    $flush = static function () use (&$entries, &$entry, &$last): void {
        if (isset($entry['msgid'])) {
            $key = (isset($entry['msgctxt']) ? $entry['msgctxt'] . "\x04" : '') . $entry['msgid'];
            if (isset($entry['msgid_plural'])) {
                $key .= "\0" . $entry['msgid_plural'];
                $forms = [];
                for ($i = 0; isset($entry["msgstr[$i]"]); $i++) {
                    $forms[] = $entry["msgstr[$i]"];
                }
                $translation = implode("\0", $forms);
            } else {
                $translation = $entry['msgstr'] ?? '';
            }
            // Untranslated messages are left out (except the header, msgid "")
            if ($entry['msgid'] === '' || str_replace("\0", '', $translation) !== '') {
                $entries[$key] = $translation;
            }
        }
        $entry = [];
        $last  = null;
    };

    $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($file));
    foreach (preg_split('/\R/', $content) as $line) {
        $line = trim($line);
        if ($line === '') {
            $flush();
            continue;
        }
        if ($line[0] === '#') {
            continue;
        }
        if (preg_match('/^(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s+"(.*)"$/', $line, $match)) {
            // A new message may start without a blank line before it
            $starts_message = in_array($match[1], ['msgid', 'msgctxt'], true);
            if ($starts_message && (isset($entry['msgstr']) || isset($entry['msgstr[0]']))) {
                $flush();
            }
            $last = $match[1];
            $entry[$last] = stripcslashes($match[2]);
        } elseif ($last !== null && preg_match('/^"(.*)"$/', $line, $match)) {
            $entry[$last] .= stripcslashes($match[1]);
        }
    }
    $flush();

    ksort($entries, SORT_STRING);
    return $entries;
}

/** GNU .mo format, little endian, without hash table */
function build_mo(array $entries): string
{
    $count      = count($entries);
    $originals  = '';
    $translated = '';
    $orig_table = [];
    $trans_table = [];

    foreach ($entries as $original => $translation) {
        $orig_table[] = [strlen($original), strlen($originals)];
        $originals   .= $original . "\0";
        $trans_table[] = [strlen($translation), strlen($translated)];
        $translated   .= $translation . "\0";
    }

    $orig_offset  = 28;
    $trans_offset = $orig_offset + $count * 8;
    $data_offset  = $trans_offset + $count * 8;

    $mo = pack('V7', 0x950412de, 0, $count, $orig_offset, $trans_offset, 0, $data_offset);
    foreach ($orig_table as [$length, $offset]) {
        $mo .= pack('V2', $length, $data_offset + $offset);
    }
    $translated_base = $data_offset + strlen($originals);
    foreach ($trans_table as [$length, $offset]) {
        $mo .= pack('V2', $length, $translated_base + $offset);
    }
    return $mo . $originals . $translated;
}
