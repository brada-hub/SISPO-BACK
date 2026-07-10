<?php

namespace App\Console\Commands;

use App\Models\EvaluationResult;
use App\Models\AiMatchingResult;
use App\Models\Postulacion;
use Illuminate\Console\Command;

class CompareAiDeterministicCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:compare-ai-deterministic {--limit=50 : Max comparisons to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare SISPO Score Engine deterministic scores against historical AI Gemini results';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $this->info('============================================================');
        $this->info('      COMPARATIVA: SISPO DET DETERMINISTIC VS GEMINI AI     ');
        $this->info('============================================================');

        // Fetch evaluations
        $evaluations = EvaluationResult::orderBy('postulacion_id', 'asc')->get();

        if ($evaluations->isEmpty()) {
            $this->warn('No existen resultados en la tabla evaluation_results. Por favor, corre primero:');
            $this->info('php artisan sispo:evaluate-deterministic --write');
            return 0;
        }

        $comparedCount = 0;
        $diffSum = 0.0;
        $extremeCount = 0;

        $comparisonRows = [];
        $header = ['ID Postulación', 'Nombre Postulante', 'Score DET', 'Score IA', 'Dif. Abs', 'Clasif. DET', 'Clasif. IA', 'Estado'];

        foreach ($evaluations as $e) {
            if ($comparedCount >= $limit) break;

            $aiResult = AiMatchingResult::where('postulacion_id', $e->postulacion_id)->latest()->first();

            if (!$aiResult) {
                continue; // no historical AI result for this postulación
            }

            $detScore = (float) $e->score_total;
            $aiScore = (float) $aiResult->score_total;
            $diff = abs($detScore - $aiScore);

            $diffSum += $diff;
            $comparedCount++;

            $isExtreme = $diff > 20.0;
            if ($isExtreme) $extremeCount++;

            $diffStr = $isExtreme 
                ? "<fg=red;options=bold>" . round($diff, 2) . " pts</>" 
                : round($diff, 2) . ' pts';

            $statusStr = $isExtreme 
                ? '<fg=red;options=bold>[❌ DESVIACIÓN EXTREMA]</>' 
                : '<fg=green>[✓ Aceptable]</>';

            $postulacion = Postulacion::with('postulante')->find($e->postulacion_id);
            $nombre = 'N/A';
            if ($postulacion && $postulacion->postulante) {
                $nombre = $postulacion->postulante->nombres . ' ' . $postulacion->postulante->apellidos;
            }

            $comparisonRows[] = [
                $e->postulacion_id,
                mb_substr($nombre, 0, 30),
                $detScore . ' pts',
                $aiScore . ' pts',
                $diffStr,
                mb_strtoupper($e->classification),
                mb_strtoupper($aiResult->clasificacion_ia ?? 'N/A'),
                $statusStr,
            ];
        }

        if ($comparedCount === 0) {
            $this->warn('Se encontraron registros en evaluation_results, pero ninguno coincide con ai_matching_results históricos.');
            return 0;
        }

        $this->table($header, $comparisonRows);

        $avgDiff = $diffSum / $comparedCount;

        $this->info('------------------------------------------------------------');
        $this->info('                      RESUMEN ESTADÍSTICO                   ');
        $this->info('------------------------------------------------------------');
        
        $stats = [
            ['Métrica Comparativa', 'Valor'],
            ['Total Comparados', $comparedCount],
            ['Desviación Promedio Absoluta', round($avgDiff, 2) . ' pts'],
            ['Casos con Desviación Extrema (> 20 pts)', $extremeCount . ' (' . round(($extremeCount / $comparedCount) * 100, 1) . '%)'],
        ];
        $this->table($stats[0], array_slice($stats, 1));
        $this->info('============================================================');

        return 0;
    }
}
