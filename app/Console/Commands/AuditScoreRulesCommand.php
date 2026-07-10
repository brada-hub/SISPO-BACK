<?php

namespace App\Console\Commands;

use App\Models\Convocatoria;
use App\Models\ConvocatoriaScoreRule;
use Illuminate\Console\Command;

class AuditScoreRulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:audit-score-rules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit convocatoria score rules weights, point totals, and active criteria consistency';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('============================================================');
        $this->info('      AUDITORÍA DE REGLAS DE SELECCIÓN POR CONVOCATORIA     ');
        $this->info('============================================================');

        $convocatorias = Convocatoria::has('baremos')->orHas('ofertas')->get();

        if ($convocatorias->isEmpty()) {
            // Load some default active ones in the DB
            $convocatorias = Convocatoria::limit(10)->get();
        }

        $auditTable = [['ID Convocatoria', 'Título de la Convocatoria', 'Total Pesos', 'Total Puntos Max', 'Criterios Activos', 'Criterios Cero']];

        foreach ($convocatorias as $conv) {
            $rules = ConvocatoriaScoreRule::where('convocatoria_id', $conv->id)->get();

            if ($rules->isEmpty()) {
                $auditTable[] = [
                    $conv->id,
                    mb_substr($conv->titulo, 0, 30),
                    '<fg=yellow>SIN REGLAS</>',
                    '<fg=yellow>SIN REGLAS</>',
                    '0',
                    '0',
                ];
                continue;
            }

            $weightSum = $rules->sum('weight');
            $pointsSum = $rules->sum('max_points');
            $activeCount = $rules->where('is_enabled', true)->count();
            $zeroCount = $rules->where('max_points', 0.00)->count();

            // Highlight deviations
            $weightStr = $weightSum != 100.00 ? "<fg=red;options=bold>{$weightSum} %</>" : "<fg=green>{$weightSum} %</>";
            $pointsStr = $pointsSum != 100.00 ? "<fg=red;options=bold>{$pointsSum} pts</>" : "<fg=green>{$pointsSum} pts</>";

            $auditTable[] = [
                $conv->id,
                mb_substr($conv->titulo, 0, 30),
                $weightStr,
                $pointsStr,
                $activeCount,
                $zeroCount > 0 ? "<fg=red>{$zeroCount}</>" : '0',
            ];
        }

        $this->table($auditTable[0], array_slice($auditTable, 1));
        $this->info('============================================================');

        return 0;
    }
}
