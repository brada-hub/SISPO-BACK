<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SispoMigrateMeritsFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sispo:migrate-merits-files
                            {--dry-run : Muestra los resultados sin guardarlos en la base de datos}
                            {--write : Guarda los datos procesados en la base de datos}
                            {--only= : Limita la migración a una tabla normalizada específica}
                            {--postulante_id= : Limita la migración a un postulante específico}
                            {--limit=1000 : Límite de registros a procesar}
                            {--force : Sobrescribe columnas de archivos que ya están llenas}';

    /**
     * The console command description.
     */
    protected $description = 'Migra la metadata de archivos desde merito_archivos a las nuevas columnas normalizadas directas.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $write = $this->option('write');
        $only = $this->option('only');
        $postulanteId = $this->option('postulante_id');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        if (!$dryRun && !$write) {
            $this->error('Error: Debes especificar --dry-run para simular la migración o --write para aplicar los cambios.');
            return 1;
        }

        if ($dryRun && $write) {
            $this->error('Error: No puedes usar --dry-run y --write al mismo tiempo.');
            return 1;
        }

        $allowedTables = [
            'formaciones_academicas',
            'formaciones_postgrado',
            'experiencias_docencia',
            'experiencias_profesionales',
            'capacitaciones',
            'producciones_intelectuales',
            'reconocimientos'
        ];

        $targetTables = $allowedTables;
        if (!empty($only)) {
            $onlyTables = explode(',', $only);
            $invalidTables = array_diff($onlyTables, $allowedTables);
            if (!empty($invalidTables)) {
                $this->error('Error: Tablas no válidas en --only: ' . implode(', ', $invalidTables));
                return 1;
            }
            $targetTables = $onlyTables;
        }

        $this->info("========================================================================");
        $this->info("           SISPO: MIGRACIÓN DE RESPALDOS A TABLAS NORMALIZADAS          ");
        $this->info("========================================================================");
        if ($dryRun) {
            $this->warn(" MODO SIMULACIÓN (--dry-run) - No se guardará nada en la BD.");
        } else {
            $this->warn(" MODO ESCRITURA (--write) - Los cambios se aplicarán usando transacciones.");
        }
        $this->line("");

        // Query files from legacy tables
        $query = DB::table('merito_archivos')
            ->join('postulante_meritos', 'merito_archivos.merito_id', '=', 'postulante_meritos.id')
            ->select(
                'merito_archivos.*',
                'postulante_meritos.postulante_id',
                'postulante_meritos.tipo_documento_id'
            );

        if (!empty($postulanteId)) {
            $this->info("Filtrando por Postulante ID: {$postulanteId}");
            $query->where('postulante_meritos.postulante_id', $postulanteId);
        }

        $query->orderBy('merito_archivos.id', 'asc')->limit($limit);

        $legacyFiles = $query->get();
        $totalFiles = $legacyFiles->count();

        $this->info("Total de registros de archivos leídos de merito_archivos: {$totalFiles}");
        $this->line("");

        // Stats tracking
        $stats = [
            'total_read' => $totalFiles,
            'migrated' => 0,
            'skipped_duplicate' => 0,
            'missing_postulante_merito' => 0,
            'missing_target_table' => 0,
            'missing_path' => 0,
            'missing_physical_file' => 0,
            'leak_prevention_triggered' => 0,
            'skipped_already_filled' => 0,
            'by_table' => array_fill_keys($allowedTables, 0),
            'by_file_type' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($legacyFiles as $file) {
                $filePath = $file->archivo_path;
                if (empty($filePath)) {
                    $stats['missing_path']++;
                    continue;
                }

                // Verify target table based on tipo_documento_id
                $targetTable = $this->mapTipoDocumentoToTable($file->tipo_documento_id);
                if (!$targetTable) {
                    $stats['missing_target_table']++;
                    continue;
                }

                // If this table is skipped by --only
                if (!in_array($targetTable, $targetTables)) {
                    continue;
                }

                // Determine target columns based on config_archivo_id
                $configId = $file->config_archivo_id ?? 'certificado';
                $prefix = $this->determineColumnPrefix($targetTable, $configId);
                if (!$prefix) {
                    $stats['missing_target_table']++;
                    continue;
                }

                $pathCol = "{$prefix}_path";
                $nameCol = "{$prefix}_original_name";
                $mimeCol = "{$prefix}_mime";

                // Read matching row in normalized table
                $normRow = DB::table($targetTable)->where('source_merito_id', $file->merito_id)->first();
                if (!$normRow) {
                    $stats['missing_postulante_merito']++;
                    continue;
                }

                // Security & Leak Prevention check: source_merito_id must belong to the exact same candidate
                if ((int)$normRow->postulante_id !== (int)$file->postulante_id) {
                    $this->error("[PREVENCIÓN DE FUGA] El mérito {$file->merito_id} pertenece al postulante {$normRow->postulante_id} pero el archivo pertenece al postulante {$file->postulante_id}! Omitiendo...");
                    $stats['leak_prevention_triggered']++;
                    continue;
                }

                // Check already filled
                if (!empty($normRow->$pathCol) && !$force) {
                    $stats['skipped_already_filled']++;
                    continue;
                }

                // Check if multiple files map to the same column for the same row
                if (!empty($normRow->$pathCol) && $force) {
                    $stats['skipped_duplicate']++;
                }

                // Physical file check in storage
                $fullPath = storage_path('app/public/' . $filePath);
                $existsPhysically = file_exists($fullPath);
                if (!$existsPhysically) {
                    $stats['missing_physical_file']++;
                }

                // Map updates
                $updateData = [
                    $pathCol => $filePath,
                    $nameCol => $file->nombre_original ?? basename($filePath),
                    $mimeCol => $file->mime_type ?? 'application/pdf',
                    'files_migrated_at' => Carbon::now(),
                    'files_migration_status' => $existsPhysically ? 'success' : 'missing_physical',
                    'files_migration_notes' => $existsPhysically ? 'Migrated successfully.' : 'Migrated but physical file was missing in storage.',
                ];

                if ($dryRun) {
                    $this->info("[SIMULACIÓN] Migrando archivo ID {$file->id} a '{$targetTable}' (ID: {$normRow->id}). Columna: {$pathCol} -> {$filePath}");
                } else {
                    DB::table($targetTable)->where('id', $normRow->id)->update($updateData);
                }

                $stats['migrated']++;
                $stats['by_table'][$targetTable]++;
                $stats['by_file_type'][$configId] = ($stats['by_file_type'][$configId] ?? 0) + 1;
            }

            if ($write) {
                DB::commit();
                $this->info("=== MIGRACIÓN COMPLETADA Y GUARDADA ===");
            } else {
                DB::rollBack();
                $this->info("=== MIGRACIÓN COMPLETADA (SIMULACIÓN - ROLLBACK) ===");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("¡ERROR EN LA MIGRACIÓN!");
            $this->error($e->getMessage());
            return 1;
        }

        // Final Report
        $this->info("\n================= RESUMEN DE PROCESAMIENTO =================");
        $this->line("Total registros leídos de merito_archivos: {$stats['total_read']}");
        $this->line("Migrados con éxito                       : <fg=green>{$stats['migrated']}</>");
        $this->line("Omitidos por columna ya llena            : {$stats['skipped_already_filled']}");
        $this->line("Omitidos por duplicado                   : {$stats['skipped_duplicate']}");
        $this->line("Errores: Fuga de postulante prevenida    : <fg=red>{$stats['leak_prevention_triggered']}</>");
        $this->line("Errores: Sin registro normalizado        : {$stats['missing_postulante_merito']}");
        $this->line("Errores: Sin tabla de destino            : {$stats['missing_target_table']}");
        $this->line("Errores: Ruta vacía                      : {$stats['missing_path']}");
        $this->line("Advertencias: Archivos rotos en Storage  : <fg=yellow>{$stats['missing_physical_file']}</>");
        
        $this->info("\n--- RESUMEN POR TABLA NORMALIZADA ---");
        foreach ($stats['by_table'] as $table => $count) {
            $this->line("- {$table}: {$count}");
        }

        $this->info("\n--- RESUMEN POR TIPO DE ARCHIVO (CONFIG) ---");
        foreach ($stats['by_file_type'] as $type => $count) {
            $this->line("- {$type}: {$count}");
        }
        $this->info("============================================================\n");

        return 0;
    }

    /**
     * Map tipo_documento_id to its normalized table.
     */
    private function mapTipoDocumentoToTable(int $tipoId): ?string
    {
        return match ($tipoId) {
            1 => 'formaciones_academicas',
            2 => 'formaciones_postgrado',
            3 => 'experiencias_docencia',
            4 => 'experiencias_profesionales',
            5 => 'capacitaciones',
            6 => 'producciones_intelectuales',
            7 => 'reconocimientos',
            default => null,
        };
    }

    /**
     * Normalize label to check string contains and aliases.
     */
    private function normalizeString(string $str): string
    {
        $unwanted = [
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
            'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u',
            'ñ'=>'n', 'Ñ'=>'n'
        ];
        $str = strtr($str, $unwanted);
        return trim(strtolower($str));
    }

    /**
     * Determine column prefix (e.g. diploma_archivo, certificado_archivo) based on config_archivo_id.
     */
    private function determineColumnPrefix(string $table, string $configId): ?string
    {
        $clean = $this->normalizeString($configId);

        if ($table === 'formaciones_academicas') {
            if (str_contains($clean, 'diploma')) {
                return 'diploma_archivo';
            }
            if (str_contains($clean, 'titulo')) {
                return 'titulo_archivo';
            }
            return 'diploma_archivo'; // Default fallback
        }
        if ($table === 'formaciones_postgrado') {
            return 'certificado_archivo';
        }
        if ($table === 'experiencias_docencia') {
            return 'respaldo_archivo';
        }
        if ($table === 'experiencias_profesionales') {
            return 'certificado_archivo';
        }
        if ($table === 'capacitaciones') {
            return 'certificado_archivo';
        }
        if ($table === 'producciones_intelectuales') {
            return 'evidencia_archivo';
        }
        if ($table === 'reconocimientos') {
            return 'reconocimiento_archivo';
        }

        return null;
    }
}
