<?php

namespace App\Importing;

interface SupplierImporter
{
    /**
     * Stable identifier used by the --supplier= CLI flag and stored on the Supplier row.
     */
    public function code(): string;

    /**
     * Human-readable supplier name used when creating the Supplier row.
     */
    public function name(): string;

    /**
     * Parse the file and yield one DTO per logical product (not per row —
     * formats that put many rows for the same product must consolidate here).
     *
     * @return iterable<\App\Importing\DTO\SupplierProductDTO>
     */
    public function parse(string $filepath): iterable;
}
