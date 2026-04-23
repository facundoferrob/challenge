<?php

namespace App\Importing;

/**
 * Outcome of persisting a single {@see \App\Importing\DTO\SupplierProductDTO}.
 *
 * `Persister` returns this so that callers (e.g. the import command) can
 * accumulate their own summary without the persister itself holding state.
 */
enum PersistResult
{
    case Created;
    case Updated;
}
