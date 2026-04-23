<?php

/**
 * Generate two sample tariff Excel files with different formats.
 * Run: php tools/generate-samples.php
 */

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$outDir = __DIR__.'/../storage/app/samples';
if (! is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

// ----------------------------------------------------------------------------
// Format A — Acme (wide): tiers as columns, taxes in a second sheet.
// Prices are net (tax-exclusive).
// ----------------------------------------------------------------------------
$acme = new Spreadsheet();
$sheet = $acme->getActiveSheet();
$sheet->setTitle('Productos');
$sheet->fromArray([
    ['Referencia', 'Marca', 'EAN13', 'Descripción', 'Familia', 'Subfamilia', 'Unidad', 'Dim (L×A×P)', 'Precio 1+', 'Precio 10+', 'Precio 100+'],
    ['ACM-001', 'Acme', '8410101010101', 'Tornillo inox M6x20', 'Tornillería', 'Tornillos', 'unidad', '20×6×6', 0.25, 0.19, 0.12],
    ['ACM-002', 'Acme', '8410101010102', 'Tuerca inox M6', 'Tornillería', 'Tuercas', 'unidad', '6×6×3', 0.15, 0.11, 0.08],
    ['ACM-003', 'Acme', null, 'Arandela plana M6', 'Tornillería', 'Arandelas', 'unidad', '12×12×1', 0.05, 0.04, 0.02],
    ['ACM-100', 'Bolts&Co', '8420202020201', 'Varilla roscada 1m', 'Tornillería', 'Varillas', 'unidad', '1000×8×8', 4.50, 4.10, 3.80],
], null, 'A1');

$tax = $acme->createSheet();
$tax->setTitle('Impuestos');
$tax->fromArray([
    ['Referencia', 'Pais', 'Unidad', 'Tasa'],
    ['ACM-001', 'ES', 'unidad', 0.21],
    ['ACM-001', 'FR', 'unidad', 0.20],
    ['ACM-002', 'ES', 'unidad', 0.21],
    ['ACM-100', 'ES', 'unidad', 0.21],
    ['ACM-100', 'DE', 'unidad', 0.19],
], null, 'A1');

IOFactory::createWriter($acme, 'Xlsx')->save($outDir.'/acme_tariff.xlsx');
echo "Wrote {$outDir}/acme_tariff.xlsx\n";

// ----------------------------------------------------------------------------
// Format B — Global Supply (long): one row per price tier, unit in header,
// VAT per country as columns.
// ----------------------------------------------------------------------------
$gs = new Spreadsheet();
$sheet = $gs->getActiveSheet();
$sheet->setTitle('Tariff');
$sheet->fromArray([
    ['REF', 'Manufacturer', 'Part Number', 'Category / Sub', 'Peso (kg)', 'Qty From', 'Price EUR', 'IVA_ES', 'IVA_FR', 'IVA_DE'],
    ['GS-A-100', 'FastenPro', '5060000000101', 'Fasteners / Bolts', 0.05, 1, 1.20, 0.21, 0.20, 0.19],
    ['GS-A-100', 'FastenPro', '5060000000101', 'Fasteners / Bolts', 0.05, 50, 1.00, 0.21, 0.20, 0.19],
    ['GS-A-100', 'FastenPro', '5060000000101', 'Fasteners / Bolts', 0.05, 500, 0.80, 0.21, 0.20, 0.19],
    ['GS-A-200', 'FastenPro', '5060000000202', 'Fasteners / Nuts', 0.02, 1, 0.50, 0.21, 0.20, 0.19],
    ['GS-A-200', 'FastenPro', '5060000000202', 'Fasteners / Nuts', 0.02, 100, 0.40, 0.21, 0.20, 0.19],
    ['GS-B-010', 'HeavyCo', '5060000000310', 'Machinery / Shafts', 12.5, 1, 35.00, 0.21, null, null],
    ['GS-B-010', 'HeavyCo', '5060000000310', 'Machinery / Shafts', 12.5, 10, 32.00, 0.21, null, null],
], null, 'A1');

IOFactory::createWriter($gs, 'Xlsx')->save($outDir.'/global_supply_tariff.xlsx');
echo "Wrote {$outDir}/global_supply_tariff.xlsx\n";
