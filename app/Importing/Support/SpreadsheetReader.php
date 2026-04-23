<?php

namespace App\Importing\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SpreadsheetReader
{
    public static function load(string $filepath): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($filepath);
        $reader->setReadDataOnly(true);

        return $reader->load($filepath);
    }

    /**
     * Read a sheet as an array of associative rows keyed by the header row.
     *
     * @return iterable<array<string, mixed>>
     */
    public static function readAssoc(string $filepath, ?string $sheetName = null): iterable
    {
        $spreadsheet = self::load($filepath);
        $sheet = $sheetName ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getSheet(0);

        if (! $sheet) {
            return [];
        }

        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) === 0) {
            return [];
        }

        $headers = array_map(fn ($v) => is_string($v) ? trim($v) : (string) $v, $rows[0]);

        $out = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $assoc = [];
            foreach ($headers as $idx => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $row[$idx] ?? null;
            }
            // skip blank rows
            if (count(array_filter($assoc, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $out[] = $assoc;
        }

        return $out;
    }
}
