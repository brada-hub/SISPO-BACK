<?php

namespace App\Console\Commands;

use App\Models\Postulante;
use App\Services\Evaluation\Temporal\TrainingTemporalCalculator;
use Illuminate\Console\Command;

class ProcessTrainingTemporalDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:process-training-temporal
                            {--dry-run : Simulate the training temporal processing}
                            {--write : Calculate and write temporal values to MySQL DB}
                            {--postulante_id= : Process only a specific Postulante ID}
                            {--limit= : Limit the number of candidates to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and process temporal, recency and hours distribution metrics for candidates trainings';

    /**
     * Execute the console command.
     */
    public function handle(TrainingTemporalCalculator $calculator): int
    {
        $write = $this->option('write');
        $dryRun = $this->option('dry-run');
        $postulanteId = $this->option('postulante_id') ? (int) $this->option('postulante_id') : null;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if (!$write && !$dryRun) {
            $this->error('Debes especificar --write o --dry-run para ejecutar el procesamiento temporal.');
            $this->info('Ejemplo: php artisan sispo:process-training-temporal --dry-run');
            return 1;
        }

        $this->info('============================================================');
        $this->info('       INICIANDO TRAINING TEMPORAL SCORING LAYER            ');
        $this->info('============================================================');
        $this->info('Modo de ejecución: ' . ($write ? '<fg=green;options=bold>ESCRITURA REAL</>' : '<fg=yellow;options=bold>SIMULACIÓN (DRY-RUN)</>'));
        if ($postulanteId) $this->info('Filtrando postulante ID: ' . $postulanteId);
        if ($limit) $this->info('Límite de registros: ' . $limit);
        $this->info('------------------------------------------------------------');

        // Query candidates
        $query = Postulante::query();
        if ($postulanteId) {
            $query->where('id', $postulanteId);
        }
        if ($limit) {
            $query->limit($limit);
        }

        // Only process candidates with trainings
        $query->whereHas('capacitaciones');

        $candidates = $query->get();

        if ($candidates->isEmpty()) {
            $this->warn('No se encontraron candidatos con registros de capacitaciones.');
            return 0;
        }

        $totalProcessed = 0;
        $totalHoursSum = 0;
        $hoursLast3Sum = 0;
        $hoursLast5Sum = 0;
        $invalidDatesTotal = 0;
        $results = [];

        foreach ($candidates as $candidate) {
            try {
                $res = $calculator->process($candidate->id, $write);

                $totalProcessed++;
                $totalHoursSum += $res['total_training_hours'];
                $hoursLast3Sum += $res['training_hours_last_3_years'];
                $hoursLast5Sum += $res['training_hours_last_5_years'];
                $invalidDatesTotal += $res['invalid_dates_count'];

                $res['nombre_completo'] = $candidate->nombres . ' ' . $candidate->apellidos;
                $results[] = $res;

            } catch (\Throwable $e) {
                $this->error("Error procesando postulante ID {$candidate->id}: " . $e->getMessage());
            }
        }

        // Summary report
        $this->info('============================================================');
        $this->info('             AUDITORÍA GLOBAL DE CAPACITACIONES TEMPORALES  ');
        $this->info('============================================================');

        $avgHoursTotal = $totalProcessed > 0 ? round($totalHoursSum / $totalProcessed, 1) : 0;
        $avgHoursLast5 = $totalProcessed > 0 ? round($hoursLast5Sum / $totalProcessed, 1) : 0;

        $statsTable = [
            ['Métrica de Carga Horaria Temporal', 'Valor'],
            ['Total Postulantes Procesados', $totalProcessed],
            ['Promedio de Horas Totales por Postulante', $avgHoursTotal . ' horas'],
            ['Promedio de Horas en Últimos 5 Años por Postulante', $avgHoursLast5 . ' horas'],
            ['Total Cursos con Fecha Inválida/Nula (Fallback)', $invalidDatesTotal],
        ];
        $this->table($statsTable[0], array_slice($statsTable, 1));
        $this->info('------------------------------------------------------------');

        // Top 20 Candidates by hours in last 5 years
        $this->comment('🏆 TOP 20 POSTULANTES POR HORAS RECIENTES (ÚLTIMOS 5 AÑOS):');
        usort($results, function ($a, $b) {
            return $b['training_hours_last_5_years'] <=> $a['training_hours_last_5_years'];
        });

        $topTable = [['ID Postulante', 'Nombre Postulante', 'Horas Totales', 'Horas Últ. 3 Años', 'Horas Últ. 5 Años', 'Recencia Máx.']];
        foreach (array_slice($results, 0, 20) as $r) {
            $topTable[] = [
                $r['postulante_id'],
                $r['nombre_completo'],
                $r['total_training_hours'] . ' hrs',
                $r['training_hours_last_3_years'] . ' hrs',
                $r['training_hours_last_5_years'] . ' hrs',
                $r['max_training_recency_score'] . ' pts',
            ];
        }

        $this->table($topTable[0], array_slice($topTable, 1));
        $this->info('============================================================');

        if ($write) {
            $this->info('🎉 ¡El procesamiento temporal de capacitaciones se ha guardado en MySQL!');
        } else {
            $this->info('💡 Simulación completada. No se grabaron cambios en MySQL. Usa --write para persistir.');
        }
        $this->info('============================================================');

        return 0;
    }
}
