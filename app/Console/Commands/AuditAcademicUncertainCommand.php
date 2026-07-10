<?php

namespace App\Console\Commands;

use App\Models\FormacionAcademica;
use App\Models\EvaluationResult;
use App\Models\Postulacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditAcademicUncertainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:audit-academic-uncertain';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit academic_match_uncertain reasons, detailing unnormalized careers and professional areas status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('============================================================');
        $this->info('        DIAGNÓSTICO ACADEMIC MATCH UNCERTAIN (SISPO)        ');
        $this->info('============================================================');

        // 1. Total academic_match_uncertain cases in EvaluationResults
        $evaluations = EvaluationResult::all();
        $uncertainCount = 0;
        foreach ($evaluations as $e) {
            $flags = $e->review_flags_json ?? [];
            if (in_array('academic_match_uncertain', $flags)) {
                $uncertainCount++;
            }
        }

        $totalEvaluations = $evaluations->count();
        $uncertainRatio = $totalEvaluations > 0 ? round(($uncertainCount / $totalEvaluations) * 100, 1) . '%' : '0%';

        $this->comment('📊 INCIDENCIA DEL FLAG DE INCERTIDUMBRE:');
        $this->table(
            ['Métrica de Diagnóstico', 'Valor'],
            [
                ['Total Postulaciones Evaluadas', $totalEvaluations],
                ['Casos con "academic_match_uncertain"', $uncertainCount . ' (' . $uncertainRatio . ')'],
            ]
        );
        $this->info('------------------------------------------------------------');

        // 2. Top 50 carrera_raw no normalizadas
        $this->comment('🏫 TOP 50 CARRERA_RAW NO NORMALIZADAS (career_id IS NULL):');
        $unnormalizedCareers = FormacionAcademica::whereNull('career_id')
            ->select('carrera_raw', DB::raw('count(*) as total'))
            ->groupBy('carrera_raw')
            ->orderByDesc('total')
            ->limit(50)
            ->get();

        $careersTable = [['Carrera Registrada (Raw)', 'Casos sin Normalizar']];
        foreach ($unnormalizedCareers as $c) {
            $careersTable[] = [$c->carrera_raw ?: '[Vacio/Null]', $c->total . ' postulantes'];
        }
        $this->table($careersTable[0], array_slice($careersTable, 1));
        $this->info('------------------------------------------------------------');

        // 3. Top 50 nivel_academico_raw de no normalizadas
        $this->comment('🎓 TOP 50 NIVEL_ACADEMICO_RAW NO NORMALIZADOS O SIN CORRESPONDENCIA:');
        $unnormalizedLevels = FormacionAcademica::whereNull('academic_level_id')
            ->select('nivel_academico_raw', DB::raw('count(*) as total'))
            ->groupBy('nivel_academico_raw')
            ->orderByDesc('total')
            ->limit(50)
            ->get();

        $levelsTable = [['Nivel Académico (Raw)', 'Frecuencia']];
        foreach ($unnormalizedLevels as $l) {
            $levelsTable[] = [$l->nivel_academico_raw ?: '[Vacio/Null]', $l->total . ' casos'];
        }
        $this->table($levelsTable[0], array_slice($levelsTable, 1));
        $this->info('------------------------------------------------------------');

        // 4. Convocatorias afectadas
        $this->comment('📋 TOP CONVOCATORIAS CON MAYOR INCIDENCIA:');
        $uncertainConvocatorias = [];
        foreach ($evaluations as $e) {
            $flags = $e->review_flags_json ?? [];
            if (in_array('academic_match_uncertain', $flags)) {
                $convId = $e->convocatoria_id;
                if (!isset($uncertainConvocatorias[$convId])) {
                    $uncertainConvocatorias[$convId] = 0;
                }
                $uncertainConvocatorias[$convId]++;
            }
        }
        arsort($uncertainConvocatorias);

        $convTable = [['ID Convocatoria', 'Vacante / Cargo', 'Postulaciones con Alerta']];
        foreach (array_slice($uncertainConvocatorias, 0, 10, true) as $convId => $count) {
            $postulacion = Postulacion::where('oferta_id', $convId)->with('oferta.cargo')->first();
            $cargo = $postulacion?->oferta?->cargo?->nombre ?? 'N/A';
            $convTable[] = [
                $convId,
                $cargo,
                $count . ' postulantes',
            ];
        }
        $this->table($convTable[0], array_slice($convTable, 1));
        $this->info('------------------------------------------------------------');

        // 5. Professional Area status (Fase 3 check)
        $this->comment('🔍 ESTADO ACTUAL DE NORMALIZACIÓN DE ÁREAS (professional_area_id):');
        
        $totalFormaciones = FormacionAcademica::count();
        $noCareerCount = FormacionAcademica::whereNull('career_id')->count();
        
        $noCareerButArea = FormacionAcademica::whereNull('career_id')
            ->whereNotNull('professional_area_id')
            ->count();

        $neitherCareerNorArea = FormacionAcademica::whereNull('career_id')
            ->whereNull('professional_area_id')
            ->count();

        $this->table(
            ['Métrica de Áreas Profesionales', 'Valor'],
            [
                ['Total Formaciones Académicas Registradas', $totalFormaciones],
                ['Formaciones sin "career_id" (Sin Carrera Normalizada)', $noCareerCount],
                ['Tienen "professional_area_id" pero NO "career_id"', $noCareerButArea . ' casos'],
                ['No tienen ni "career_id" ni "professional_area_id"', $neitherCareerNorArea . ' casos'],
            ]
        );
        $this->info('============================================================');

        return 0;
    }
}
