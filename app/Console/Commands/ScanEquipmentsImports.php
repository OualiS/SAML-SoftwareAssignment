<?php

namespace App\Console\Commands;

use App\Jobs\ImportEquipmentsFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ScanEquipmentsImports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scan-equipments-imports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan equipments imports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dir = base_path(config('equipment.equipments_filepath'));

        if (! is_dir($dir)) {
            $this->warn("Import directory not found: {$dir}");
            return self::SUCCESS;
        }

        $files = glob($dir.DIRECTORY_SEPARATOR.'EQUIPMENTS_*.txt') ?: [];

        if ($files === []) {
            $this->info('No equipments file found.');
            return self::SUCCESS;
        }

        usort($files, fn(string $a, string $b) => strcmp(basename($a), basename($b)));
        $latestPath = end($files);
        $latestFile = basename($latestPath);

        $lastImported = Cache::get('equipments:last_imported_file');
        if ($lastImported === $latestFile) {
            $this->info("Already imported: {$latestFile}");
            return self::SUCCESS;
        }

        ImportEquipmentsFile::dispatch($latestPath, $latestFile);
        $this->info("Queued import for: {$latestFile}");

        return self::SUCCESS;
    }
}
