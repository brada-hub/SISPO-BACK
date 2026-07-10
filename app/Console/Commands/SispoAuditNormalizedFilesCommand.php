<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SispoAuditNormalizedFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sispo:audit-normalized-files';

    /**
     * The console command description.
     */
    protected $description = 'Audita la integridad y consistencia de los archivos en las tablas normalizadas.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $allowedTables = [
            'formaciones_academicas',
            'formaciones_postgrado',
            'experiencias_docencia',
            'experiencias_profesionales',
            'capacitaciones',
            'producciones_intelectuales',
            'reconocimientos'
        ];

        $this->info("========================================================================");
        $this->info("             SISPO: AUDITORÍA DE ARCHIVOS NORMALIZADOS Y QA             ");
        $this->info("========================================================================");
        $this->line("");

        $totalRecords = 0;
        $totalWithDirect = 0;
        $totalWithFallbackOnly = 0;
        $totalWithoutAny = 0;
        $totalBrokenPhysical = 0;
        $totalInconsistencies = 0;

        $duplicatePaths = [];
        $uniquePaths = [];

        $criticalExamples = [];

        foreach ($allowedTables as $table) {
            $this->comment("Auditar tabla: '{$table}'...");
            $rows = DB::table($table)->get();
            $tableCount = $rows->count();
            $totalRecords += $tableCount;

            $withDirect = 0;
            $withFallbackOnly = 0;
            $withoutAny = 0;
            $brokenPhysical = 0;
            $inconsistencies = 0;

            foreach ($rows as $row) {
                // Determine direct paths
                $directPaths = [];
                if ($table === 'formaciones_academicas') {
                    if (!empty($row->diploma_archivo_path)) $directPaths[] = $row->diploma_archivo_path;
                    if (!empty($row->titulo_archivo_path)) $directPaths[] = $row->titulo_archivo_path;
                } else {
                    $prefix = match ($table) {
                        'formaciones_postgrado' => 'certificado_archivo',
                        'experiencias_docencia' => 'respaldo_archivo',
                        'experiencias_profesionales' => 'certificado_archivo',
                        'capacitaciones' => 'certificado_archivo',
                        'producciones_intelectuales' => 'evidencia_archivo',
                        'reconocimientos' => 'reconocimiento_archivo',
                        default => null,
                    };
                    if ($prefix) {
                        $pathCol = "{$prefix}_path";
                        if (!empty($row->$pathCol)) {
                            $directPaths[] = $row->$pathCol;
                        }
                    }
                }

                // Check direct path presence
                $hasDirect = !empty($directPaths);
                if ($hasDirect) {
                    $withDirect++;
                    foreach ($directPaths as $dp) {
                        // Check duplicates
                        if (in_array($dp, $uniquePaths)) {
                            $duplicatePaths[] = $dp;
                        } else {
                            $uniquePaths[] = $dp;
                        }

                        // Check physical file
                        $fullPath = storage_path('app/public/' . $dp);
                        if (!file_exists($fullPath)) {
                            $brokenPhysical++;
                            $totalBrokenPhysical++;
                            if (count($criticalExamples) < 5) {
                                $criticalExamples[] = "ID {$row->id} en '{$table}': archivo físico no existe para el path '{$dp}'";
                            }
                        }
                    }
                }

                // Check legacy fallback
                $hasLegacy = false;
                if (!empty($row->source_merito_id)) {
                    $hasLegacy = DB::table('merito_archivos')->where('merito_id', $row->source_merito_id)->exists();

                    // Check leak/inconsistency
                    $legacyMerit = DB::table('postulante_meritos')->where('id', $row->source_merito_id)->first();
                    if ($legacyMerit && (int)$legacyMerit->postulante_id !== (int)$row->postulante_id) {
                        $inconsistencies++;
                        $totalInconsistencies++;
                        if (count($criticalExamples) < 5) {
                            $criticalExamples[] = "[FUGA CRÍTICA] ID {$row->id} en '{$table}' pertenece a postulante {$row->postulante_id} pero source_merito_id {$row->source_merito_id} pertenece a postulante {$legacyMerit->postulante_id}";
                        }
                    }
                }

                if ($hasDirect) {
                    // Already counted in withDirect
                } elseif ($hasLegacy) {
                    $withFallbackOnly++;
                    $totalWithFallbackOnly++;
                } else {
                    $withoutAny++;
                    $totalWithoutAny++;
                }
            }

            $totalWithDirect += $withDirect;

            $this->line("  - Total registros            : {$tableCount}");
            $this->line("  - Con Archivo Directo        : <fg=green>{$withDirect}</>");
            $this->line("  - Con Fallback Legacy únicamente: <fg=yellow>{$withFallbackOnly}</>");
            $this->line("  - Sin Archivos               : {$withoutAny}");
            if ($brokenPhysical > 0) {
                $this->line("  - <fg=red;options=bold>ADVERTENCIA: Archivos rotos físicamente: {$brokenPhysical}</>");
            }
            if ($inconsistencies > 0) {
                $this->line("  - <fg=red;options=bold>ALERTA CRÍTICA: Inconsistencias postulante_id: {$inconsistencies}</>");
            }
            $this->line("");
        }

        // Summary report
        $this->info("========================================================================");
        $this->info("                        RESUMEN GLOBAL DE AUDITORÍA                     ");
        $this->info("========================================================================");
        $this->line("Total Registros Normalizados Analizados     : {$totalRecords}");
        $this->line("Con Archivo Directo Migrado                : <fg=green>{$totalWithDirect}</>");
        $this->line("Con Fallback Legacy únicamente             : <fg=yellow>{$totalWithFallbackOnly}</>");
        $this->line("Sin Ningún Archivo                         : {$totalWithoutAny}");
        $this->line("Archivos Rotos físicamente en Storage      : <fg=red>{$totalBrokenPhysical}</>");
        $this->line("Inconsistencias de Postulante ID (Fugas)   : <fg=red>{$totalInconsistencies}</>");
        $this->line("Rutas de Archivos Duplicadas               : " . count(array_unique($duplicatePaths)));
        $this->line("");

        if (!empty($criticalExamples)) {
            $this->warn("EJEMPLOS CRÍTICOS Y ALERTAS DETECTADAS:");
            foreach ($criticalExamples as $ex) {
                $this->line("  * {$ex}");
            }
            $this->line("");
        } else {
            $this->info("✔ ¡Felicidades! No se detectaron inconsistencias críticas ni fugas de datos.");
            $this->line("");
        }

        return 0;
    }
}
