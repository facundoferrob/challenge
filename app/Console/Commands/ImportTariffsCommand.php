<?php

namespace App\Console\Commands;

use App\Importing\ImporterRegistry;
use App\Importing\ImportReport;
use App\Importing\Persister;
use Illuminate\Console\Command;

class ImportTariffsCommand extends Command
{
    protected $signature = 'tariffs:import
                            {file : Path to the supplier file}
                            {--supplier= : Supplier code (REQUIRED). The caller must state which supplier the file belongs to — we do not auto-detect.}';

    protected $description = 'Import a supplier tariff file';

    public function handle(ImporterRegistry $registry, Persister $persister): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $code = $this->option('supplier');
        if ($code === null || $code === '') {
            $this->error(sprintf(
                'Missing required option --supplier=<code>. Known suppliers: [%s].',
                implode(', ', $registry->knownCodes()),
            ));

            return self::FAILURE;
        }

        try {
            $importer = $registry->resolve($code);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Using importer: {$importer->code()} ({$importer->name()})");

        $report = new ImportReport();
        foreach ($importer->parse($file) as $dto) {
            try {
                $report->record($persister->persist($dto, $importer->name()));
            } catch (\Throwable $e) {
                $report->recordError($dto->reference, $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('Done. '.$report->summary());

        foreach ($report->errorMessages() as $msg) {
            $this->warn('  - '.$msg);
        }

        return self::SUCCESS;
    }
}
