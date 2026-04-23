<?php

namespace Tests\Unit;

use App\Importing\DTO\SupplierProductDTO;
use App\Importing\ImporterRegistry;
use App\Importing\SupplierImporter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ImporterRegistryTest extends TestCase
{
    public function test_resolves_by_code(): void
    {
        $a = $this->makeImporter('acme');
        $b = $this->makeImporter('global_supply');
        $registry = new ImporterRegistry([$a, $b]);

        $this->assertSame($a, $registry->resolve('acme'));
        $this->assertSame($b, $registry->resolve('global_supply'));
    }

    public function test_by_code_returns_null_when_unknown(): void
    {
        $registry = new ImporterRegistry([$this->makeImporter('acme')]);

        $this->assertNull($registry->byCode('nonexistent'));
    }

    public function test_resolve_throws_with_known_codes_when_unknown(): void
    {
        $registry = new ImporterRegistry([
            $this->makeImporter('acme'),
            $this->makeImporter('global_supply'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unknown supplier 'nonexistent'. Known suppliers: [acme, global_supply].");

        $registry->resolve('nonexistent');
    }

    public function test_known_codes_lists_every_registered_importer(): void
    {
        $registry = new ImporterRegistry([
            $this->makeImporter('acme'),
            $this->makeImporter('global_supply'),
        ]);

        $this->assertSame(['acme', 'global_supply'], $registry->knownCodes());
    }

    private function makeImporter(string $code): SupplierImporter
    {
        return new class($code) implements SupplierImporter
        {
            public function __construct(private string $c) {}

            public function code(): string
            {
                return $this->c;
            }

            public function name(): string
            {
                return ucfirst($this->c);
            }

            public function parse(string $filepath): iterable
            {
                yield new SupplierProductDTO(
                    supplierCode: $this->c,
                    reference: 'X',
                    brand: 'Y',
                );
            }
        };
    }
}
