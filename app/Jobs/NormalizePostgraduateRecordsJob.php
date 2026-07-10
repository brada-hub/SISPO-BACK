<?php

namespace App\Jobs;

use App\Models\FormacionPostgrado;
use App\Services\Normalization\PostgraduateTypeNormalizer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NormalizePostgraduateRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?array $recordIds = null,
        protected bool $write = false
    ) {}

    public function handle(PostgraduateTypeNormalizer $postgradNormalizer): array
    {
        $stats = [
            'postgrado_total' => 0,
            'postgrado_normalized' => 0,
        ];

        $query = FormacionPostgrado::query();
        if (!empty($this->recordIds)) {
            $query->whereIn('id', $this->recordIds);
        }

        $query->chunk(100, function ($records) use ($postgradNormalizer, &$stats) {
            foreach ($records as $record) {
                $stats['postgrado_total']++;

                // Normalize postgraduate type
                $match = $postgradNormalizer->normalizePostgraduateType($record->tipo_posgrado_raw);

                if ($match) {
                    $stats['postgrado_normalized']++;

                    if ($this->write) {
                        $record->update([
                            'postgraduate_type_id' => $match['id'],
                            'normalization_confidence' => $match['confidence'] * 100,
                            'normalization_method' => 'postgrad:' . $match['method'],
                            'normalized_at' => Carbon::now(),
                        ]);
                    }
                }
            }
        });

        return $stats;
    }
}
