<?php

namespace App\Console\Commands;

use App\Models\Convocatoria;
use App\Models\ConvocatoriaScoreRule;
use Illuminate\Console\Command;

class AuditConvocatoriaScoreProfilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:audit-convocatoria-score-profiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit score profiles applied to convocatorias, looking for points consistency or role mismatch warnings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('============================================================');
        $this->info('   AUDITORÍA INTELIGENTE DE PERFILES Y BAREMOS DE SELECCIÓN ');
        $this->info('============================================================');

        $convocatorias = Convocatoria::with(['scoreProfile', 'scoreRules'])->get();

        if ($convocatorias->isEmpty()) {
            $this->warn('No se encontraron convocatorias para auditar.');
            return 0;
        }

        $auditTable = [['ID', 'Título Convocatoria', 'Perfil Asignado', 'Total Puntos', 'Reglas Activas', 'Alertas / Sugerencias de Negocio']];
        $inconsistenciesCount = 0;

        foreach ($convocatorias as $conv) {
            $rules = $conv->scoreRules;
            $profileName = $conv->scoreProfile ? $conv->scoreProfile->name : '<fg=yellow>NINGUNO</>';
            
            $pointsSum = $rules->sum('max_points');
            $activeCount = $rules->where('is_enabled', true)->count();

            $alerts = [];

            // Alert 1: No profile assigned
            if (!$conv->score_profile_id) {
                $alerts[] = '<fg=yellow>⚠️ Sin perfil asignado</>';
            }

            // Alert 2: No rules found
            if ($rules->isEmpty()) {
                $alerts[] = '<fg=red;options=bold>❌ Sin reglas de baremo</>';
                $inconsistenciesCount++;
            }

            // Alert 3: Points sum is not 100
            if (!$rules->isEmpty() && abs($pointsSum - 100.00) > 0.01) {
                $alerts[] = "<fg=red;options=bold>❌ Suma de puntos incorrecta ({$pointsSum} pts)</>";
                $inconsistenciesCount++;
            }

            // Alert 4: Zero max points on enabled criteria
            $zeroPointsEnabled = $rules->where('is_enabled', true)->where('max_points', 0.00)->count();
            if ($zeroPointsEnabled > 0) {
                $alerts[] = "<fg=red>❌ Criterios habilitados con 0 pts ({$zeroPointsEnabled})</>";
                $inconsistenciesCount++;
            }

            // Alert 5: Profile Role Incompatibility Analysis (Smart Business Audit)
            $titulo = mb_strtoupper($conv->titulo, 'UTF-8');
            
            if ($conv->scoreProfile) {
                $code = $conv->scoreProfile->code;
                
                // Académico / Docente title check
                if (str_contains($titulo, 'DIRECCIÓN ACADÉMICA') || str_contains($titulo, 'DOCENTE') || str_contains($titulo, 'DIRECTOR ACADÉMICO') || str_contains($titulo, 'CARRERA')) {
                    if ($code === 'administrative_profile') {
                        $alerts[] = '<fg=cyan>💡 Sugerencia: Cargo académico con perfil administrativo</>';
                    }
                }

                // Técnico / Operativo title check
                if (str_contains($titulo, 'TÉCNICO') || str_contains($titulo, 'AUXILIAR') || str_contains($titulo, 'ACTIVOS FIJOS') || str_contains($titulo, 'MANTENIMIENTO')) {
                    if ($code === 'teaching_profile') {
                        $alerts[] = '<fg=cyan>💡 Sugerencia: Cargo operativo con perfil docente</>';
                    }
                }
            }

            $alertStr = count($alerts) > 0 ? implode(' | ', $alerts) : '<fg=green>✓ Conforme e Íntegro</>';

            // Highlight deviations
            $pointsStr = $pointsSum != 100.00 ? "<fg=red;options=bold>{$pointsSum} pts</>" : "<fg=green>{$pointsSum} pts</>";

            $auditTable[] = [
                $conv->id,
                mb_substr($conv->titulo, 0, 30),
                $profileName,
                $pointsStr,
                $activeCount,
                $alertStr,
            ];
        }

        $this->table($auditTable[0], array_slice($auditTable, 1));
        $this->info('------------------------------------------------------------');
        $this->comment("Total de inconsistencias críticas detectadas: {$inconsistenciesCount}");
        $this->info('============================================================');

        return 0;
    }
}
