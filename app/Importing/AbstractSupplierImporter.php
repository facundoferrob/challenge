<?php

namespace App\Importing;

/**
 * Base class for supplier importers.
 *
 * Concrete subclasses implement {@see code()}, {@see name()} and {@see parse()},
 * and are picked up automatically by {@see \App\Providers\ImportingServiceProvider}
 * via PSR-4 discovery under `App\Importing\Suppliers\`.
 *
 * Holds a single shared helper — {@see cleanString()} — for defensive cell
 * normalization. Numeric casts and column fallbacks are simple enough that
 * each importer does them inline.
 */
abstract class AbstractSupplierImporter implements SupplierImporter
{
    abstract public function code(): string;

    abstract public function name(): string;

    abstract public function parse(string $filepath): iterable;

    /**
     * Normalize a raw spreadsheet cell to a trimmed non-empty string, or null.
     * PhpSpreadsheet returns `null`, `''`, numbers, or strings with surrounding
     * whitespace depending on the cell — this collapses those into a clean
     * `?string` so the rest of the importer can treat it uniformly.
     */
    protected function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
