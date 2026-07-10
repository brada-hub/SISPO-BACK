<?php

namespace App\Services\Evaluation\Score;

use App\Models\Postulante;
use App\Models\Postulacion;
use App\Models\ConvocatoriaScoreRule;
use App\Models\EvaluationResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SispoScoreEngine
{
    public function __construct(
        protected AcademicScoreCalculator $academicCalculator,
        protected PostgraduateScoreCalculator $postgradCalculator,
        protected ProfessionalExperienceScoreCalculator $experienceCalculator,
        protected TeachingExperienceScoreCalculator $teachingCalculator,
        protected TrainingScoreCalculator $trainingCalculator,
        protected IntellectualProductionScoreCalculator $intellectualCalculator,
        protected RecognitionScoreCalculator $recognitionCalculator,
        protected FinalClassificationResolver $classResolver
    ) {}

    /**
     * Get or create default score rules for a convocatoria.
     */
    public function getOrCreateRules(int $convocatoriaId): array
    {
        $existing = ConvocatoriaScoreRule::where('convocatoria_id', $convocatoriaId)
                                         ->where('is_enabled', true)
                                         ->get();

        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        // Create default rules for HRTech UNITEPC standard
        $defaults = [
            [
                'criterion_code' => 'academic_formation',
                'criterion_name' => 'Formación Académica',
                'weight'         => 25.00,
                'max_points'     => 25.00,
            ],
            [
                'criterion_code' => 'postgraduate',
                'criterion_name' => 'Formación de Postgrado',
                'weight'         => 15.00,
                'max_points'     => 15.00,
            ],
            [
                'criterion_code' => 'professional_experience',
                'criterion_name' => 'Experiencia Profesional',
                'weight'         => 30.00,
                'max_points'     => 30.00,
            ],
            [
                'criterion_code' => 'teaching_experience',
                'criterion_name' => 'Experiencia en Docencia',
                'weight'         => 15.00,
                'max_points'     => 15.00,
            ],
            [
                'criterion_code' => 'training',
                'criterion_name' => 'Capacitación y Cursos',
                'weight'         => 5.00,
                'max_points'     => 5.00,
            ],
            [
                'criterion_code' => 'intellectual_production',
                'criterion_name' => 'Producción Intelectual y Publicaciones',
                'weight'         => 5.00,
                'max_points'     => 5.00,
            ],
            [
                'criterion_code' => 'recognitions',
                'criterion_name' => 'Reconocimientos y Distinciones',
                'weight'         => 5.00,
                'max_points'     => 5.00,
            ],
        ];

        $rules = [];
        foreach ($defaults as $d) {
            $rule = ConvocatoriaScoreRule::create(array_merge($d, [
                'convocatoria_id' => $convocatoriaId,
                'is_required'     => false,
                'is_enabled'      => true,
            ]));
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * Run evaluation for a given Postulación.
     *
     * @param int $postulacionId
     * @param bool $write
     * @return array Evaluation results metadata
     */
    public function evaluate(int $postulacionId, bool $write = false): array
    {
        $postulacion = Postulacion::with([
            'oferta',
            'postulante.formacionesAcademicas.academicLevel',
            'postulante.formacionesPostgrado.postgraduateType',
            'postulante.experienciasProfesionales',
            'postulante.experienciasDocencia',
            'postulante.capacitaciones',
            'postulante.produccionesIntelectuales',
            'postulante.reconocimientos',
            'postulante.experienceSummary',
            'postulante.trainingSummary',
        ])->findOrFail($postulacionId);

        $postulante = $postulacion->postulante;
        $convocatoriaId = $postulacion->oferta ? $postulacion->oferta->convocatoria_id : $postulacion->oferta_id;

        // Load or create rules
        $rules = $this->getOrCreateRules($convocatoriaId);

        $breakdown = [];
        $scoreTotal = 0.0;
        $failedRequirements = [];
        $strengths = [];
        $weaknesses = [];

        // Track confidence averages for AI review rules
        $confidenceSum = 0.0;
        $confidenceCount = 0;

        foreach ($rules as $rule) {
            $code = $rule->criterion_code;
            $res = [
                'criterion_code' => $code,
                'criterion_name' => $rule->criterion_name,
                'score'          => 0.0,
                'max_points'     => (float) $rule->max_points,
                'details'        => [],
            ];

            switch ($code) {
                case 'academic_formation':
                    $calc = $this->academicCalculator->calculate($postulante, $rule);
                    $res = array_merge($res, $calc);
                    break;
                case 'postgraduate':
                    $calc = $this->postgradCalculator->calculate($postulante, $rule);
                    $res = array_merge($res, $calc);
                    break;
                case 'professional_experience':
                    $calc = $this->experienceCalculator->calculate($postulante, $rule);
                    $res = array_merge($res, $calc);
                    break;
                case 'teaching_experience':
                    $calc = $this->teachingCalculator->calculate($postulante, $rule);
                    $res = array_merge($res, $calc);
                    break;
                case 'training':
                    $calc = $this->trainingCalculator->calculate($postulante, $rule);
                    $res = array_merge($res, $calc);
                    break;
                case 'intellectual_production':
                    $calc = $this->intellectualCalculator->calculate($postulante, $rule);
                    $res = array_merge($res, $calc);
                    break;
                case 'recognitions':
                    $calc = $this->recognitionCalculator->calculate($postulante, $rule);
                    $res = array_merge($res, $calc);
                    break;
            }

            $scoreTotal += $res['score'];
            $breakdown[$code] = $res;

            // Formulate Strengths & Weaknesses
            if ($res['score'] >= ($res['max_points'] * 0.80)) {
                $strengths[] = "Excelente desempeño en " . $rule->criterion_name . " (" . $res['score'] . " / " . $res['max_points'] . " pts)";
            } elseif ($res['score'] <= ($res['max_points'] * 0.40)) {
                $weaknesses[] = "Bajo desempeño o falta de méritos en " . $rule->criterion_name . " (" . $res['score'] . " / " . $res['max_points'] . " pts)";
            }
        }

        // Classify base score
        $classification = $this->classResolver->resolve($scoreTotal);

        // Run ReviewDecisionService deterministically
        $decisionService = new \App\Services\Evaluation\Review\ReviewDecisionService();
        $auditResult = $decisionService->audit($postulante, $scoreTotal, $breakdown);

        $requiresHuman = $auditResult['requires_human_review'];
        $riskScore     = $auditResult['review_risk_score'];
        $riskLevel     = $auditResult['review_risk_level'];
        $reviewFlags   = $auditResult['review_flags'] ?? [];
        $reasonSummary = $auditResult['review_reason_summary'];
        $evalStatus    = $auditResult['evaluation_status'];

        // --- DEEPMIND ADVANCED INTEGRITY GATES & SCORE INVALIDATION RULE ---
        $gateResult = $this->checkIntegrityGates($postulante, $rules, $breakdown);
        $completitud = $gateResult['completitud_documental'];
        $failedMerits = $gateResult['failed_required_merits'];
        $failedDocs = $gateResult['failed_required_documents'];

        $gateFailed = !$gateResult['gate_passed'];
        $zeroFiles = ($completitud === 0.0 && $gateResult['total_meritos'] > 0);

        if ($gateFailed || $zeroFiles) {
            // Force human audit review and flag high risk due to compliance
            $requiresHuman = true;
            $riskScore     = max($riskScore, $zeroFiles ? 100.00 : 85.00);
            $riskLevel     = $zeroFiles ? 'critical' : 'high';
            $evalStatus    = 'requires_human_review';

            if ($zeroFiles) {
                $reviewFlags[] = 'zero_supporting_files';
                $reviewFlags[] = 'missing_required_document';
                $reasonSummary = "[CONTROL DE INTEGRIDAD: CERO RESPALDOS CARGADOS (0% COMPLETITUD)] El candidato declaró {$gateResult['total_meritos']} méritos manualmente pero no subió ningún archivo de respaldo físico. " . $reasonSummary;
                // Invalidate classification to no_apto automatically due to document fraud/incompleteness
                $classification = 'no_apto';
            } else {
                $reviewFlags[] = 'low_document_completeness';
                $reasonSummary = "[CONTROL DE INTEGRIDAD: BAJO RESPALDO ({$completitud}%)] Pocos méritos cuentan con archivos de respaldo cargados. " . $reasonSummary;
            }

            foreach ($failedMerits as $fm) {
                $reviewFlags[] = 'failed_required_merit_' . $fm['code'];
                $reasonSummary = "[REQUISITO INCUMPLIDO] {$fm['reason']} " . $reasonSummary;
                $classification = 'no_apto'; // Invalidate to no_apto if required merit is missing
            }

            foreach ($failedDocs as $fd) {
                $reviewFlags[] = 'failed_required_document_' . $fd['code'];
                $reviewFlags[] = 'missing_required_document';
                $reasonSummary = "[RESPALDO INCUMPLIDO] {$fd['reason']} " . $reasonSummary;
                $classification = 'no_apto'; // Invalidate to no_apto if required document is missing
            }
        }

        $resultPayload = [
            'postulacion_id'           => $postulacionId,
            'convocatoria_id'          => $convocatoriaId,
            'evaluation_mode'          => 'deterministic',
            'score_total'              => min(100.00, round($scoreTotal, 2)),
            'classification'           => $classification,
            'requires_ai_review'       => false, // Omit IA automatic reviews
            'requires_human_review'    => $requiresHuman,
            'review_risk_score'        => $riskScore,
            'review_risk_level'        => $riskLevel,
            'review_flags_json'        => array_values(array_unique($reviewFlags)),
            'review_reason_summary'    => $reasonSummary,
            'evaluation_status'        => $evalStatus,
            'score_breakdown_json'     => $breakdown,
            'failed_requirements_json' => [
                'flags'             => array_values(array_unique($reviewFlags)),
                'failed_merits'     => $failedMerits,
                'failed_documents'  => $failedDocs,
                'completitud'       => $completitud,
            ],
            'strengths_json'           => $strengths,
            'weaknesses_json'          => $weaknesses,
        ];

        if ($write) {
            EvaluationResult::updateOrCreate(
                ['postulacion_id' => $postulacionId],
                array_merge($resultPayload, ['evaluated_at' => Carbon::now()])
            );
        }

        return $resultPayload;
    }

    /**
     * Check Quality and Integrity Gates: Required merits, required documents, and global completitud.
     */
    public function checkIntegrityGates(Postulante $postulante, array $rules, array $breakdown): array
    {
        $failedRequiredMerits = [];
        $failedRequiredDocuments = [];

        // 1. Calculate overall completitud documental
        $rawMeritos = DB::table('postulante_meritos')->where('postulante_id', $postulante->id)->get();
        $totalMerits = count($rawMeritos);
        $meritosConArchivo = 0;
        
        foreach ($rawMeritos as $m) {
            $hasFile = $this->hasFileRespaldo($m->id, $m->tipo_documento_id);
            if ($hasFile) {
                $meritosConArchivo++;
            }
        }

        $completitud = $totalMerits > 0 ? round(($meritosConArchivo / $totalMerits) * 100, 2) : 100;

        // 2. Validate criteria gates
        foreach ($rules as $rule) {
            if ($rule->is_required) {
                $code = $rule->criterion_code;
                $score = $breakdown[$code]['score'] ?? 0.0;

                // Required Merit Gate: Candidate must score > 0 on this mandatory criterion
                if ($score <= 0.0) {
                    $failedRequiredMerits[] = [
                        'code' => $code,
                        'name' => $rule->criterion_name,
                        'reason' => "No se registraron méritos válidos para el criterio obligatorio '{$rule->criterion_name}'."
                    ];
                }

                // Required Document Gate: Registered merits for this mandatory criterion must have at least one file
                $mappedTipoId = $this->mapCriterionToTipoDocumento($code);
                if ($mappedTipoId) {
                    $categoryMerits = $rawMeritos->where('tipo_documento_id', $mappedTipoId);
                    if ($categoryMerits->isNotEmpty()) {
                        $hasAnyFile = false;
                        foreach ($categoryMerits as $cm) {
                            if ($this->hasFileRespaldo($cm->id, $cm->tipo_documento_id)) {
                                $hasAnyFile = true;
                                break;
                            }
                        }
                        if (!$hasAnyFile) {
                            $failedRequiredDocuments[] = [
                                'code' => $code,
                                'name' => $rule->criterion_name,
                                'reason' => "Falta cargar el documento de respaldo obligatorio para el criterio '{$rule->criterion_name}'."
                            ];
                        }
                    }
                }
            }
        }

        $gatePassed = empty($failedRequiredMerits) && empty($failedRequiredDocuments) && ($completitud >= 20.0);

        return [
            'gate_passed' => $gatePassed,
            'completitud_documental' => $completitud,
            'total_meritos' => $totalMerits,
            'meritos_con_archivo' => $meritosConArchivo,
            'failed_required_merits' => $failedRequiredMerits,
            'failed_required_documents' => $failedRequiredDocuments,
        ];
    }

    /**
     * Map criterion code to TipoDocumento ID based on standard UNITEPC baremo.
     */
    private function mapCriterionToTipoDocumento(string $code): ?int
    {
        return match ($code) {
            'academic_formation' => 1,
            'postgraduate' => 2,
            'professional_experience' => 4,
            'teaching_experience' => 3,
            'training' => 5,
            'intellectual_production' => 6,
            'recognitions' => 7,
            default => null,
        };
    }

    /**
     * Check if a merit has a file in the normalized table or legacy pivot.
     */
    private function hasFileRespaldo(int $meritoId, int $tipoDocumentoId): bool
    {
        $table = match ($tipoDocumentoId) {
            1 => 'formaciones_academicas',
            2 => 'formaciones_postgrado',
            3 => 'experiencias_docencia',
            4 => 'experiencias_profesionales',
            5 => 'capacitaciones',
            6 => 'producciones_intelectuales',
            7 => 'reconocimientos',
            default => null,
        };

        if ($table) {
            $row = DB::table($table)->where('source_merito_id', $meritoId)->first();
            if ($row) {
                if ($table === 'formaciones_academicas') {
                    if (!empty($row->diploma_archivo_path) || !empty($row->titulo_archivo_path)) {
                        return true;
                    }
                } else {
                    $prefix = match ($table) {
                        'formaciones_postgrado' => 'certificado_archivo',
                        'experiencias_docencia' => 'respaldo_archivo',
                        'experiencias_profesionales' => 'certificado_archivo',
                        'capacitaciones' => 'certificado_archivo',
                        'producciones_intelectuales' => 'evidencia_archivo',
                        'reconocimientos' => 'reconocimiento_archivo',
                        default => null,
                    };
                    if ($prefix) {
                        $pathCol = "{$prefix}_path";
                        if (!empty($row->$pathCol)) {
                            return true;
                        }
                    }
                }
            }
        }

        // Fallback to legacy database pivot
        $exists = DB::table('merito_archivos')->where('merito_id', $meritoId)->exists();
        if ($exists) {
            \Illuminate\Support\Facades\Log::warning('LEGACY_FALLBACK_TRIGGERED: Lectura de archivo legacy detectada en validación de scoring', [
                'merito_id' => $meritoId,
                'service' => 'SispoScoreEngine'
            ]);
        }
        return $exists;
    }
}
