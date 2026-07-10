<?php

namespace App\Console\Commands;

use App\Models\Postulacion;
use App\Models\EvaluationResult;
use App\Models\ConvocatoriaScoreRule;
use App\Services\Evaluation\Score\SispoScoreEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SispoDebugScoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:debug-score {postulacion_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detailed surgical audit and score reconstruction for a given postulación';

    /**
     * Execute the console command.
     */
    public function handle(SispoScoreEngine $scoreEngine): int
    {
        $postulacionId = $this->argument('postulacion_id');
        $this->info('========================================================================');
        $this->info('           AUDITORÍA Y RECONSTRUCCIÓN DETALLADA DEL SCORING             ');
        $this->info('========================================================================');

        $postulacion = Postulacion::with([
            'postulante',
            'oferta.convocatoria',
            'oferta.cargo',
            'oferta.sede'
        ])->find($postulacionId);

        if (!$postulacion) {
            $this->error("Error: No se encontró la postulación con ID: {$postulacionId}");
            return 1;
        }

        $postulante = $postulacion->postulante;
        $convocatoria = $postulacion->oferta->convocatoria;

        $this->line("<fg=yellow;options=bold>1. DATOS BASE DEL CANDIDATO Y CONVOCATORIA</>");
        $this->line("  - Postulación ID  : {$postulacion->id}");
        $this->line("  - Postulante      : <fg=green;options=bold>{$postulante->nombres} {$postulante->apellidos}</> (ID: {$postulante->id}, CI: {$postulante->ci})");
        $this->line("  - Convocatoria    : <fg=cyan>{$convocatoria->titulo}</> (ID: {$convocatoria->id})");
        $this->line("  - Sede / Cargo    : {$postulacion->oferta->sede->nombre} | {$postulacion->oferta->cargo->nombre}");
        $this->line("");

        // Rules
        $rules = ConvocatoriaScoreRule::where('convocatoria_id', $convocatoria->id)->get();
        if ($rules->isEmpty()) {
            $this->warn("  - Advertencia: No hay reglas de baremo específicas cargadas. Se aplicarán las por defecto.");
        } else {
            $this->line("  - Reglas de Convocatoria Activas:");
            foreach ($rules as $rule) {
                $reqStr = $rule->is_required ? '<fg=red;options=bold>[OBLIGATORIO]</>' : '[OPCIONAL]';
                $this->line("    * {$rule->criterion_name} ({$rule->criterion_code}): Max {$rule->max_points} pts | Peso {$rule->weight}% | {$reqStr}");
            }
        }
        $this->line("");

        // Always run real-time evaluation to test latest gates and rules
        $this->info("  - Ejecutando recalculo determinístico en tiempo real con Integrity Gates...");
        $scoreEngine->evaluate($postulacionId, true);
        $eval = EvaluationResult::where('postulacion_id', $postulacionId)->first();

        $breakdown = $eval->score_breakdown_json;

        // FASE 2: FORMACIÓN ACADÉMICA
        $this->line("<fg=yellow;options=bold>2. FORMACIÓN ACADÉMICA</>");
        $fa = $breakdown['academic_formation'] ?? null;
        if ($fa) {
            $this->line("  - Score Obtenido : <fg=green;options=bold>{$fa['score']} / {$fa['max_points']} pts</>");
            $this->line("  - Justificación  : {$fa['reason']}");
            if (isset($fa['details'])) {
                $this->line("  - Detalles       : Carrera: " . ($fa['details']['carrera'] ?? 'N/D') . " | Nivel Máximo: " . ($fa['details']['nivel_maximo'] ?? 'N/D') . " | Relación Directa: " . (($fa['details']['relacion_directa'] ?? false) ? 'SÍ' : 'NO'));
            }
        } else {
            $this->line("  - Sin méritos registrados para Formación Académica.");
        }
        $this->line("");

        // FASE 3: POSTGRADO
        $this->line("<fg=yellow;options=bold>3. FORMACIÓN DE POSTGRADO</>");
        $pg = $breakdown['postgraduate'] ?? null;
        if ($pg) {
            $this->line("  - Score Obtenido : <fg=green;options=bold>{$pg['score']} / {$pg['max_points']} pts</>");
            $this->line("  - Justificación  : {$pg['reason']}");
            if (isset($pg['details']['programas'])) {
                foreach ($pg['details']['programas'] as $prog) {
                    $this->line("    * [{$prog['tipo']}] {$prog['nombre']} -> +{$prog['puntos']} pts");
                }
            }
        } else {
            $this->line("  - Sin méritos registrados para Postgrados.");
        }
        $this->line("");

        // FASE 4: EXPERIENCIA PROFESIONAL
        $this->line("<fg=yellow;options=bold>4. EXPERIENCIA PROFESIONAL (CON MITIGACIÓN DE SOLAPAMIENTOS)</>");
        $ep = $breakdown['professional_experience'] ?? null;
        if ($ep) {
            $this->line("  - Score Obtenido : <fg=green;options=bold>{$ep['score']} / {$ep['max_points']} pts</>");
            $this->line("  - Justificación  : {$ep['reason']}");
            if (isset($ep['details'])) {
                $det = $ep['details'];
                $solapado = ($det['solapamiento_detectado'] ?? false) ? '<fg=red;options=bold>SÍ</>' : 'NO';
                $this->line("  - Detalles       : Meses Únicos (Últimos 5 años): " . ($det['meses_unicos_last_5_years'] ?? '0') . " | Recencia: " . ($det['max_recency_score'] ?? '0') . "%");
                $this->line("  - Solapamientos  : Detectado: {$solapado} | Meses Solapados Mitigados: <fg=red;options=bold>" . ($det['meses_solapados_mitigados'] ?? '0') . " meses</>");
            }
        } else {
            $this->line("  - Sin méritos registrados para Experiencia Profesional.");
        }
        $this->line("");

        // FASE 5: CAPACITACIÓN Y CURSOS
        $this->line("<fg=yellow;options=bold>5. CAPACITACIÓN Y CURSOS</>");
        $tr = $breakdown['training'] ?? null;
        if ($tr) {
            $this->line("  - Score Obtenido : <fg=green;options=bold>{$tr['score']} / {$tr['max_points']} pts</>");
            $this->line("  - Justificación  : {$tr['reason']}");
            if (isset($tr['details'])) {
                $det = $tr['details'];
                $this->line("  - Detalles       : Horas Totales: " . ($det['total_horas'] ?? '0') . " hrs | Cursos: " . ($det['total_cursos'] ?? '0') . " | Horas Recientes: " . ($det['horas_ultimos_5_anios'] ?? '0') . " hrs");
            }
        } else {
            $this->line("  - Sin méritos registrados para Capacitación.");
        }
        $this->line("");

        // FASE 6: RECONOCIMIENTOS
        $this->line("<fg=yellow;options=bold>6. RECONOCIMIENTOS Y DISTINCIONES</>");
        $rec = $breakdown['recognitions'] ?? null;
        if ($rec) {
            $this->line("  - Score Obtenido : <fg=green;options=bold>{$rec['score']} / {$rec['max_points']} pts</>");
            $this->line("  - Justificación  : {$rec['reason']}");
        } else {
            $this->line("  - Sin reconocimientos registrados.");
        }
        $this->line("");

        // FASE 7: PRODUCCIÓN INTELECTUAL
        $this->line("<fg=yellow;options=bold>7. PRODUCCIÓN INTELECTUAL Y PUBLICACIONES</>");
        $pi = $breakdown['intellectual_production'] ?? null;
        if ($pi) {
            $this->line("  - Score Obtenido : <fg=green;options=bold>{$pi['score']} / {$pi['max_points']} pts</>");
            $this->line("  - Justificación  : {$pi['reason']}");
        } else {
            $this->line("  - Sin producción intelectual registrada.");
        }
        $this->line("");

        // INTEGRIDAD DOCUMENTAL DE RESPALDO (CRITICAL QUALITY GATE CHECK)
        $this->info('========================================================================');
        $this->line("<fg=red;options=bold>8. AUDITORÍA DE INTEGRIDAD DOCUMENTAL (RESPALDOS)</>");
        
        // 1. Legacy
        $rawMeritos = DB::table('postulante_meritos')->where('postulante_id', $postulante->id)->get();
        $totalMerits = count($rawMeritos);
        
        $meritosConArchivo = 0;
        $filesMetadata = [];
        
        foreach ($rawMeritos as $m) {
            $files = DB::table('merito_archivos')->where('merito_id', $m->id)->get();
            if ($files->isNotEmpty()) {
                $meritosConArchivo++;
                foreach ($files as $f) {
                    $filesMetadata[] = [
                        'source' => 'legacy_merito_archivos',
                        'archivo_path' => $f->archivo_path,
                        'name' => 'Legacy Doc'
                    ];
                }
            }
        }

        // 2. Normalized
        $normalizedDocs = [
            ['table' => 'formaciones_academicas', 'cols' => ['diploma_archivo_path', 'titulo_archivo_path']],
            ['table' => 'formaciones_postgrado', 'cols' => ['certificado_archivo_path']],
            ['table' => 'experiencias_profesionales', 'cols' => ['certificado_archivo_path']],
            ['table' => 'capacitaciones', 'cols' => ['certificado_archivo_path']],
            ['table' => 'experiencias_docencia', 'cols' => ['respaldo_archivo_path']],
            ['table' => 'producciones_intelectuales', 'cols' => ['evidencia_archivo_path']],
            ['table' => 'reconocimientos', 'cols' => ['reconocimiento_archivo_path']],
        ];

        $totalNormalizedMerits = 0;
        $normalizedConArchivo = 0;

        foreach ($normalizedDocs as $cfg) {
            $rows = DB::table($cfg['table'])->where('postulante_id', $postulante->id)->get();
            $totalNormalizedMerits += count($rows);
            foreach ($rows as $row) {
                $hasFile = false;
                foreach ($cfg['cols'] as $col) {
                    if (!empty($row->$col)) {
                        $hasFile = true;
                        $filesMetadata[] = [
                            'source' => $cfg['table'],
                            'archivo_path' => $row->$col,
                            'name' => $col
                        ];
                    }
                }
                if ($hasFile) {
                    $normalizedConArchivo++;
                }
            }
        }

        $grandTotalMerits = $totalMerits + $totalNormalizedMerits;
        $grandTotalConArchivo = $meritosConArchivo + $normalizedConArchivo;
        $completitud = $grandTotalMerits > 0 ? round(($grandTotalConArchivo / $grandTotalMerits) * 100, 2) : 100;
        
        $this->line("  - Méritos Declarados manualmente  : <fg=cyan>{$grandTotalMerits}</> (Legacy: {$totalMerits}, Normal: {$totalNormalizedMerits})");
        $this->line("  - Méritos con Documento de Respaldo: <fg=yellow>{$grandTotalConArchivo}</> (Legacy: {$meritosConArchivo}, Normal: {$normalizedConArchivo})");
        
        if ($completitud < 50) {
            $this->line("  - Grado de Completitud Documental : <fg=red;options=bold>{$completitud}%</> (CRÍTICO: Muy bajo nivel de respaldo)");
        } else {
            $this->line("  - Grado de Completitud Documental : <fg=green>{$completitud}%</>");
        }

        $this->line("  - Archivos Físicos en Storage:");
        if (empty($filesMetadata)) {
            $this->line("    <fg=red;options=bold>* ALERTA: EL POSTULANTE TIENE EXACTAMENTE 0 ARCHIVOS DE RESPALDO EN EL SERVIDOR.</>");
        } else {
            foreach ($filesMetadata as $f) {
                $path = storage_path('app/public/' . $f['archivo_path']);
                $exists = file_exists($path) ? '<fg=green>SÍ</>' : '<fg=red;options=bold>NO (Archivo Roto / Perdido)</>';
                $this->line("    * [{$f['source']}] Col: {$f['name']} | Path: {$f['archivo_path']} | Existe en Storage: {$exists}");
            }
        }
        $this->line("");

        // TOTALS & DECISION STRATIFICATION
        $this->info('========================================================================');
        $this->line("<fg=yellow;options=bold>9. DICTAMEN FINAL Y STRATIFICACIÓN DE RIESGO</>");
        $this->line("  - PUNTUACIÓN TOTAL   : <fg=green;options=bold>{$eval->score_total} %</>");
        $this->line("  - CLASIFICACIÓN FINAL: <fg=cyan;options=bold>" . strtoupper($eval->classification) . "</>");
        $this->line("  - NIVEL DE RIESGO RRHH: <fg=yellow;options=bold>" . strtoupper($eval->review_risk_level) . "</> (Score de riesgo: {$eval->review_risk_score}/100)");
        
        $flags = $eval->review_flags_json ?: [];
        $this->line("  - Banderas de Riesgo Detectadas:");
        if (empty($flags)) {
            $this->line("    * Ninguna bandera de riesgo detectada.");
        } else {
            foreach ($flags as $f) {
                $this->line("    * <fg=red>* {$f}</>");
            }
        }
        
        if ($completitud === 0.0) {
            $this->line("    * <fg=red;options=bold>* ALERTA CRÍTICA: FRAUDE DOCUMENTAL / CERO RESPALDOS CARGADOS</>");
        }

        $this->info('========================================================================');

        return 0;
    }
}
