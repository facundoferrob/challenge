<?php

namespace Tests\Unit;

use App\Importing\Suppliers\GlobalSupplyImporter;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExcelBuilder;

class GlobalSupplyImporterTest extends TestCase
{
    public function test_parses_long_format_groups_tiers_and_extracts_unit_from_header(): void
    {
        $path = ExcelBuilder::write([
            'Tariff' => [
                ['REF', 'Manufacturer', 'Part Number', 'Category / Sub', 'Peso (kg)', 'Qty From', 'Price EUR', 'IVA_ES', 'IVA_FR'],
                ['GS-1', 'FastenPro', '5000000000001', 'Fasteners / Bolts', 0.05, 1, 1.20, 0.21, 0.20],
                ['GS-1', 'FastenPro', '5000000000001', 'Fasteners / Bolts', 0.05, 50, 1.00, 0.21, 0.20],
                ['GS-1', 'FastenPro', '5000000000001', 'Fasteners / Bolts', 0.05, 500, 0.80, 0.21, 0.20],
                ['GS-2', 'HeavyCo',   '5000000000002', 'Machinery / Shafts', 12.5, 1, 35.00, 0.21, null],
            ],
        ]);

        $dtos = iterator_to_array((new GlobalSupplyImporter())->parse($path), false);

        $this->assertCount(2, $dtos, 'rows with same REF must be consolidated into one DTO');

        $gs1 = $dtos[0];
        $this->assertSame('GS-1', $gs1->reference);
        $this->assertSame('FastenPro', $gs1->brand);
        $this->assertSame('5000000000001', $gs1->ean);
        $this->assertSame('Fasteners', $gs1->family);
        $this->assertSame('Bolts', $gs1->subfamily);
        $this->assertSame('kg', $gs1->unit, 'unit must be extracted from "Peso (kg)" header');
        $this->assertSame(0.05, $gs1->dimensions['weight']);

        $this->assertCount(3, $gs1->priceTiers);
        $this->assertSame([1, 50, 500], array_map(fn ($t) => $t['min_quantity'], $gs1->priceTiers));
        $this->assertSame([1.20, 1.00, 0.80], array_map(fn ($t) => $t['price'], $gs1->priceTiers));

        $this->assertCount(2, $gs1->taxes);
        $this->assertSame('ES', $gs1->taxes[0]['country_code']);
        $this->assertSame(0.21, $gs1->taxes[0]['rate']);

        $gs2 = $dtos[1];
        $this->assertCount(1, $gs2->priceTiers);
        $this->assertCount(1, $gs2->taxes, 'null-rate country columns are skipped');

        @unlink($path);
    }
}
