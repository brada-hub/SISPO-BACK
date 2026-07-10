<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SispoMonitorLegacyWritesCommand extends Command
{
    protected $signature = 'sispo:monitor-legacy-writes {--since= : Fecha de corte en formato YYYY-MM-DD HH:MM:SS}';
    protected $description = 'Monitor to detect any illegal writes to legacy merit tables.';

    public function handle()
    {
        $this->info("========================================================================");
        $this->info("             MONITOREO DE ESCRITURAS LEGACY ACTIVADO                    ");
        $this->info("========================================================================");

        $sinceDate = $this->option('since');
        if (!$sinceDate) {
            $sinceDate = Carbon::now()->subDays(3)->format('Y-m-d H:i:s');
        }

        $this->info(" => Analizando escrituras desde la fecha de corte: {$sinceDate}");

        // postulante_meritos
        $pmQuery = DB::table('postulante_meritos');
        $pmTotal = clone $pmQuery;
        $pmRecent = $pmQuery->where('created_at', '>=', $sinceDate)->get();

        // merito_archivos
        $maQuery = DB::table('merito_archivos');
        $maTotal = clone $maQuery;
        $maRecent = $maQuery->where('created_at', '>=', $sinceDate)->get();

        $this->info("\n[ ESTADÍSTICAS GLOBALES ]");
        $this->line(" - postulante_meritos : " . $pmTotal->count() . " registros totales.");
        $this->line(" - merito_archivos    : " . $maTotal->count() . " registros totales.");

        $this->info("\n[ RESULTADO DE AUDITORÍA POST-CORTE ]");
        $alert = false;

        if ($pmRecent->count() > 0) {
            $this->error(" ❌ ALERTA CRÍTICA: Se detectaron {$pmRecent->count()} escrituras ilegales en postulante_meritos.");
            $this->table(['ID', 'Postulante ID', 'Tipo Doc', 'Created At'], $pmRecent->map(fn($r) => [$r->id, $r->postulante_id, $r->tipo_documento_id, $r->created_at])->toArray());
            $alert = true;
        } else {
            $this->info(" ✅ OK: postulante_meritos LIMPIO (0 escrituras nuevas).");
        }

        if ($maRecent->count() > 0) {
            $this->error(" ❌ ALERTA CRÍTICA: Se detectaron {$maRecent->count()} escrituras ilegales en merito_archivos.");
            $this->table(['ID', 'Mérito ID', 'Config', 'Created At'], $maRecent->map(fn($r) => [$r->id, $r->merito_id, $r->config_archivo_id, $r->created_at])->toArray());
            $alert = true;
        } else {
            $this->info(" ✅ OK: merito_archivos LIMPIO (0 escrituras nuevas).");
        }

        $this->info("\n========================================================================");
        if ($alert) {
            $this->error(" DICTAMEN: NO APROBADO (Escritura Legacy Activa). Retenga eliminación.");
            return Command::FAILURE;
        } else {
            $this->info(" DICTAMEN: APROBADO. Ninguna escritura registrada post-normalización.");
            return Command::SUCCESS;
        }
    }
}
