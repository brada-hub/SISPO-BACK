<?php

namespace App\Jobs;

use App\Models\Postulacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Batch job that dispatches individual AnalyzeCvJob for each
 * postulación in a convocatoria. Includes rate-limiting to
 * stay within Gemini's free tier (15 RPM).
 */
class BatchAnalyzeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(
        public int $convocatoriaId,
    ) {}

    public function handle(): void
    {
        Log::info("BatchAnalyzeJob: Starting for convocatoria #{$this->convocatoriaId}");

        $postulaciones = Postulacion::whereHas('oferta', function ($q) {
            $q->where('convocatoria_id', $this->convocatoriaId);
        })
            ->whereHas('postulante', function ($q) {
                $q->whereNotNull('cv_pdf_path')->where('cv_pdf_path', '!=', '');
            })
            ->pluck('id');

        $total = $postulaciones->count();
        Log::info("BatchAnalyzeJob: Found {$total} postulaciones with CVs");

        try {
            // Dispatch individual jobs with staggered delays to respect rate limits
            // Gemini free tier: ~15 RPM → 1 request every 4 seconds
            // Each analysis makes 2 API calls (CV + matching) → 1 job every 8 seconds
            foreach ($postulaciones as $index => $postulacionId) {
                $delaySecs = $index * 10; // 10 seconds between each to be safe

                AnalyzeCvJob::dispatch($postulacionId)
                    ->delay(now()->addSeconds($delaySecs))
                    ->onQueue('ai');
            }

            Log::info("BatchAnalyzeJob: Dispatched {$total} analysis jobs");
        } catch (\Throwable $e) {
            Log::error("BatchAnalyzeJob: Failed to dispatch jobs for convocatoria #{$this->convocatoriaId}", [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
