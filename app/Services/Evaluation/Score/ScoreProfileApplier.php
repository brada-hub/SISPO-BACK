<?php

namespace App\Services\Evaluation\Score;

use App\Models\Convocatoria;
use App\Models\ScoreProfile;
use App\Models\ConvocatoriaScoreRule;
use Illuminate\Support\Facades\DB;

class ScoreProfileApplier
{
    /**
     * Apply a ScoreProfile rules to a specific Convocatoria.
     */
    public function apply(int $convocatoriaId, string $profileCode, bool $force = false, bool $write = false): array
    {
        $convocatoria = Convocatoria::findOrFail($convocatoriaId);
        $profile = ScoreProfile::where('code', $profileCode)->firstOrFail();
        $rules = $profile->rules;

        // Validation A: Check total max_points sum is exactly 100
        $sumPoints = $rules->sum('max_points');
        if (abs($sumPoints - 100.00) > 0.01) {
            throw new \InvalidArgumentException("El perfil '{$profileCode}' no es consistente: la suma de max_points es {$sumPoints} (debe ser exactamente 100).");
        }

        $createdCount = 0;
        $updatedCount = 0;
        $disabledCount = 0;
        $rulesApplied = [];

        if ($write) {
            DB::transaction(function () use ($convocatoria, $profile, $rules, $force, &$createdCount, &$updatedCount, &$disabledCount, &$rulesApplied) {
                // Associate profile to convocatoria
                $convocatoria->update(['score_profile_id' => $profile->id]);

                foreach ($rules as $r) {
                    $existing = ConvocatoriaScoreRule::where('convocatoria_id', $convocatoria->id)
                        ->where('criterion_code', $r->criterion_code)
                        ->first();

                    $isEnabled = $r->max_points > 0.00 && $r->is_enabled;
                    if (!$isEnabled) $disabledCount++;

                    if ($existing) {
                        if ($force) {
                            $existing->update([
                                'criterion_name' => $r->criterion_name,
                                'weight'         => $r->weight,
                                'max_points'     => $r->max_points,
                                'is_enabled'     => $isEnabled,
                                'config_json'    => $r->config_json,
                            ]);
                            $updatedCount++;
                        }
                    } else {
                        ConvocatoriaScoreRule::create([
                            'convocatoria_id' => $convocatoria->id,
                            'criterion_code'  => $r->criterion_code,
                            'criterion_name'  => $r->criterion_name,
                            'weight'          => $r->weight,
                            'max_points'      => $r->max_points,
                            'is_enabled'      => $isEnabled,
                            'config_json'     => $r->config_json,
                        ]);
                        $createdCount++;
                    }

                    $rulesApplied[] = [
                        'code'       => $r->criterion_code,
                        'name'       => $r->criterion_name,
                        'weight'     => $r->weight,
                        'max_points' => $r->max_points,
                        'is_enabled' => $isEnabled,
                    ];
                }
            });
        } else {
            // dry-run simulation
            foreach ($rules as $r) {
                $existing = ConvocatoriaScoreRule::where('convocatoria_id', $convocatoria->id)
                    ->where('criterion_code', $r->criterion_code)
                    ->first();

                $isEnabled = $r->max_points > 0.00 && $r->is_enabled;
                if (!$isEnabled) $disabledCount++;

                if ($existing) {
                    if ($force) $updatedCount++;
                } else {
                    $createdCount++;
                }

                $rulesApplied[] = [
                    'code'       => $r->criterion_code,
                    'name'       => $r->criterion_name,
                    'weight'     => $r->weight,
                    'max_points' => $r->max_points,
                    'is_enabled' => $isEnabled,
                ];
            }
        }

        return [
            'convocatoria_id'    => $convocatoriaId,
            'convocatoria_title' => $convocatoria->titulo,
            'profile_applied'    => $profile->name,
            'rules_created'      => $createdCount,
            'rules_updated'      => $updatedCount,
            'rules_disabled'     => $disabledCount,
            'total_max_points'   => $sumPoints,
            'rules'              => $rulesApplied,
        ];
    }
}
