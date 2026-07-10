<?php

namespace App\Console\Commands;

use App\Jobs\ProcessProfessionalExperienceTemporalDataJob;
use App\Jobs\ProcessTeachingExperienceTemporalDataJob;
use App\Models\ExperienciaProfesional;
use App\Models\ExperienciaDocencia;
use App\Models\Postulante;
use App\Services\Evaluation\Temporal\TemporalScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessTemporalExperienceDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:process-temporal-data
                            {--dry-run : Simulate temporal calculations without saving}
                            {--write : Write the temporal metrics and recency scores to DB}
                            {--only= : Filter execution. Options: professional, teaching}
                            {--limit= : Limit the number of records to process}
                            {--postulante_id= : Filter by a specific postulante ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform deterministic temporal calculations and recency scoring on experiences';

    /**
     * Execute the console command.
     */
    public function handle(TemporalScoringService $scoringService): int
    {
        $write = $this->option('write');
        $dryRun = $this->option('dry-run');
        $only = $this->option('only');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $postulanteId = $this->option('postulante_id') ? (int) $this->option('postulante_id') : null;

        if (!$write && !$dryRun) {
            $this->error('Debes especificar --write o --dry-run para ejecutar el procesamiento temporal.');
            $this->info('Ejemplo: php artisan sispo:process-temporal-data --dry-run');
            return 1;
        }

        $this->info('============================================================');
        $this->info('        INICIANDO CÁLCULO TEMPORAL DE EXPERIENCIA           ');
        $this->info('============================================================');
        $this->info('Modo de ejecución: ' . ($write ? '<fg=green;options=bold>ESCRITURA REAL</>' : '<fg=yellow;options=bold>SIMULACIÓN (DRY-RUN)</>'));
        if ($only) {
            $this->info('Filtro sección: ' . $only);
        }
        if ($postulanteId) {
            $this->info('Filtrando postulante ID: ' . $postulanteId);
        }
        if ($limit) {
            $this->info('Límite de registros: ' . $limit);
        }
        $this->info('Fecha Referencia de Recencia: ' . $scoringService->getReferenceDate()->format('Y-m-d'));
        $this->info('------------------------------------------------------------');

        $profStats = null;
        $teachStats = null;

        // 1. Process Professional Experience
        if (!$only || $only === 'professional') {
            $this->comment('Procesando Experiencia Profesional...');

            $query = ExperienciaProfesional::query();
            if ($postulanteId) {
                $query->where('postulante_id', $postulanteId);
            }
            if ($limit) {
                $query->limit($limit);
            }
            $recordIds = $query->pluck('id')->toArray();

            $job = new ProcessProfessionalExperienceTemporalDataJob($recordIds, $write);
            $profStats = $job->handle($scoringService);
        }

        // 2. Process Teaching Experience
        if (!$only || $only === 'teaching') {
            $this->comment('Procesando Experiencia en Docencia...');

            $query = ExperienciaDocencia::query();
            if ($postulanteId) {
                $query->where('postulante_id', $postulanteId);
            }
            if ($limit) {
                $query->limit($limit);
            }
            $recordIds = $query->pluck('id')->toArray();

            $job = new ProcessTeachingExperienceTemporalDataJob($recordIds, $write);
            $teachStats = $job->handle($scoringService);
        }

        // Output Reports
        $this->info('============================================================');
        $this->info('                  AUDITORÍA TEMPORAL GLOVAL                 ');
        $this->info('============================================================');

        $reports = [];
        if ($profStats) {
            $avgTotal = $profStats['processed'] > 0 ? round($profStats['months_total_sum'] / $profStats['processed'], 1) : 0;
            $avgRecent = $profStats['processed'] > 0 ? round($profStats['months_5_years_sum'] / $profStats['processed'], 1) : 0;

            $reports[] = ['Métrica (Exp. Profesional)', 'Valor'];
            $reports[] = ['Total Analizados', $profStats['total']];
            $reports[] = ['Procesados Exitosamente', $profStats['processed']];
            $reports[] = ['Vigentes (Actualmente trabajando)', $profStats['currents']];
            $reports[] = ['Recientes (Score >= 70)', $profStats['recents']];
            $reports[] = ['Promedio Duración Total (Meses)', $avgTotal];
            $reports[] = ['Promedio Duración en Últimos 5 Años (Meses)', $avgRecent];
            $reports[] = ['Errores de Parsing de Fechas', $profStats['errors']];
        }
        if (!empty($reports)) {
            $this->table($reports[0], array_slice($reports, 1));
            $this->info('------------------------------------------------------------');
        }

        $teachReports = [];
        if ($teachStats) {
            $teachReports[] = ['Métrica (Exp. Docente)', 'Valor'];
            $teachReports[] = ['Total Analizados', $teachStats['total']];
            $teachReports[] = ['Procesados Exitosamente', $teachStats['processed']];
            $teachReports[] = ['Recientes en Últimos 5 Años (Score >= 70)', $teachStats['recents']];
            $teachReports[] = ['Errores de Parsing / Períodos Docentes Inválidos', $teachStats['errors']];

            $this->table($teachReports[0], array_slice($teachReports, 1));
            
            // Show invalid periods if any
            if (!empty($teachStats['parsing_failed_periods'])) {
                $this->warn('⚠️ PERÍODOS DOCENTES QUE NO SE PUDIERON PARSEAR:');
                $invalidTable = [['ID Registro', 'Período Original (gestion_periodo)']];
                foreach (array_slice($teachStats['parsing_failed_periods'], 0, 10) as $err) {
                    $invalidTable[] = [$err['id'], $err['periodo']];
                }
                $this->table($invalidTable[0], array_slice($invalidTable, 1));
                if (count($teachStats['parsing_failed_periods']) > 10) {
                    $this->comment('... y ' . (count($teachStats['parsing_failed_periods']) - 10) . ' registros de docencia más fallidos.');
                }
            }
            $this->info('------------------------------------------------------------');
        }

        // Top Postulantes con mayor experiencia
        if ($write && (!$only || $only === 'professional')) {
            $this->info('🏆 TOP 5 POSTULANTES CON MAYOR EXPERIENCIA RECIENTE VIGENTE:');
            
            $topApplicants = ExperienciaProfesional::select(
                    'postulante_id',
                    DB::raw("CONCAT(postulantes.nombres, ' ', postulantes.apellidos) as nombre_completo"),
                    DB::raw('SUM(months_in_last_5_years) as meses_recientes'),
                    DB::raw('SUM(months_total) as meses_totales'),
                    DB::raw('MAX(recency_score) as score_max_recencia')
                )
                ->join('postulantes', 'experiencias_profesionales.postulante_id', '=', 'postulantes.id')
                ->groupBy('postulante_id', 'postulantes.nombres', 'postulantes.apellidos')
                ->orderBy('meses_recientes', 'desc')
                ->limit(5)
                ->get();

            $topTable = [['Postulante ID', 'Nombre Postulante', 'Meses en Últimos 5 Años', 'Meses Totales', 'Máximo Score de Recencia']];
            foreach ($topApplicants as $ta) {
                $topTable[] = [
                    $ta->postulante_id,
                    $ta->nombre_completo,
                    $ta->meses_recientes . ' meses',
                    $ta->meses_totales . ' meses',
                    $ta->score_max_recencia . ' pts'
                ];
            }
            
            if (count($topTable) > 1) {
                $this->table($topTable[0], array_slice($topTable, 1));
            } else {
                $this->comment('No hay datos agregados disponibles todavía.');
            }
            $this->info('============================================================');
        }

        if ($write) {
            $this->info('🎉 ¡El procesamiento temporal y score de recencia se han guardado en MySQL!');
        } else {
            $this->info('💡 Simulación completada. No se grabaron cambios en MySQL. Usa --write para persistir.');
        }
        $this->info('============================================================');

        return 0;
    }
}
