<?php

namespace App\Jobs;

use App\Models\FormacionAcademica;
use App\Models\ExperienciaDocencia;
use App\Services\Normalization\AcademicLevelNormalizer;
use App\Services\Normalization\CareerNormalizer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NormalizeAcademicRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?array $academicIds = null,
        protected ?array $docenciaIds = null,
        protected bool $write = false
    ) {}

    public function handle(AcademicLevelNormalizer $levelNormalizer, CareerNormalizer $careerNormalizer): array
    {
        $stats = [
            'academic_total' => 0,
            'academic_normalized' => 0,
            'docencia_total' => 0,
            'docencia_normalized' => 0,
        ];

        // 1. Process formaciones_academicas
        $academicQuery = FormacionAcademica::query();
        if (!empty($this->academicIds)) {
            $academicQuery->whereIn('id', $this->academicIds);
        }
        
        $academicQuery->chunk(100, function ($records) use ($levelNormalizer, $careerNormalizer, &$stats) {
            $areaMatcher = new \App\Services\Evaluation\Temporal\AcademicAreaMatcher();

            foreach ($records as $record) {
                $stats['academic_total']++;

                // Normalize level
                $levelMatch = $levelNormalizer->normalizeAcademicLevel($record->nivel_academico_raw);
                
                // Normalize career
                $careerMatch = $careerNormalizer->matchCareer($record->carrera_raw);

                $confidence = 0.0;
                $matchesCount = 0;
                $methodParts = [];

                if ($levelMatch) {
                    $confidence += $levelMatch['confidence'];
                    $matchesCount++;
                    $methodParts[] = "level:" . $levelMatch['method'];
                }

                if ($careerMatch) {
                    $confidence += $careerMatch['confidence'];
                    $matchesCount++;
                    $methodParts[] = "career:" . $careerMatch['method'];
                }

                $finalConfidence = $matchesCount > 0 ? round($confidence / $matchesCount, 2) : null;
                $finalMethod = !empty($methodParts) ? implode('|', $methodParts) : null;

                // Academic Area Matcher fallback logic (Fase 3)
                $areaId = null;
                $areaConf = null;
                $areaMethod = null;

                if ($careerMatch) {
                    // Inherit from career catalog
                    $careerModel = \App\Models\CatalogCareer::find($careerMatch['id']);
                    if ($careerModel) {
                        $areaId = $careerModel->professional_area_id;
                        $areaConf = 100.00;
                        $areaMethod = 'inherited_from_career_catalog';
                    }
                } else {
                    // Fallback to keyword matching
                    $areaMatch = $areaMatcher->match($record->carrera_raw ?? '');
                    if ($areaMatch['professional_area_id']) {
                        $areaId = $areaMatch['professional_area_id'];
                        $areaConf = $areaMatch['area_confidence'];
                        $areaMethod = $areaMatch['area_normalization_method'];
                    }
                }

                if ($levelMatch || $careerMatch || $areaId) {
                    $stats['academic_normalized']++;
                    
                    if ($this->write) {
                        $record->update([
                            'academic_level_id'          => $levelMatch ? $levelMatch['id'] : null,
                            'career_id'                  => $careerMatch ? $careerMatch['id'] : null,
                            'professional_area_id'       => $areaId,
                            'area_confidence'            => $areaConf,
                            'area_normalization_method'  => $areaMethod,
                            'normalization_confidence'   => $finalConfidence ? $finalConfidence * 100 : 70.00,
                            'normalization_method'       => $finalMethod ?? 'area_keyword_only',
                            'normalized_at'              => Carbon::now(),
                        ]);
                    }
                }
            }
        });

        // 2. Process experiencias_docencia
        $docenciaQuery = ExperienciaDocencia::query();
        if (!empty($this->docenciaIds)) {
            $docenciaQuery->whereIn('id', $this->docenciaIds);
        }

        $docenciaQuery->chunk(100, function ($records) use ($careerNormalizer, &$stats) {
            foreach ($records as $record) {
                $stats['docencia_total']++;

                // Normalize career
                $careerMatch = $careerNormalizer->matchCareer($record->carrera_raw);

                if ($careerMatch) {
                    $stats['docencia_normalized']++;

                    if ($this->write) {
                        $record->update([
                            'career_id' => $careerMatch['id'],
                            'normalization_confidence' => $careerMatch['confidence'] * 100,
                            'normalization_method' => 'career:' . $careerMatch['method'],
                            'normalized_at' => Carbon::now(),
                        ]);
                    }
                }
            }
        });

        return $stats;
    }
}
