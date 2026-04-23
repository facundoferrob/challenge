<?php

namespace App\Importing;

use RuntimeException;

/**
 * Flat lookup of supplier importers by code. No auto-detection — callers
 * must state which supplier the file belongs to. Unknown codes fail loud.
 */
class ImporterRegistry
{
    /** @var SupplierImporter[] */
    private array $importers;

    /**
     * @param  iterable<SupplierImporter>  $importers
     */
    public function __construct(iterable $importers)
    {
        $this->importers = is_array($importers) ? $importers : iterator_to_array($importers);
    }

    public function byCode(string $code): ?SupplierImporter
    {
        foreach ($this->importers as $importer) {
            if ($importer->code() === $code) {
                return $importer;
            }
        }

        return null;
    }

    /**
     * Resolve the importer for the given supplier code, or throw with the
     * list of known codes so the caller knows what's available.
     */
    public function resolve(string $code): SupplierImporter
    {
        $importer = $this->byCode($code);
        if ($importer === null) {
            throw new RuntimeException(sprintf(
                "Unknown supplier '%s'. Known suppliers: [%s].",
                $code,
                implode(', ', $this->knownCodes()),
            ));
        }

        return $importer;
    }

    /** @return string[] */
    public function knownCodes(): array
    {
        return array_map(fn (SupplierImporter $i) => $i->code(), $this->importers);
    }
}
