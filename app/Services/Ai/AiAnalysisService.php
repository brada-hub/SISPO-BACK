<?php

namespace App\Services\Ai;

use App\Models\AiAuditLog;
use App\Models\AiCvAnalysis;
use App\Models\AiMatchingResult;
use App\Models\Convocatoria;
use App\Models\Postulacion;
use App\Models\Postulante;
use Illuminate\Support\Facades\Log;

/**
 * Main orchestrator for the AI analysis pipeline.
 *
 * Flow: DB Expediente → Context → Prompt → Gemini → Score → Persist
 *
 * This service coordinates all sub-services and handles error recovery.
 * It is designed to be called from a Job (async) or directly (sync for testing).
 */
class AiAnalysisService
{
    public function __construct(
        private PdfExtractorService $pdfExtractor,
        private GeminiClient        $gemini,
        private PromptBuilder       $promptBuilder,
        private MatchingEngine      $matchingEngine,
    ) {}

    /**
     * Run the full analysis pipeline for a single postulación.
     *
     * @param  int  $postulacionId
     * @param  AiPipelineTracker|null  $tracker  Optional tracker for observability
     * @return AiMatchingResult|null  The matching result, or null if analysis failed
     */
    public function analyzePostulacion(int $postulacionId, ?AiPipelineTracker $tracker = null): ?AiMatchingResult
    {
        $postulacion = Postulacion::with([
            'postulante.formacionesAcademicas',
            'postulante.formacionesPostgrado',
            'postulante.experienciasDocencia',
            'postulante.experienciasProfesionales',
            'postulante.capacitaciones',
            'postulante.produccionesIntelectuales',
            'postulante.reconocimientos',
            'oferta.cargo',
            'oferta.convocatoria.baremos.tipoDocumento',
        ])->findOrFail($postulacionId);

        $postulante   = $postulacion->postulante;
        $convocatoria = $postulacion->oferta->convocatoria;
        $cargo        = $postulacion->oferta->cargo;

        // Create tracker if not provided (for direct calls)
        if (!$tracker) {
            $postulanteName = trim(($postulante->nombres ?? '') . ' ' . ($postulante->apellidos ?? ''));
            $tracker = new AiPipelineTracker(
                $postulacionId,
                $postulanteName,
                $cargo->nombre ?? 'Cargo no especificado',
                $convocatoria->id ?? null,
            );
            $tracker->start();
        }

        try {
            // ── ETAPA 1: Build Context from DB ──────────────────────
            $t1 = microtime(true);
            $context = $this->buildAiEvaluationContext($postulacion);
            $t1ms = (int) round((microtime(true) - $t1) * 1000);

            $tracker->buildContext($t1ms, mb_strlen($context));

            // ── ETAPA 2: Build Prompt ───────────────────────────────
            $t2 = microtime(true);
            $promptData = $this->promptBuilder->buildConsolidatedEvaluationPrompt($context);
            $t2ms = (int) round((microtime(true) - $t2) * 1000);

            $tracker->buildPrompt($t2ms, $promptData['version'], mb_strlen($promptData['prompt']));

            // ── ETAPA 3: Send to Gemini ─────────────────────────────
            $tracker->callingGemini($this->gemini->getModel());

            $attempts = 0;
            $parsed = null;
            $aiResult = null;

            while ($attempts < 2) {
                try {
                    $aiResult = $this->gemini->analyze($promptData['prompt']);
                    $parsed = $aiResult['response'];

                    if (!isset($parsed['score_total']) || !isset($parsed['clasificacion'])) {
                        throw new \RuntimeException(
                            'Gemini devolvió un JSON incompleto (falta score_total o clasificacion). Keys: '
                            . implode(', ', array_keys($parsed))
                        );
                    }

                    $tracker->geminiResponse(
                        $aiResult['tokens_used'] ?? 0,
                        $aiResult['processing_ms'] ?? 0,
                    );
                    break; // Success

                } catch (\RuntimeException $e) {
                    // Do NOT retry on quota or auth errors — they won't resolve
                    if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), '403')) {
                        throw $e;
                    }
                    $attempts++;
                    Log::warning("[AI] Attempt {$attempts}/2 failed (retryable): " . $e->getMessage());
                    if ($attempts >= 2) throw $e;
                    sleep(3);
                } catch (\Exception $e) {
                    $attempts++;
                    Log::warning("[AI] Attempt {$attempts}/2 failed: " . $e->getMessage());
                    if ($attempts >= 2) throw $e;
                    sleep(3);
                }
            }

            // ── ETAPA 4: Parse & validate score ─────────────────────
            $tracker->parsingResponse(
                (float) $parsed['score_total'],
                $parsed['clasificacion'],
            );

            // ── ETAPA 5: Persist to MySQL ───────────────────────────
            $analysis = AiCvAnalysis::updateOrCreate(
                ['postulante_id' => $postulante->id, 'postulacion_id' => $postulacionId],
                [
                    'status'                => 'completed',
                    'raw_text'              => $context,
                    'text_extraction_method' => 'db_consolidated',
                    'text_length'           => mb_strlen($context),
                    'ai_response'           => $parsed,
                    'ai_provider'           => 'gemini',
                    'ai_model'              => $this->gemini->getModel(),
                    'prompt_version'        => $promptData['version'],
                    'tokens_used'           => $aiResult['tokens_used'],
                    'processing_time_ms'    => $aiResult['processing_ms'],
                ]
            );

            AiAuditLog::logAnalysis($analysis, $promptData['prompt'], $promptData['version'], $aiResult['raw']);

            $matching = AiMatchingResult::updateOrCreate(
                ['postulacion_id' => $postulacionId],
                [
                    'ai_cv_analysis_id'    => $analysis->id,
                    'convocatoria_id'      => $convocatoria->id,
                    'score_formacion'      => 0,
                    'score_experiencia'    => 0,
                    'score_habilidades'    => 0,
                    'score_requisitos'     => 0,
                    'score_total'          => min(100, max(0, (float) $parsed['score_total'])),
                    'clasificacion_ia'     => $parsed['clasificacion'],
                    'observaciones_ia'     => $parsed['observaciones_ia'] ?? ($parsed['observaciones'] ?? ''),
                    'fortalezas'           => is_array($parsed['fortalezas'] ?? null) ? $parsed['fortalezas'] : [],
                    'debilidades'          => is_array($parsed['brechas'] ?? null) ? $parsed['brechas'] : [],
                    'pesos_aplicados'      => ['justificacion' => $parsed['justificacion_score'] ?? ''],
                ]
            );

            AiAuditLog::logMatching($matching);

            $tracker->savingResults($matching->id, (float) $matching->score_total);
            $tracker->finished($tracker->getElapsedMs());

            return $matching;

        } catch (\Throwable $e) {
            $tracker->failed($e);

            // Mark as failed in AiCvAnalysis
            AiCvAnalysis::updateOrCreate(
                ['postulante_id' => $postulante->id, 'postulacion_id' => $postulacionId],
                ['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 500)]
            );

            // Re-throw so the Queue Worker registers it as a FAILED JOB
            throw $e;
        }
    }

    private function buildAiEvaluationContext(Postulacion $postulacion): string
    {
        $postulante = $postulacion->postulante;
        $convocatoria = $postulacion->oferta->convocatoria;
        $cargo = $postulacion->oferta->cargo;

        $context = "--- DATOS DE LA CONVOCATORIA ---\n";
        $context .= "Cargo: " . $cargo->nombre . "\n";
        $context .= "Descripción: " . $cargo->descripcion . "\n";
        
        $requisitosIds = $convocatoria->config_requisitos_ids ?? [];
        $requisitos = 'Sin requisitos específicos';
        if (!empty($requisitosIds)) {
            $reqs = \App\Models\TipoDocumento::whereIn('id', $requisitosIds)->pluck('nombre')->toArray();
            $requisitos = implode(", ", $reqs);
        }
        $context .= "Requisitos exigidos: " . $requisitos . "\n\n";

        $context .= "--- DATOS DEL POSTULANTE ---\n";
        $context .= "Nombre: " . $postulante->nombres . " " . $postulante->apellidos . "\n";
        $context .= "Pretensión Salarial: " . ($postulacion->pretension_salarial ?: 'No especificada') . "\n";
        $context .= "Motivación para el cargo: " . ($postulacion->porque_cargo ?: 'No especificada') . "\n\n";

        $context .= "--- EXPEDIENTE ACADÉMICO Y PROFESIONAL ESTRUCTURADO ---\n";
        
        $hasMeritos = false;

        // 1. Formaciones Académicas
        if ($postulante->formacionesAcademicas && $postulante->formacionesAcademicas->isNotEmpty()) {
            $hasMeritos = true;
            $context .= ">> FORMACIÓN ACADÉMICA:\n";
            foreach ($postulante->formacionesAcademicas as $f) {
                $context .= "  - Nivel Académico: " . ($f->nivel_academico_normalizado ?: $f->nivel_academico_raw) . "\n";
                $context .= "    Carrera/Profesión: " . $f->carrera_raw . "\n";
                $context .= "    Universidad/Institución: " . $f->universidad . "\n";
                if ($f->fecha_diploma) $context .= "    Fecha Diploma: " . $f->fecha_diploma->format('Y-m-d') . "\n";
                if ($f->fecha_titulo) $context .= "    Fecha Título Provisión: " . $f->fecha_titulo->format('Y-m-d') . "\n";
                $context .= "\n";
            }
        }

        // 2. Formaciones Posgrado
        if ($postulante->formacionesPostgrado && $postulante->formacionesPostgrado->isNotEmpty()) {
            $hasMeritos = true;
            $context .= ">> FORMACIÓN EN POSGRADO:\n";
            foreach ($postulante->formacionesPostgrado as $fp) {
                $context .= "  - Programa: " . $fp->nombre_programa . "\n";
                $context .= "    Tipo Posgrado: " . ($fp->tipo_posgrado_normalizado ?: $fp->tipo_posgrado_raw) . "\n";
                $context .= "    Institución: " . $fp->institucion . "\n";
                if ($fp->fecha_certificacion) $context .= "    Fecha Certificación: " . $fp->fecha_certificacion->format('Y-m-d') . "\n";
                $context .= "\n";
            }
        }

        // 3. Experiencias Docencia
        if ($postulante->experienciasDocencia && $postulante->experienciasDocencia->isNotEmpty()) {
            $hasMeritos = true;
            $context .= ">> EXPERIENCIA EN DOCENCIA:\n";
            foreach ($postulante->experienciasDocencia as $ed) {
                $context .= "  - Universidad/Institución: " . $ed->universidad . "\n";
                $context .= "    Carrera: " . $ed->carrera_raw . "\n";
                $context .= "    Asignaturas dictadas: " . $ed->asignaturas . "\n";
                $context .= "    Gestión/Período: " . $ed->gestion_periodo . "\n";
                $context .= "\n";
            }
        }

        // 4. Experiencias Profesionales
        if ($postulante->experienciasProfesionales && $postulante->experienciasProfesionales->isNotEmpty()) {
            $hasMeritos = true;
            $context .= ">> EXPERIENCIA PROFESIONAL:\n";
            foreach ($postulante->experienciasProfesionales as $ep) {
                $context .= "  - Cargo Desempeñado: " . $ep->cargo_raw . "\n";
                $context .= "    Empresa/Institución: " . $ep->empresa . "\n";
                if ($ep->fecha_inicio) $context .= "    Fecha Inicio: " . $ep->fecha_inicio->format('Y-m-d') . "\n";
                if ($ep->fecha_fin) $context .= "    Fecha Fin: " . $ep->fecha_fin->format('Y-m-d') . "\n";
                if ($ep->duracion_meses !== null) $context .= "    Duración: " . $ep->duracion_meses . " meses\n";
                $context .= "\n";
            }
        }

        // 5. Capacitaciones
        if ($postulante->capacitaciones && $postulante->capacitaciones->isNotEmpty()) {
            $hasMeritos = true;
            $context .= ">> CAPACITACIONES:\n";
            foreach ($postulante->capacitaciones as $cap) {
                $context .= "  - Curso/Seminario: " . $cap->nombre_curso . "\n";
                $context .= "    Institución Organizadora: " . $cap->institucion_organizadora . "\n";
                if ($cap->carga_horaria) $context .= "    Carga Horaria: " . $cap->carga_horaria . " horas\n";
                if ($cap->fecha) $context .= "    Fecha: " . $cap->fecha->format('Y-m-d') . "\n";
                $context .= "\n";
            }
        }

        // 6. Producciones Intelectuales
        if ($postulante->produccionesIntelectuales && $postulante->produccionesIntelectuales->isNotEmpty()) {
            $hasMeritos = true;
            $context .= ">> PRODUCCIÓN INTELECTUAL:\n";
            foreach ($postulante->produccionesIntelectuales as $pi) {
                $context .= "  - Título: " . $pi->titulo . "\n";
                $context .= "    Tipo: " . ($pi->tipo_produccion_normalizado ?: $pi->tipo_produccion_raw) . "\n";
                $context .= "    Editorial/Revista: " . $pi->editorial_revista . "\n";
                if ($pi->lugar) $context .= "    Lugar: " . $pi->lugar . "\n";
                if ($pi->fecha_publicacion) $context .= "    Fecha Publicación: " . $pi->fecha_publicacion->format('Y-m-d') . "\n";
                $context .= "\n";
            }
        }

        // 7. Reconocimientos
        if ($postulante->reconocimientos && $postulante->reconocimientos->isNotEmpty()) {
            $hasMeritos = true;
            $context .= ">> RECONOCIMIENTOS:\n";
            foreach ($postulante->reconocimientos as $rec) {
                $context .= "  - Reconocimiento: " . $rec->titulo_reconocimiento . "\n";
                $context .= "    Institución Otorgante: " . $rec->institucion_otorgante . "\n";
                if ($rec->lugar) $context .= "    Lugar: " . $rec->lugar . "\n";
                if ($rec->fecha) $context .= "    Fecha: " . $rec->fecha->format('Y-m-d') . "\n";
                $context .= "\n";
            }
        }

        if (!$hasMeritos) {
            $context .= "EL POSTULANTE NO REGISTRÓ NINGÚN MÉRITO (Cero experiencia, cero formación en DB).\n\n";
        }

        return $context;
    }

    /**
     * Force a complete reanalysis of a postulación.
     */
    public function reanalyze(int $postulacionId): ?AiMatchingResult
    {
        $postulacion = Postulacion::findOrFail($postulacionId);

        AiCvAnalysis::where('postulacion_id', $postulacionId)->delete();
        AiMatchingResult::where('postulacion_id', $postulacionId)->delete();

        return $this->analyzePostulacion($postulacionId);
    }

    /**
     * Extract requirements from a convocatoria as a simple list for the AI prompt.
     */
    private function extractRequisitos(Convocatoria $convocatoria): array
    {
        $requisitosIds = $convocatoria->config_requisitos_ids ?? [];

        if (empty($requisitosIds)) {
            return [['nombre' => 'Sin requisitos definidos', 'categoria' => 'general']];
        }

        return \App\Models\TipoDocumento::whereIn('id', $requisitosIds)
            ->get(['nombre', 'categoria', 'descripcion'])
            ->map(fn ($td) => [
                'nombre'      => $td->nombre,
                'categoria'   => $td->categoria,
                'descripcion' => $td->descripcion,
            ])
            ->toArray();
    }

    /**
     * Pre-validate text before spending API credits to avoid garbage/hallucinations.
     */
    private function validateExtractedText(string $text): ?string
    {
        $length = mb_strlen($text);
        
        // 1. Minimum length
        if ($length < 150) {
            return 'El texto extraído es demasiado corto (posible PDF escaneado sin OCR).';
        }
        
        // 2. Repetitive text detection
        $words = preg_split('/\s+/', strtolower($text));
        $totalWords = count($words);
        if ($totalWords > 50) {
            $uniqueWords = count(array_unique($words));
            if (($uniqueWords / $totalWords) < 0.2) {
                return 'El texto detectado parece ser basura o altamente repetitivo.';
            }
        }
        
        // 3. Structure detection (must contain at least 2 common CV keywords)
        $keywords = ['experiencia', 'educación', 'formación', 'estudios', 'universidad', 'trabajo', 'conocimientos', 'habilidades', 'bachiller', 'licenciatura', 'diplomado', 'curso', 'cargo', 'referencia', 'perfil'];
        $foundKeywords = 0;
        $lowerText = strtolower($text);
        
        foreach ($keywords as $kw) {
            if (str_contains($lowerText, $kw)) {
                $foundKeywords++;
            }
        }
        
        if ($foundKeywords < 2) {
            return 'El documento no contiene palabras clave básicas que identifiquen un Curriculum Vitae válido.';
        }
        
        return null; // Valid
    }

    /**
     * Check if AI analysis is available (API configured).
     */
    public function isAvailable(): bool
    {
        return $this->gemini->isAvailable() && config('services.gemini.enabled', false);
    }
}
