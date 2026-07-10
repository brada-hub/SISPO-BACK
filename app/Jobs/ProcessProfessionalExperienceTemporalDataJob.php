<?php

namespace App\Jobs;

use App\Models\ExperienciaProfesional;
use App\Services\Evaluation\Temporal\TemporalScoringService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProfessionalExperienceTemporalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?array $recordIds = null,
        protected bool $write = false
    ) {}

    public function handle(TemporalScoringService $scoringService): array
    {
        $stats = [
            'total' => 0,
            'processed' => 0,
            'currents' => 0,
            'recents' => 0,
            'months_total_sum' => 0,
            'months_5_years_sum' => 0,
            'errors' => 0,
        ];

        $query = ExperienciaProfesional::query();
        if (!empty($this->recordIds)) {
            $query->whereIn('id', $this->recordIds);
        }

        $query->chunk(100, function ($records) use ($scoringService, &$stats) {
            foreach ($records as $record) {
                $stats['total']++;

                try {
                    if (empty($record->fecha_inicio)) {
                        $stats['errors']++;
                        continue;
                    }

                    $result = $scoringService->scoreProfessionalExperience($record);

                    $stats['processed']++;
                    if ($result['is_current']) {
                        $stats['currents']++;
                    }
                    if ($result['recency_score'] >= 70.00) {
                        $stats['recents']++;
                    }
                    
                    $stats['months_total_sum'] += $result['months_total'];
                    $stats['months_5_years_sum'] += $result['months_in_last_5_years'];

                    if ($this->write) {
                        $record->update([
                            'is_current'             => $result['is_current'],
                            'last_experience_date'   => $result['last_experience_date'],
                            'months_in_last_3_years' => $result['months_in_last_3_years'],
                            'months_in_last_5_years' => $result['months_in_last_5_years'],
                            'months_total'           => $result['months_total'],
                            'recency_score'          => $result['recency_score'],
                            'temporal_processed_at'  => Carbon::now(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::error("[Temporal Engine] Error processing professional experience ID {$record->id}: " . $e->getMessage());
                }
            }
        });

        return $stats;
    }
}
