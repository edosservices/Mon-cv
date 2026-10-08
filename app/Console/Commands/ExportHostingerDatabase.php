<?php

namespace App\Console\Commands;

use App\Support\HostingerSqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExportHostingerDatabase extends Command
{
    protected $signature = 'db:export-hostinger {path? : Chemin du fichier SQL à créer}';

    protected $description = 'Exporte la base MySQL en SQL compatible Hostinger, sans modifier les données.';

    public function handle(HostingerSqlDump $dump): int
    {
        $path = (string) ($this->argument('path') ?: storage_path('app/hostinger/limete_wifi_hostinger.sql'));

        try {
            $report = $dump->export(DB::connection(), $path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Base source : '.$report['database'].' (lecture seule)');
        $this->info('Tables : '.$report['tables']);
        $this->info('Lignes exportées : '.$report['inserts']);
        $this->info('Fichier : '.$report['path']);
        $this->info('Vérification : OK');
        foreach ($report['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
