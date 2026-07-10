<?php

namespace App\Console\Commands;

use App\Models\Postulante;
use App\Models\PostulanteExperienceSummary;
use App\Services\Evaluation\Temporal\ExperienceOverlapCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateExperienceSummariesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:calculate-experience-summaries
                            {--dry-run : Simulate the overlap calculation process without saving}
                            {--write : Calculate and write summaries to postulante_experience_summaries DB table}
                            {--postulante_id= : Process only a specific postulante ID}
                            {--limit= : Limit the number of postulantes to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and normalize professional experience overlaps, generating chronological summary for postulantes';

    /**
     * Execute the console command.
     */
    public function handle(ExperienceOverlapCalculator $overlapCalculator): int
    {
        $write = $this->option('write');
        $dryRun = $this->option('dry-run');
        $postulanteId = $this->option('postulante_id') ? (int) $this->option('postulante_id') : null;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if (!$write && !$dryRun) {
            $this->error('Debes especificar --write o --dry-run para ejecutar la normalización de solapamientos.');
            $this->info('Ejemplo: php artisan sispo:calculate-experience-summaries --dry-run');
            return 1;
        }

        $this->info('============================================================');
        $this->info('    INICIANDO CAPA DE NORMALIZACIÓN DE SOLAPAMIENTOS        ');
        $this->info('============================================================');
        $this->info('Modo de ejecución: ' . ($write ? '<fg=green;options=bold>ESCRITURA REAL</>' : '<fg=yellow;options=bold>SIMULACIÓN (DRY-RUN)</>'));
        if ($postulanteId) {
            $this->info('Filtrando postulante ID: ' . $postulanteId);
        }
        if ($limit) {
            $this->info('Límite de postulantes: ' . $limit);
        }
        $this->info('Fecha Referencia de Recencia: 2026-05-17');
        $this->info('------------------------------------------------------------');

        // Query postulantes
        $query = Postulante::query();
        if ($postulanteId) {
            $query->where('id', $postulanteId);
        }
        if ($limit) {
            $query->limit($limit);
        }

        $postulantes = $query->get();
        $totalProcessed = 0;
        $overlapDetectedCount = 0;

        $accumulated5Sum = 0;
        $unique5Sum = 0;
        $summaries = [];

        foreach ($postulantes as $postulante) {
            $summary = $overlapCalculator->summarizeForPostulante($postulante->id);
            if (!$summary) {
                continue;
            }

            $totalProcessed++;
            if ($summary['overlap_detected']) {
                $overlapDetectedCount++;
            }

            $accumulated5Sum += $summary['accumulated_months_last_5_years'];
            $unique5Sum += $summary['unique_months_last_5_years'];

            $summary['nombre_completo'] = $postulante->nombres . ' ' . $postulante->apellidos;
            $summaries[] = $summary;

            if ($write) {
                PostulanteExperienceSummary::updateOrCreate(
                    ['postulante_id' => $postulante->id],
                    array_merge($summary, ['processed_at' => Carbon::now()])
                );
            }
        }

        // Summary report
        $this->info('============================================================');
        $this->info('              REPORTE DE NORMALIZACIÓN DE OVERLAPS          ');
        $this->info('============================================================');

        $avgAccumulated5 = $totalProcessed > 0 ? round($accumulated5Sum / $totalProcessed, 1) : 0;
        $avgUnique5 = $totalProcessed > 0 ? round($unique5Sum / $totalProcessed, 1) : 0;

        $mainStats = [
            ['Métrica de Overlap', 'Valor'],
            ['Total Postulantes Procesados', $totalProcessed],
            ['Postulantes con Solapamientos Solucionados (Overlap Detected)', $overlapDetectedCount],
            ['Promedio Meses Acumulados (Últimos 5 Años)', $avgAccumulated5 . ' meses'],
            ['Promedio Meses Únicos Reales (Últimos 5 Años - CAP 60)', $avgUnique5 . ' meses'],
        ];
        $this->table($mainStats[0], array_slice($mainStats, 1));
        $this->info('------------------------------------------------------------');

        // Top 20 Postulantes con mayor solapamiento (Overlap Months Estimated)
        $this->comment('⚠️ TOP 20 POSTULANTES CON MAYOR SOLAPAMIENTO DE CARGOS PARALELOS:');
        usort($summaries, function ($a, $b) {
            return $b['overlap_months_estimated'] <=> $a['overlap_months_estimated'];
        });

        $topOverlapTable = [['ID', 'Nombre Postulante', 'Meses Acumulados', 'Meses Únicos Reales', 'Meses Solapados (Overlap)']];
        foreach (array_slice($summaries, 0, 20) as $s) {
            if ($s['overlap_months_estimated'] > 0) {
                $topOverlapTable[] = [
                    $s['postulante_id'],
                    $s['nombre_completo'],
                    $s['total_accumulated_months'] . ' meses',
                    $s['total_unique_months'] . ' meses',
                    '<fg=red;options=bold>' . $s['overlap_months_estimated'] . ' meses solapados</>',
                ];
            }
        }

        if (count($topOverlapTable) > 1) {
            $this->table($topOverlapTable[0], array_slice($topOverlapTable, 1));
        } else {
            $this->comment('No se detectaron solapamientos significativos en los postulantes procesados.');
        }
        $this->info('------------------------------------------------------------');

        // Top 20 Postulantes por meses únicos reales en los últimos 5 años (unique_months_last_5_years)
        $this->comment('🏆 TOP 20 POSTULANTES POR EXPERIENCIA CRONOLÓGICA ÚNICA REAL EN ÚLTIMOS 5 AÑOS:');
        usort($summaries, function ($a, $b) {
            return $b['unique_months_last_5_years'] <=> $a['unique_months_last_5_years'];
        });

        $topUniqueTable = [['ID', 'Nombre Postulante', 'Únicos Reales (Últimos 5 Años)', 'Acumulados (Últimos 5 Años)', 'Vigentes']];
        foreach (array_slice($summaries, 0, 20) as $s) {
            if ($s['unique_months_last_5_years'] > 0) {
                $topUniqueTable[] = [
                    $s['postulante_id'],
                    $s['nombre_completo'],
                    '<fg=green;options=bold>' . $s['unique_months_last_5_years'] . ' meses</>',
                    $s['accumulated_months_last_5_years'] . ' meses',
                    $s['current_experience_count'] . ' cargos',
                ];
            }
        }

        if (count($topUniqueTable) > 1) {
            $this->table($topUniqueTable[0], array_slice($topUniqueTable, 1));
        } else {
            $this->comment('No hay experiencia en los últimos 5 años registrada en los postulantes procesados.');
        }
        $this->info('============================================================');

        if ($write) {
            $this->info('🎉 ¡La normalización de solapamientos se ha guardado exitosamente en base de datos!');
        } else {
            $this->info('💡 Simulación completada. No se grabaron cambios en MySQL. Usa --write para persistir.');
        }
        $this->info('============================================================');

        return 0;
    }
}
