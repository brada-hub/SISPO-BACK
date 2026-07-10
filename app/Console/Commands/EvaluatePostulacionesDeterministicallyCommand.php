<?php

namespace App\Console\Commands;

use App\Models\Postulacion;
use App\Models\EvaluationResult;
use App\Services\Evaluation\Score\SispoScoreEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EvaluatePostulacionesDeterministicallyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:evaluate-deterministic
                            {--dry-run : Simulate the scoring process without saving}
                            {--write : Calculate and write scores to evaluation_results DB table}
                            {--convocatoria_id= : Process only a specific Convocatoria (Oferta) ID}
                            {--postulacion_id= : Process only a specific Postulación ID}
                            {--limit= : Limit the number of postulaciones to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate candidate applications mathematically and deterministically using institutional rules';

    /**
     * Execute the console command.
     */
    public function handle(SispoScoreEngine $scoreEngine): int
    {
        $write = $this->option('write');
        $dryRun = $this->option('dry-run');
        $convocatoriaId = $this->option('convocatoria_id') ? (int) $this->option('convocatoria_id') : null;
        $postulacionId = $this->option('postulacion_id') ? (int) $this->option('postulacion_id') : null;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if (!$write && !$dryRun) {
            $this->error('Debes especificar --write o --dry-run para ejecutar la evaluación.');
            $this->info('Ejemplo: php artisan sispo:evaluate-deterministic --dry-run');
            return 1;
        }

        $this->info('============================================================');
        $this->info('           INICIANDO SISPO SCORE ENGINE V1                  ');
        $this->info('============================================================');
        $this->info('Modo de ejecución: ' . ($write ? '<fg=green;options=bold>ESCRITURA REAL</>' : '<fg=yellow;options=bold>SIMULACIÓN (DRY-RUN)</>'));
        if ($convocatoriaId) {
            $this->info('Filtrando convocatoria ID: ' . $convocatoriaId);
        }
        if ($postulacionId) {
            $this->info('Filtrando postulación ID: ' . $postulacionId);
        }
        if ($limit) {
            $this->info('Límite de postulaciones: ' . $limit);
        }
        $this->info('------------------------------------------------------------');

        // Query postulaciones
        $query = Postulacion::query();
        if ($convocatoriaId) {
            $query->where('oferta_id', $convocatoriaId);
        }
        if ($postulacionId) {
            $query->where('id', $postulacionId);
        }
        if ($limit) {
            $query->limit($limit);
        }

        $postulaciones = $query->get();
        
        if ($postulaciones->isEmpty()) {
            $this->warn('No se encontraron postulaciones que cumplan con los filtros especificados.');
            return 0;
        }

        $totalProcessed = 0;
        $scoreSum = 0.0;

        $distributions = [
            'apto'             => 0,
            'parcialmente_apto'=> 0,
            'no_apto'          => 0,
        ];

        $humanReviewsRequired = 0;
        $breakdownsSum = [];
        $evaluations = [];

        foreach ($postulaciones as $post) {
            try {
                $res = $scoreEngine->evaluate($post->id, $write);

                $totalProcessed++;
                $scoreSum += (float) $res['score_total'];
                $distributions[$res['classification']]++;
                
                if ($res['requires_human_review']) {
                    $humanReviewsRequired++;
                }

                // Add breakdowns
                foreach ($res['score_breakdown_json'] as $code => $crit) {
                    if (!isset($breakdownsSum[$code])) {
                        $breakdownsSum[$code] = [
                            'name'       => $crit['criterion_name'],
                            'score_sum'  => 0.0,
                            'max_points' => $crit['max_points'],
                        ];
                    }
                    $breakdownsSum[$code]['score_sum'] += $crit['score'];
                }

                // Load candidate names
                $postulante = $post->postulante;
                $res['nombre_completo'] = $postulante->nombres . ' ' . $postulante->apellidos;
                $evaluations[] = $res;

                // Sync frontend compatibility models in real write mode
                if ($write && $postulante) {
                    $analysis = \App\Models\AiCvAnalysis::updateOrCreate(
                        ['postulante_id' => $postulante->id, 'postulacion_id' => $post->id],
                        [
                            'status'                => 'completed',
                            'raw_text'              => 'Deterministic Scoring Mode (100% No-AI Mode active, Gemini bypassed)',
                            'text_extraction_method' => 'db_consolidated',
                            'text_length'           => 100,
                            'ai_response'           => $res,
                            'ai_provider'           => 'score_engine',
                            'ai_model'              => 'deterministic_v1',
                            'prompt_version'        => '1.0',
                            'tokens_used'           => 0,
                            'processing_time_ms'    => 5,
                        ]
                    );

                    \App\Models\AiMatchingResult::updateOrCreate(
                        ['postulacion_id' => $post->id],
                        [
                            'ai_cv_analysis_id'    => $analysis->id,
                            'convocatoria_id'      => $res['convocatoria_id'],
                            'score_total'          => $res['score_total'],
                            'score_formacion'      => $res['score_breakdown_json']['academic_formation']['score'] ?? 0,
                            'score_experiencia'    => $res['score_breakdown_json']['professional_experience']['score'] ?? 0,
                            'score_docencia'       => $res['score_breakdown_json']['teaching_experience']['score'] ?? 0,
                            'score_habilidades'    => 0.0,
                            'score_requisitos'     => 0.0,
                            'score_idiomas'        => 0.0,
                            'pesos_aplicados'      => [],
                            'clasificacion_ia'     => $res['classification'],
                            'cumple_requisitos'    => true,
                            'requisitos_cumplidos' => [],
                            'requisitos_faltantes' => [],
                            'observaciones_ia'     => 'Fortalezas: ' . implode(', ', $res['strengths_json']) . '. Explicación: ' . ($res['review_reason_summary'] ?? 'Evaluado determinísticamente.'),
                            'fortalezas'           => $res['strengths_json'],
                            'debilidades'          => $res['weaknesses_json'],
                            'justificacion'        => 'Score: ' . $res['score_total'] . ' pts. ' . ($res['review_reason_summary'] ?? 'Cumple con parámetros deterministicos.'),
                            'evaluation_mode'      => 'deterministic',
                        ]
                    );
                }

            } catch (\Throwable $e) {
                $this->error("Error evaluando postulación ID {$post->id}: " . $e->getMessage());
            }
        }

        // Summary reports
        $this->info('============================================================');
        $this->info('                AUDITORÍA GLOBAL DE EVALUACIONES            ');
        $this->info('============================================================');

        $avgScore = $totalProcessed > 0 ? round($scoreSum / $totalProcessed, 2) : 0;
        $humanRatio = $totalProcessed > 0 ? round(($humanReviewsRequired / $totalProcessed) * 100, 1) . '%' : '0%';

        $auditTable = [
            ['Métrica de Evaluación', 'Valor'],
            ['Total Postulaciones Procesadas', $totalProcessed],
            ['Promedio de Score Total', $avgScore . ' pts'],
            ['Aptos (Clasificación >= 80)', $distributions['apto']],
            ['Parcialmente Aptos (55 <= Score < 80)', $distributions['parcialmente_apto']],
            ['No Aptos (Score < 55)', $distributions['no_apto']],
            ['Postulaciones con "Revisión Humana Requerida"', $humanReviewsRequired . ' (' . $humanRatio . ')'],
        ];
        $this->table($auditTable[0], array_slice($auditTable, 1));
        $this->info('------------------------------------------------------------');

        // Breakdown promedio
        $this->comment('📊 RENDIMIENTO PROMEDIO POR CRITERIO EVALUADO:');
        $breakdownTable = [['Criterio de Selección', 'Promedio Obtenido', 'Máximo Criterio', 'Ratio de Éxito']];
        foreach ($breakdownsSum as $code => $b) {
            $avgCrit = $totalProcessed > 0 ? round($b['score_sum'] / $totalProcessed, 2) : 0;
            $ratio = $b['max_points'] > 0 ? round(($avgCrit / $b['max_points']) * 100, 1) . '%' : '0%';
            $breakdownTable[] = [
                $b['name'],
                $avgCrit . ' pts',
                $b['max_points'] . ' pts',
                $ratio,
            ];
        }
        $this->table($breakdownTable[0], array_slice($breakdownTable, 1));
        $this->info('------------------------------------------------------------');

        // Top 20 Candidates by score
        $this->comment('🏆 TOP 20 POSTULANTES EVALUADOS POR EL SCORE ENGINE:');
        usort($evaluations, function ($a, $b) {
            return $b['score_total'] <=> $a['score_total'];
        });

        $topTable = [['ID Postulación', 'Nombre Postulante', 'Score Total', 'Clasificación', 'Auditoría Interna']];
        foreach (array_slice($evaluations, 0, 20) as $e) {
            $badgeHuman = $e['requires_human_review'] 
                ? '<fg=yellow;options=bold>[⚠️ AUDITORÍA REQ]</>' 
                : '<fg=green>[✓ Conforme]</>';

            $classStyle = 'fg=white';
            if ($e['classification'] === 'apto') $classStyle = 'fg=green;options=bold';
            if ($e['classification'] === 'parcialmente_apto') $classStyle = 'fg=yellow;options=bold';
            if ($e['classification'] === 'no_apto') $classStyle = 'fg=red';

            $topTable[] = [
                $e['postulacion_id'],
                $e['nombre_completo'],
                $e['score_total'] . ' pts',
                '<' . $classStyle . '>' . mb_strtoupper($e['classification'], 'UTF-8') . '</>',
                $badgeHuman,
            ];
        }

        if (count($topTable) > 1) {
            $this->table($topTable[0], array_slice($topTable, 1));
        } else {
            $this->comment('No hay postulaciones evaluadas exitosamente.');
        }
        $this->info('============================================================');

        if ($write) {
            $this->info('🎉 ¡La evaluación determinística se ha ejecutado y guardado en MySQL!');
        } else {
            $this->info('💡 Simulación completada. No se grabaron cambios en MySQL. Usa --write para persistir.');
        }
        $this->info('============================================================');

        return 0;
    }
}
