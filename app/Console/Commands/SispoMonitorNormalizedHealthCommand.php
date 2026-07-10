<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Postulante;
use App\Models\Postulacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class SispoMonitorNormalizedHealthCommand extends Command
{
    protected $signature = 'sispo:monitor-normalized-health {--since= : Fecha de corte YYYY-MM-DD HH:MM:SS}';
    protected $description = 'Monitor the health of the 100% normalized pipeline for new applications.';

    public function handle()
    {
        $this->info("========================================================================");
        $this->info("         MONITOREO DE SALUD DE PIPELINE NORMALIZADO                     ");
        $this->info("========================================================================");

        $sinceDate = $this->option('since') ?: Carbon::now()->subDays(3)->format('Y-m-d H:i:s');
        $this->info(" => Analizando postulaciones nuevas desde: {$sinceDate}");

        $postulaciones = Postulacion::where('created_at', '>=', $sinceDate)->get();
        if ($postulaciones->isEmpty()) {
            $this->warn("No hay postulaciones nuevas desde la fecha de corte para auditar.");
            return Command::SUCCESS;
        }

        $this->info("Total Postulaciones Recientes: {$postulaciones->count()}");
        $failed = 0;

        foreach ($postulaciones as $postulacion) {
            $this->line("\n------------------------------------------------------------------------");
            $this->info("Postulación ID: {$postulacion->id} | Postulante ID: {$postulacion->postulante_id}");
            $postulante = $postulacion->postulante;

            // Health Checks
            $hasSourceMeritoId = false;
            $missingFiles = [];
            $recordsCount = 0;

            $relations = [
                'formacionesAcademicas' => ['diploma_archivo_path', 'titulo_archivo_path'],
                'formacionesPostgrado' => ['certificado_archivo_path'],
                'experienciasProfesionales' => ['certificado_archivo_path'],
                'experienciasDocencia' => ['respaldo_archivo_path'],
                'capacitaciones' => ['certificado_archivo_path'],
                'produccionesIntelectuales' => ['evidencia_archivo_path'],
                'reconocimientos' => ['reconocimiento_archivo_path'],
            ];

            foreach ($relations as $rel => $cols) {
                if ($postulante->$rel) {
                    foreach ($postulante->$rel as $row) {
                        $recordsCount++;
                        if (!empty($row->source_merito_id)) {
                            $hasSourceMeritoId = true;
                        }
                        // Validate physical file existence if path exists
                        foreach ($cols as $col) {
                            if (!empty($row->$col)) {
                                if (!Storage::disk('public')->exists($row->$col)) {
                                    $missingFiles[] = $row->$col;
                                }
                            }
                        }
                    }
                }
            }

            if ($recordsCount === 0) {
                $this->warn("   [!] Postulante no registró ningún mérito. Saltando validaciones de archivo.");
            } else {
                if ($hasSourceMeritoId) {
                    $this->error("   [❌] DEPENDENCY LEAK: Registros nuevos contienen source_merito_id no nulo.");
                    $failed++;
                } else {
                    $this->line("   [✔] Independencia Legacy Confirmada (source_merito_id = null)");
                }

                if (!empty($missingFiles)) {
                    $this->error("   [❌] ORPHAN FILES: " . count($missingFiles) . " archivos físicos extraviados en Storage.");
                    $failed++;
                } else {
                    $this->line("   [✔] Integridad de Archivos Físicos Confirmada (100% Match)");
                }
            }

            // Check evaluation results
            if ($postulacion->evaluationResult) {
                $this->line("   [✔] Score Engine Health: Evaluación completada determinísticamente.");
            } else {
                $this->warn("   [!] Score Engine: Evaluación aún no generada o fallida.");
            }
        }

        $this->info("\n========================================================================");
        if ($failed > 0) {
            $this->error(" DICTAMEN: FALLIDO. {$failed} postulaciones presentaron anomalías de normalización.");
            return Command::FAILURE;
        } else {
            $this->info(" DICTAMEN: APROBADO (100% NORMALIZED HEALTH).");
            return Command::SUCCESS;
        }
    }
}
