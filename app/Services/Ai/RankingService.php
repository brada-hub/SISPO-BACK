<?php

namespace App\Services\Ai;

use App\Models\AiMatchingResult;
use Illuminate\Support\Collection;

/**
 * Ranking service that orders candidates by their matching scores
 * within a given convocatoria.
 */
class RankingService
{
    /**
     * Get the ranked list of candidates for a convocatoria.
     *
     * @param  int  $convocatoriaId
     * @return Collection  Ordered collection of matching results with relations
     */
    public function getRanking(int $convocatoriaId): Collection
    {
        $results = AiMatchingResult::forConvocatoria($convocatoriaId)
            ->byRanking()
            ->with([
                'postulacion.postulante',
                'postulacion.oferta.cargo',
                'postulacion.oferta.sede',
                'cvAnalysis',
            ])
            ->get();

        // Assign ranking positions
        $results->each(function ($result, $index) {
            $result->ranking_posicion = $index + 1;
            $result->save();
        });

        return $results;
    }

    /**
     * Get ranking summary statistics for a convocatoria.
     */
    public function getStats(int $convocatoriaId): array
    {
        $results = AiMatchingResult::forConvocatoria($convocatoriaId)->get();

        if ($results->isEmpty()) {
            return [
                'total'                => 0,
                'aptos'                => 0,
                'parcialmente_aptos'   => 0,
                'no_aptos'             => 0,
                'score_promedio'       => 0,
                'score_maximo'         => 0,
                'score_minimo'         => 0,
                'analizados'           => 0,
                'pendientes'           => 0,
            ];
        }

        return [
            'total'              => $results->count(),
            'aptos'              => $results->where('clasificacion_ia', 'apto')->count(),
            'parcialmente_aptos' => $results->where('clasificacion_ia', 'parcialmente_apto')->count(),
            'no_aptos'           => $results->where('clasificacion_ia', 'no_apto')->count(),
            'score_promedio'     => round($results->avg('score_total'), 2),
            'score_maximo'       => round($results->max('score_total'), 2),
            'score_minimo'       => round($results->min('score_total'), 2),
        ];
    }

    /**
     * Get a compact ranking suitable for display as badges in the postulaciones list.
     *
     * @return Collection  Keyed by postulacion_id for O(1) lookup
     */
    public function getBadges(int $convocatoriaId): Collection
    {
        return AiMatchingResult::forConvocatoria($convocatoriaId)
            ->select('postulacion_id', 'score_total', 'clasificacion_ia', 'ranking_posicion')
            ->byRanking()
            ->get()
            ->keyBy('postulacion_id');
    }
}
