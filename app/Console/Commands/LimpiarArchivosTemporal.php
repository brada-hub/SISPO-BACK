<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class LimpiarArchivosTemporal extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'postulacion:limpiar-temporales
                            {--horas=12 : Eliminar archivos con más de N horas de antigüedad (default: 12)}
                            {--dry-run : Solo mostrar los archivos que se eliminarían, sin borrar nada}';

    /**
     * The console command description.
     */
    protected $description = 'Elimina archivos temporales de postulación (postulaciones/tmp) que tienen más de N horas de antigüedad. Estos son archivos huérfanos de postulaciones no completadas.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $horas    = (int) $this->option('horas');
        $dryRun   = (bool) $this->option('dry-run');
        $corte    = Carbon::now()->subHours($horas);
        $disk     = Storage::disk('public');
        $directorio = 'postulaciones/tmp';

        $this->info("=== SISPO: Limpieza de archivos temporales ===");
        $this->info("Directorio  : {$directorio}");
        $this->info("Antigüedad  : más de {$horas} hora(s) (antes de {$corte->toDateTimeString()})");
        $this->info("Modo        : " . ($dryRun ? 'DRY-RUN (sin borrar)' : 'REAL (borrando)'));
        $this->line('');

        if (!$disk->exists($directorio)) {
            $this->warn("El directorio '{$directorio}' no existe. Nada que limpiar.");
            return Command::SUCCESS;
        }

        $archivos = $disk->files($directorio);

        if (empty($archivos)) {
            $this->info("No hay archivos en '{$directorio}'.");
            return Command::SUCCESS;
        }

        $eliminados   = 0;
        $saltados     = 0;
        $errores      = 0;
        $bytesLiberados = 0;

        foreach ($archivos as $archivo) {
            try {
                $ultimaModificacion = $disk->lastModified($archivo);
                $fechaArchivo = Carbon::createFromTimestamp($ultimaModificacion);

                if ($fechaArchivo->isAfter($corte)) {
                    // Archivo reciente — no tocar
                    $saltados++;
                    continue;
                }

                $tamano = $disk->size($archivo);

                if ($dryRun) {
                    $this->line("  [DRY-RUN] Borraría: {$archivo} (modificado: {$fechaArchivo->toDateTimeString()}, tamaño: " . number_format($tamano / 1024, 1) . " KB)");
                } else {
                    $disk->delete($archivo);
                    $this->line("  [OK] Eliminado: {$archivo} ({$fechaArchivo->toDateTimeString()}, " . number_format($tamano / 1024, 1) . " KB)");
                    $bytesLiberados += $tamano;
                }

                $eliminados++;
            } catch (\Throwable $e) {
                $errores++;
                $this->error("  [ERROR] No se pudo procesar: {$archivo} — {$e->getMessage()}");
            }
        }

        $this->line('');
        $this->info("=== Resumen ===");
        $this->info("Archivos recientes (no tocados) : {$saltados}");
        $this->info("Archivos " . ($dryRun ? 'que se eliminarían' : 'eliminados') . "       : {$eliminados}");

        if (!$dryRun && $bytesLiberados > 0) {
            $this->info("Espacio liberado                : " . number_format($bytesLiberados / 1024 / 1024, 2) . " MB");
        }

        if ($errores > 0) {
            $this->warn("Errores                         : {$errores}");
        }

        \Log::info('postulacion:limpiar-temporales ejecutado', [
            'horas_corte'    => $horas,
            'dry_run'        => $dryRun,
            'saltados'       => $saltados,
            'eliminados'     => $eliminados,
            'bytes_liberados' => $bytesLiberados,
            'errores'        => $errores,
        ]);

        return Command::SUCCESS;
    }
}
