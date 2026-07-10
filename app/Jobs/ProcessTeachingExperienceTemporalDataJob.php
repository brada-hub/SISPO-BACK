<?php

namespace App\Jobs;

use App\Models\ExperienciaDocencia;
use App\Services\Evaluation\Temporal\TemporalScoringService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTeachingExperienceTemporalDataJob implements ShouldQueue
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
            'recents' => 0,
            'errors' => 0,
            'parsing_failed_periods' => [],
        ];

        $query = ExperienciaDocencia::query();
        if (!empty($this->recordIds)) {
            $query->whereIn('id', $this->recordIds);
        }

        $query->chunk(100, function ($records) use ($scoringService, &$stats) {
            foreach ($records as $record) {
                $stats['total']++;

                try {
                    $result = $scoringService->scoreTeachingExperience($record);

                    if (!$result) {
                        $stats['errors']++;
                        $stats['parsing_failed_periods'][] = [
                            'id' => $record->id,
                            'periodo' => $record->gestion_periodo ?? 'VACIO',
                        ];
                        continue;
                    }

                    $stats['processed']++;
                    if ($result['is_recent_last_5_years']) {
                        $stats['recents']++;
                    }

                    if ($this->write) {
                        $record->update([
                            'teaching_year_start'    => $result['teaching_year_start'],
                            'teaching_year_end'      => $result['teaching_year_end'],
                            'years_in_last_5_years'  => $result['years_in_last_5_years'],
                            'is_recent_last_5_years' => $result['is_recent_last_5_years'],
                            'last_teaching_year'     => $result['last_teaching_year'],
                            'recency_score'          => $result['recency_score'],
                            'temporal_processed_at'  => Carbon::now(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::error("[Temporal Engine] Error processing teaching experience ID {$record->id}: " . $e->getMessage());
                }
            }
        });

        return $stats;
    }
}
