<?php

namespace Tests\Unit;

use App\Importing\Suppliers\AcmeImporter;
use PHPUnit\Framework\TestCase;
use Tests\Support\ExcelBuilder;

class AcmeImporterTest extends TestCase
{
    public function test_parses_wide_format_with_multiple_tiers_and_taxes_from_second_sheet(): void
    {
        $path = ExcelBuilder::write([
            'Productos' => [
                ['Referencia', 'Marca', 'EAN13', 'Descripción', 'Familia', 'Subfamilia', 'Unidad', 'Dim (L×A×P)', 'Precio 1+', 'Precio 10+', 'Precio 100+'],
                ['ACM-001', 'Acme', '8410000000001', 'Tornillo', 'Tornillería', 'Tornillos', 'unidad', '20×6×6', 0.25, 0.19, 0.12],
                ['ACM-002', 'Acme', null, 'Tuerca', 'Tornillería', 'Tuercas', 'unidad', '', 0.15, null, null],
            ],
            'Impuestos' => [
                ['Referencia', 'Pais', 'Unidad', 'Tasa'],
                ['ACM-001', 'ES', 'unidad', 0.21],
                ['ACM-001', 'FR', 'unidad', 0.20],
            ],
        ]);

        $dtos = iterator_to_array((new AcmeImporter())->parse($path), false);

        $this->assertCount(2, $dtos);

        $first = $dtos[0];
        $this->assertSame('ACM-001', $first->reference);
        $this->assertSame('Acme', $first->brand);
        $this->assertSame('8410000000001', $first->ean);
        $this->assertSame('Tornillería', $first->family);
        $this->assertSame('Tornillos', $first->subfamily);
        $this->assertSame(['length' => 20.0, 'width' => 6.0, 'depth' => 6.0], $first->dimensions);

        $this->assertCount(3, $first->priceTiers);
        $this->assertSame(1, $first->priceTiers[0]['min_quantity']);
        $this->assertSame(0.25, $first->priceTiers[0]['price']);
        $this->assertSame(100, $first->priceTiers[2]['min_quantity']);
        $this->assertSame(0.12, $first->priceTiers[2]['price']);

        $this->assertCount(2, $first->taxes);
        $this->assertSame('ES', $first->taxes[0]['country_code']);
        $this->assertSame(0.21, $first->taxes[0]['rate']);
        $this->assertSame('FR', $first->taxes[1]['country_code']);

        $second = $dtos[1];
        $this->assertSame('ACM-002', $second->reference);
        $this->assertNull($second->ean);
        $this->assertCount(1, $second->priceTiers, 'empty tier cells must be skipped');
        $this->assertSame([], $second->taxes);

        @unlink($path);
    }
}
