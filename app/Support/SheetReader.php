<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Reads the first sheet of an Excel file (.xlsx) or a CSV file into rows of text, without any
 * library. Dates kept by Excel as numbers stay numbers here; App\Services\PatientImport turns them
 * into dates when the column is a date.
 */
class SheetReader
{
    /** @return list<list<string>> */
    public static function read(string $path, string $name, int $maxRows = 5000): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx' => self::xlsx($path, $maxRows),
            'csv', 'txt' => self::csv($path, $maxRows),
            default => throw new RuntimeException(__('Choose an Excel file (.xlsx) or a CSV file.')),
        };
    }

    private static function csv(string $path, int $maxRows): array
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);                 // the mark Excel puts in front
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1256');      // an old Arabic-script file
        }
        $first = strtok($text, "\n") ?: '';
        $sep = collect([',', ';', "\t"])->sortByDesc(fn ($s) => substr_count($first, $s))->first();
        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $sep)) !== false && count($rows) <= $maxRows) {
            if ($row !== [null]) {
                $rows[] = array_map(fn ($c) => trim((string) $c), $row);
            }
        }
        fclose($handle);

        return $rows;
    }

    private static function xlsx(string $path, int $maxRows): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException(__('The file could not be opened as an Excel file.'));
        }
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
            foreach ($doc->si as $si) {
                $strings[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }
        // the first sheet in the order of the workbook
        $sheet = 'xl/worksheets/sheet1.xml';
        if (($wb = $zip->getFromName('xl/workbook.xml')) !== false && ($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
            $wbDoc = simplexml_load_string($wb, 'SimpleXMLElement', LIBXML_NONET);
            $first = $wbDoc->sheets->sheet[0] ?? null;
            $rid = $first ? (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : null;
            foreach (simplexml_load_string($rels, 'SimpleXMLElement', LIBXML_NONET)->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');
                    $sheet = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }
        $xml = $zip->getFromName($sheet);
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException(__('The file has no sheet.'));
        }
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        $rows = [];
        foreach ($doc->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::columnIndex(preg_replace('/\d+/', '', (string) $c['r']));
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $strings[(int) $c->v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    'b' => (string) $c->v === '1' ? 'TRUE' : 'FALSE',
                    default => (string) $c->v,
                };
                $cells[$col] = trim($value);
            }
            if ($cells) {
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $i => $v) {
                    $line[$i] = $v;
                }
                $rows[] = $line;
            }
            if (count($rows) > $maxRows) {
                break;
            }
        }

        return $rows;
    }

    private static function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }
}
