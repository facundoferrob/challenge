<?php

namespace App\Providers;

use App\Importing\ImporterRegistry;
use App\Importing\SupplierImporter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

class ImportingServiceProvider extends ServiceProvider
{
    private const IMPORTERS_NAMESPACE = 'App\\Importing\\Suppliers';

    private const IMPORTERS_TAG = 'supplier.importer';

    public function register(): void
    {
        foreach ($this->discoverImporterClasses() as $class) {
            $this->app->singleton($class);
            $this->app->tag($class, self::IMPORTERS_TAG);
        }

        $this->app->singleton(ImporterRegistry::class, function ($app) {
            return new ImporterRegistry($app->tagged(self::IMPORTERS_TAG));
        });
    }

    /**
     * @return string[]
     */
    private function discoverImporterClasses(): array
    {
        $dir = app_path('Importing/Suppliers');
        if (! is_dir($dir)) {
            return [];
        }

        $classes = [];
        foreach (Finder::create()->files()->in($dir)->name('*.php') as $file) {
            $relative = str_replace(
                [$dir.DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
                ['', '', '\\'],
                $file->getPathname()
            );
            $class = self::IMPORTERS_NAMESPACE.'\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }
            $ref = new \ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->implementsInterface(SupplierImporter::class)) {
                continue;
            }
            $classes[] = $class;
        }

        return $classes;
    }
}
