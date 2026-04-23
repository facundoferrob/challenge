<?php

namespace App\Importing;

/**
 * Accumulates the outcome of an import run (how many products were created /
 * updated, which references failed).
 *
 * Lives separately from {@see Persister} so persistence stays stateless.
 */
class ImportReport
{
    private int $created = 0;

    private int $updated = 0;

    /** @var array<int, string> */
    private array $errors = [];

    public function record(PersistResult $result): void
    {
        match ($result) {
            PersistResult::Created => $this->created++,
            PersistResult::Updated => $this->updated++,
        };
    }

    public function recordError(string $reference, string $message): void
    {
        $this->errors[] = sprintf('ref=%s: %s', $reference, $message);
    }

    public function createdCount(): int
    {
        return $this->created;
    }

    public function updatedCount(): int
    {
        return $this->updated;
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    /** @return array<int, string> */
    public function errorMessages(): array
    {
        return $this->errors;
    }

    public function summary(): string
    {
        return sprintf(
            'Created: %d, Updated: %d, Errors: %d',
            $this->created,
            $this->updated,
            $this->errorCount(),
        );
    }
}
