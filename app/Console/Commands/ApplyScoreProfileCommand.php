<?php

namespace App\Console\Commands;

use App\Services\Evaluation\Score\ScoreProfileApplier;
use Illuminate\Console\Command;

class ApplyScoreProfileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:apply-score-profile
                            {--convocatoria_id= : Convocatoria ID to apply the profile rules}
                            {--profile= : Score profile code (e.g. administrative_profile)}
                            {--force : Force overwrite existing score rules for the convocatoria}
                            {--dry-run : Simulate the profile rules mapping}
                            {--write : Persist rules to MySQL DB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply a scorecard profile rules to a specific convocatoria';

    /**
     * Execute the console command.
     */
    public function handle(ScoreProfileApplier $applier): int
    {
        $convocatoriaId = $this->option('convocatoria_id') ? (int) $this->option('convocatoria_id') : null;
        $profileCode = $this->option('profile');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $write = $this->option('write');

        if (!$convocatoriaId || !$profileCode) {
            $this->error('Error: Debes proporcionar --convocatoria_id y --profile.');
            $this->info('Ejemplo: php artisan sispo:apply-score-profile --convocatoria_id=4 --profile=administrative_profile --dry-run');
            return 1;
        }

        if (!$write && !$dryRun) {
            $this->error('Error: Debes especificar --write o --dry-run para ejecutar la asignación de perfiles.');
            return 1;
        }

        $this->info('============================================================');
        $this->info('             SISPO SCORE PROFILE APPLIER                    ');
        $this->info('============================================================');
        $this->info('Modo de ejecución: ' . ($write ? '<fg=green;options=bold>ESCRITURA REAL</>' : '<fg=yellow;options=bold>SIMULACIÓN (DRY-RUN)</>'));
        $this->info("Convocatoria ID: {$convocatoriaId} | Perfil: {$profileCode}");
        $this->info('------------------------------------------------------------');

        try {
            $res = $applier->apply($convocatoriaId, $profileCode, $force, $write);

            $this->comment("Convocatoria: {$res['convocatoria_title']}");
            $this->comment("Perfil Evaluador: {$res['profile_applied']}");

            $statsTable = [
                ['Métrica de Asignación', 'Valor'],
                ['Reglas Creadas', $res['rules_created']],
                ['Reglas Actualizadas', $res['rules_updated']],
                ['Reglas Deshabilitadas (max_points = 0)', $res['rules_disabled']],
                ['Suma Puntos Máximos', $res['total_max_points'] . ' pts'],
            ];
            $this->table($statsTable[0], array_slice($statsTable, 1));
            $this->info('------------------------------------------------------------');

            $this->comment('📋 RESUMEN DE REGLAS DE BAREMO GENERADAS:');
            $rulesTable = [['Código Criterio', 'Nombre del Criterio', 'Peso Ponderado', 'Puntos Max', 'Estado Criterio']];
            foreach ($res['rules'] as $r) {
                $rulesTable[] = [
                    $r['code'],
                    $r['name'],
                    $r['weight'] . ' %',
                    $r['max_points'] . ' pts',
                    $r['is_enabled'] ? '<fg=green>Habilitado</>' : '<fg=red>Deshabilitado (0 pts)</>',
                ];
            }
            $this->table($rulesTable[0], array_slice($rulesTable, 1));
            $this->info('============================================================');

            if ($write) {
                $this->info('🎉 ¡Reglas aplicadas con total éxito en la base de datos MySQL!');
            } else {
                $this->info('💡 Simulación exitosa. Usa --write para persistir en base de datos de producción.');
            }

        } catch (\Throwable $e) {
            $this->error('Error aplicando perfil de baremo: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
