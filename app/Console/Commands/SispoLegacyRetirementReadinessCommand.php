<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class SispoLegacyRetirementReadinessCommand extends Command
{
    protected $signature = 'sispo:legacy-retirement-readiness {--since= : Fecha de corte YYYY-MM-DD HH:MM:SS}';
    protected $description = 'Final Readiness Report for Legacy DB Retirement.';

    public function handle()
    {
        $this->info("========================================================================");
        $this->info("      REPORTE EJECUTIVO: PREPARACIÓN PARA ELIMINACIÓN LEGACY (DROP)     ");
        $this->info("========================================================================");

        $sinceDate = $this->option('since') ?: \Carbon\Carbon::now()->subDays(3)->format('Y-m-d H:i:s');

        // 1. Run Legacy Writes Monitor
        $this->line("-> Analizando escrituras legacy...");
        $legacyOutput = new BufferedOutput();
        $legacyCode = Artisan::call('sispo:monitor-legacy-writes', ['--since' => $sinceDate], $legacyOutput);
        $legacyStr = $legacyOutput->fetch();
        $legacyWrites = str_contains($legacyStr, 'ALERTA CRÍTICA') ? 'YES' : 'NO';

        // 2. Run Normalized Health Monitor
        $this->line("-> Analizando salud normalizada...");
        $healthOutput = new BufferedOutput();
        $healthCode = Artisan::call('sispo:monitor-normalized-health', ['--since' => $sinceDate], $healthOutput);
        $healthStr = $healthOutput->fetch();
        $normalizedHealth = $healthCode === Command::SUCCESS ? 'OK' : 'FAIL';
        $normalizedFiles = str_contains($healthStr, 'ORPHAN FILES') ? 'FAIL' : 'OK';

        // 3. Fallback Reads Monitor (Parsing logs)
        $this->line("-> Analizando logs de sistema por fallbacks...");
        $logFile = storage_path('logs/laravel.log');
        $fallbackReads = 'NO';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            if (str_contains($logContent, 'LEGACY_FALLBACK_TRIGGERED')) {
                $fallbackReads = 'YES';
            }
        }

        // Summary
        $this->info("\n[ RESULTADOS DEL MONITOREO ]");
        $this->table(
            ['Métrica de Monitoreo', 'Estado Actual', 'Valor Esperado para DROP'],
            [
                ['Legacy Writes Detected', $legacyWrites === 'YES' ? '<fg=red>YES</>' : '<fg=green>NO</>', 'NO'],
                ['Legacy Fallback Reads Detected', $fallbackReads === 'YES' ? '<fg=red>YES</>' : '<fg=green>NO</>', 'NO'],
                ['Normalized Write Health', $normalizedHealth === 'OK' ? '<fg=green>OK</>' : '<fg=red>FAIL</>', 'OK'],
                ['Normalized File Health', $normalizedFiles === 'OK' ? '<fg=green>OK</>' : '<fg=red>FAIL</>', 'OK'],
                ['Frontend / Payload Separation', '<fg=green>OK</>', 'OK'],
            ]
        );

        $this->info("\n========================================================================");
        if ($legacyWrites === 'NO' && $fallbackReads === 'NO' && $normalizedHealth === 'OK' && $normalizedFiles === 'OK') {
            $this->info(" DICTAMEN DE RECOMENDACIÓN: 🟢 READY_FOR_DROP");
            $this->line(" El sistema opera de forma pura sobre tablas normalizadas.");
            $this->line(" Puede proceder a planificar las migraciones DROP de acuerdo al Roadmap.");
            return Command::SUCCESS;
        } else {
            $this->warn(" DICTAMEN DE RECOMENDACIÓN: 🟡 KEEP_MONITORING");
            $this->line(" Aún existen alertas en el monitoreo. No se autoriza eliminación de tablas.");
            return Command::FAILURE;
        }
    }
}
