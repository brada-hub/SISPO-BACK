<?php

namespace App\Jobs;

use App\Models\ExperienciaProfesional;
use App\Services\Normalization\JobPositionNormalizer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NormalizeProfessionalExperienceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?array $recordIds = null,
        protected bool $write = false
    ) {}

    public function handle(JobPositionNormalizer $positionNormalizer): array
    {
        $stats = [
            'profesional_total' => 0,
            'profesional_normalized' => 0,
        ];

        $query = ExperienciaProfesional::query();
        if (!empty($this->recordIds)) {
            $query->whereIn('id', $this->recordIds);
        }

        $query->chunk(100, function ($records) use ($positionNormalizer, &$stats) {
            foreach ($records as $record) {
                $stats['profesional_total']++;

                // Normalize job position
                $match = $positionNormalizer->matchJobPosition($record->cargo_raw);

                if ($match) {
                    $stats['profesional_normalized']++;

                    if ($this->write) {
                        $record->update([
                            'job_position_id' => $match['id'],
                            'normalization_confidence' => $match['confidence'] * 100,
                            'normalization_method' => 'job:' . $match['method'],
                            'normalized_at' => Carbon::now(),
                        ]);
                    }
                }
            }
        });

        return $stats;
    }
}
