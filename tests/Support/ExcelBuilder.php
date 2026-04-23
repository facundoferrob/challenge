<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Build xlsx fixtures programmatically inside tests so they stay close
 * to the assertions that use them.
 */
class ExcelBuilder
{
    /**
     * Write a multi-sheet workbook to a temp file and return its path.
     *
     * @param  array<string, array<int, array<int, mixed>>>  $sheets
     *         [sheetName => [ [row], [row], ... ]]
     */
    public static function write(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tariff_').'.xlsx';
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        $i = 0;
        foreach ($sheets as $name => $rows) {
            $sheet = $book->createSheet($i++);
            $sheet->setTitle($name);
            $sheet->fromArray($rows, null, 'A1');
        }

        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }
}
