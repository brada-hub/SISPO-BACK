<?php

namespace App\Console\Commands;

use App\Models\Capacitacion;
use App\Models\Postulante;
use Illuminate\Console\Command;

class AuditTrainingScoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:audit-training-score';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit normalized training records, loading hours distribution and identifying migration errors';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('============================================================');
        $this->info('           AUDITORÍA DE CAPACITACIONES SISPO                ');
        $this->info('============================================================');

        $totalCount = Capacitacion::count();
        $nullOrZeroCount = Capacitacion::where(function($q) {
            $q->whereNull('carga_horaria')->orWhere('carga_horaria', 0);
        })->count();
        $positiveCount = Capacitacion::where('carga_horaria', '>', 0)->count();

        $avgHours = Capacitacion::where('carga_horaria', '>', 0)->avg('carga_horaria');

        $statsTable = [
            ['Métrica de Carga Horaria', 'Valor'],
            ['Total Capacitaciones en BD', $totalCount],
            ['Registros con carga_horaria nula o en 0', $nullOrZeroCount],
            ['Registros con carga_horaria > 0', $positiveCount],
            ['Promedio de Horas en registros positivos', round($avgHours ?? 0, 1) . ' horas'],
        ];
        $this->table($statsTable[0], array_slice($statsTable, 1));
        $this->info('------------------------------------------------------------');

        // Top 20 values raw comparisons
        $this->comment('🔍 TOP 20 REGISTROS DE CAPACITACIÓN Y SUS VALORES MIGRADOS DESDE JSON:');
        
        $records = Capacitacion::with('sourceMerito')
            ->limit(20)
            ->get();

        $auditTable = [['ID', 'Nombre del Curso', 'Carga Horaria (Migrado)', 'Valores JSON Originales']];
        foreach ($records as $r) {
            $jsonRaw = 'N/A';
            if ($r->sourceMerito && $r->sourceMerito->respuestas) {
                $respuestas = $r->sourceMerito->respuestas;
                // Look for common keys in responses JSON
                $jsonRaw = json_encode(array_intersect_key($respuestas, array_flip(['carga_horaria', 'horas', 'duracion', 'institucion_organizadora', 'nombre_curso', 'curso'])));
            }

            $auditTable[] = [
                $r->id,
                mb_substr($r->nombre_curso, 0, 35),
                $r->carga_horaria ?? 'NULL',
                mb_substr($jsonRaw, 0, 60),
            ];
        }
        $this->table($auditTable[0], array_slice($auditTable, 1));
        $this->info('------------------------------------------------------------');

        // Examples of candidates with trainings but 0 hours (or active hours)
        $this->comment('⚠️ POSTULANTES CON REGISTROS DE CAPACITACIÓN DISPONIBLES:');
        $postulantes = Postulante::withCount('capacitaciones')
            ->has('capacitaciones')
            ->limit(10)
            ->get();

        $postTable = [['ID Postulante', 'Nombre Postulante', 'Cantidad Cursos', 'Suma Carga Horaria']];
        foreach ($postulantes as $p) {
            $sumHours = $p->capacitaciones()->sum('carga_horaria');
            $postTable[] = [
                $p->id,
                $p->nombres . ' ' . $p->apellidos,
                $p->capacitaciones_count,
                $sumHours . ' horas',
            ];
        }
        $this->table($postTable[0], array_slice($postTable, 1));
        $this->info('============================================================');

        return 0;
    }
}
