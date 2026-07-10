<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PostulanteMerito;
use App\Models\FormacionAcademica;
use App\Models\FormacionPostgrado;
use App\Models\ExperienciaDocencia;
use App\Models\ExperienciaProfesional;
use App\Models\Capacitacion;
use App\Models\ProduccionIntelectual;
use App\Models\Reconocimiento;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MigrateMeritosToNormalizedTables extends Command
{
    protected $signature = 'sispo:migrate-meritos 
                            {--dry-run : Muestra los resultados sin guardarlos en la base de datos}
                            {--write : Guarda los datos procesados en la base de datos}
                            {--only= : Limita la migración a una o varias tablas separadas por coma (formaciones_academicas,formaciones_postgrado,experiencias_docencia,experiencias_profesionales,capacitaciones,producciones_intelectuales,reconocimientos)}
                            {--postulante_id= : Limita la migración a un postulante específico}';

    protected $description = 'Migra de manera robusta los méritos estructurados de postulante_meritos a sus correspondientes tablas normalizadas en paralelo.';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $write = $this->option('write');
        $only = $this->option('only');
        $postulanteId = $this->option('postulante_id');

        if (!$dryRun && !$write) {
            $this->error('Debes especificar --dry-run para simular la migración o --write para aplicar los cambios.');
            return 1;
        }

        if ($dryRun && $write) {
            $this->error('No puedes usar --dry-run y --write al mismo tiempo.');
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
                $this->error('Tablas no válidas especificadas en --only: ' . implode(', ', $invalidTables));
                return 1;
            }
            $targetTables = $onlyTables;
        }

        $this->info("=== INICIANDO MIGRACIÓN DE MÉRITOS NORMALIZADOS ===");
        if ($dryRun) {
            $this->warn("MODO SIMULACIÓN (--dry-run) - No se guardará nada en la BD.");
        } else {
            $this->warn("MODO ESCRITURA (--write) - Los cambios se guardarán usando transacciones.");
        }

        // Cargar registros
        $query = PostulanteMerito::with(['tipoDocumento', 'postulante']);

        if (!empty($postulanteId)) {
            $this->info("Filtrando por Postulante ID: {$postulanteId}");
            $query->where('postulante_id', $postulanteId);
        }

        $meritos = $query->get();
        $total = $meritos->count();

        $this->info("Total de registros a analizar: {$total}");

        $stats = [
            'total_procesados' => 0,
            'omitidos_por_tabla_no_incluida' => 0,
            'omitidos_por_vacio' => 0,
            'duplicados_omitidos' => 0,
            'exitosos' => 0,
            'errores' => 0,
            'por_tabla' => array_fill_keys($allowedTables, 0)
        ];

        DB::beginTransaction();

        try {
            foreach ($meritos as $merito) {
                $stats['total_procesados']++;

                $targetTable = $this->resolveTipoDocumento($merito->tipoDocumento);
                if (!$targetTable) {
                    $this->warn("[OMITIDO] Registro ID {$merito->id}: No se pudo determinar la tabla destino para el tipo '" . ($merito->tipoDocumento->nombre ?? 'N/A') . "'");
                    $stats['omitidos_por_tabla_no_incluida']++;
                    continue;
                }

                if (!in_array($targetTable, $targetTables)) {
                    $stats['omitidos_por_tabla_no_incluida']++;
                    continue;
                }

                $respuestas = $merito->respuestas;
                if (is_string($respuestas)) {
                    $respuestas = json_decode($respuestas, true);
                }

                if (empty($respuestas) || !is_array($respuestas)) {
                    $this->warn("[VACÍO] Registro ID {$merito->id}: El JSON respuestas está vacío o no es un array.");
                    $stats['omitidos_por_vacio']++;
                    continue;
                }

                // Validar duplicado por source_merito_id
                if ($this->existeDuplicado($targetTable, $merito->id)) {
                    $this->line("[DUPLICADO] Registro ID {$merito->id}: Ya migrado en la tabla '{$targetTable}'");
                    $stats['duplicados_omitidos']++;
                    continue;
                }

                // Procesar e Insertar
                $mappedData = $this->mapData($targetTable, $merito->postulante_id, $merito->id, $respuestas);
                
                if (empty($mappedData)) {
                    $this->error("[ERROR] Registro ID {$merito->id}: Falló el mapeo para la tabla '{$targetTable}'");
                    $stats['errores']++;
                    continue;
                }

                if ($dryRun) {
                    $this->info("[SIMULACIÓN] Migrando Mérito ID {$merito->id} a '{$targetTable}'");
                    $this->line(json_encode($mappedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                } else {
                    $this->saveRecord($targetTable, $mappedData);
                }

                $stats['exitosos']++;
                $stats['por_tabla'][$targetTable]++;
            }

            if ($write) {
                DB::commit();
                $this->info("=== MIGRACIÓN COMPLETADA EXITOSAMENTE Y GUARDADA ===");
            } else {
                DB::rollBack();
                $this->info("=== MIGRACIÓN COMPLETADA (SIMULACIÓN - TRANSLACIÓN DESHECHA) ===");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("¡ERROR CRÍTICO EN LA MIGRACIÓN!");
            $this->error($e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        // Resumen
        $this->info("\n================= RESUMEN DE PROCESAMIENTO =================");
        $this->line("Total registros analizados: {$stats['total_procesados']}");
        $this->line("Migrados exitosamente     : {$stats['exitosos']}");
        $this->line("Omitidos por tipo/filtro : {$stats['omitidos_por_tabla_no_incluida']}");
        $this->line("Omitidos por vacíos       : {$stats['omitidos_por_vacio']}");
        $this->line("Omitidos por duplicados   : {$stats['duplicados_omitidos']}");
        $this->line("Errores de mapeo          : {$stats['errores']}");
        $this->info("\n--- REGISTROS MIGRADOS POR TABLA NORMALIZADA ---");
        foreach ($stats['por_tabla'] as $tabla => $count) {
            $this->line("- {$tabla}: {$count}");
        }
        $this->info("============================================================\n");

        return 0;
    }

    protected function resolveTipoDocumento($tipoDocumento)
    {
        if (!$tipoDocumento || empty($tipoDocumento->nombre)) {
            return null;
        }
        
        $nombre = trim(mb_strtoupper($tipoDocumento->nombre));
        
        switch ($nombre) {
            case 'FORMACIÓN ACADÉMICA':
            case 'FORMACION ACADEMICA':
                return 'formaciones_academicas';
            case 'FORMACIÓN EN POSGRADO':
            case 'FORMACION EN POSGRADO':
            case 'FORMACIÓN EN POSTGRADO':
            case 'FORMACION EN POSTGRADO':
            case 'POSGRADO':
            case 'POSTGRADO':
                return 'formaciones_postgrado';
            case 'EXPERIENCIA DOCENCIA':
            case 'DOCENCIA':
                return 'experiencias_docencia';
            case 'EXPERIENCIA PROFESIONAL':
            case 'LABORAL':
                return 'experiencias_profesionales';
            case 'CAPACITACION':
            case 'CAPACITACIÓN':
            case 'CAPACITACIONES':
                return 'capacitaciones';
            case 'PRODUCCIÓN INTELECTUAL':
            case 'PRODUCCION INTELECTUAL':
            case 'PRODUCCION':
                return 'producciones_intelectuales';
            case 'RECONOCIMIENTO':
            case 'RECONOCIMIENTOS':
                return 'reconocimientos';
            default:
                return null;
        }
    }

    protected function existeDuplicado($tabla, $sourceMeritoId)
    {
        return DB::connection('mysql')->table($tabla)->where('source_merito_id', $sourceMeritoId)->exists();
    }

    protected function mapData($tabla, $postulanteId, $sourceMeritoId, array $respuestas)
    {
        $base = [
            'postulante_id' => $postulanteId,
            'source_merito_id' => $sourceMeritoId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        switch ($tabla) {
            case 'formaciones_academicas':
                $nivel = $respuestas['nivel'] ?? ($respuestas['nivel_academico'] ?? null);
                return array_merge($base, [
                    'nivel_academico_raw' => $this->safeString($nivel),
                    'nivel_academico_normalizado' => $this->safeString($nivel),
                    'universidad' => $this->safeString($respuestas['universidad'] ?? ($respuestas['institucion'] ?? null)),
                    'carrera_raw' => $this->safeString($respuestas['profesion'] ?? ($respuestas['carrera'] ?? null)),
                    'carrera_normalizada_id' => null,
                    'fecha_diploma' => $this->parseFlexibleDate($respuestas['fecha_diploma'] ?? null),
                    'fecha_titulo' => $this->parseFlexibleDate($respuestas['fecha_titulo'] ?? null),
                ]);

            case 'formaciones_postgrado':
                $tipo = $respuestas['tipo_posgrado'] ?? ($respuestas['tipo'] ?? null);
                return array_merge($base, [
                    'tipo_posgrado_raw' => $this->safeString($tipo),
                    'tipo_posgrado_normalizado' => $this->safeString($tipo),
                    'nombre_programa' => $this->safeString($respuestas['nombre_programa'] ?? ($respuestas['nombre'] ?? ($respuestas['programa'] ?? null))),
                    'fecha_certificacion' => $this->parseFlexibleDate($respuestas['fecha_certificacion'] ?? ($respuestas['fecha'] ?? null)),
                    'institucion' => $this->safeString($respuestas['institucion'] ?? ($respuestas['universidad'] ?? null)),
                ]);

            case 'experiencias_docencia':
                return array_merge($base, [
                    'universidad' => $this->safeString($respuestas['universidad'] ?? ($respuestas['institucion'] ?? null)),
                    'carrera_raw' => $this->safeString($respuestas['carrera'] ?? null),
                    'carrera_normalizada_id' => null,
                    'asignaturas' => $respuestas['asignaturas'] ?? ($respuestas['materia'] ?? null), // Text field, no limit
                    'gestion_periodo' => $this->safeString($respuestas['gestion_periodo'] ?? ($respuestas['gestion'] ?? null)),
                ]);

            case 'experiencias_profesionales':
                $fechaInicio = $this->parseFlexibleDate($respuestas['fecha_inicio'] ?? null);
                $fechaFin = $this->parseFlexibleDate($respuestas['fecha_fin'] ?? null);
                $duracionMeses = $this->calcularDuracionMeses($fechaInicio, $fechaFin);

                return array_merge($base, [
                    'cargo_raw' => $this->safeString($respuestas['cargo'] ?? null),
                    'cargo_normalizado_id' => null,
                    'empresa' => $this->safeString($respuestas['empresa'] ?? ($respuestas['institucion'] ?? null)),
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'duracion_meses' => $duracionMeses,
                ]);

            case 'capacitaciones':
                return array_merge($base, [
                    'nombre_curso' => $this->safeString($respuestas['nombre'] ?? ($respuestas['nombre_capacitacion'] ?? ($respuestas['titulo'] ?? null))),
                    'fecha' => $this->parseFlexibleDate($respuestas['fecha'] ?? null),
                    'institucion_organizadora' => $this->safeString($respuestas['institucion'] ?? ($respuestas['institucion_organizadora'] ?? null)),
                    'carga_horaria' => $this->parseInteger($respuestas['horas'] ?? ($respuestas['carga_horaria'] ?? null)),
                ]);

            case 'producciones_intelectuales':
                $tipoProd = $respuestas['tipo'] ?? ($respuestas['tipo_produccion'] ?? null);
                return array_merge($base, [
                    'tipo_produccion_raw' => $this->safeString($tipoProd),
                    'tipo_produccion_normalizado' => $this->safeString($tipoProd),
                    'titulo' => $this->safeString($respuestas['titulo'] ?? ($respuestas['nombre'] ?? null)),
                    'fecha_publicacion' => $this->parseFlexibleDate($respuestas['fecha_publicacion'] ?? ($respuestas['fecha'] ?? null)),
                    'editorial_revista' => $this->safeString($respuestas['editorial'] ?? ($respuestas['editorial_revista'] ?? ($respuestas['revista'] ?? null))),
                    'lugar' => $this->safeString($respuestas['lugar'] ?? null),
                ]);

            case 'reconocimientos':
                return array_merge($base, [
                    'titulo_reconocimiento' => $this->safeString($respuestas['titulo'] ?? ($respuestas['titulo_reconocimiento'] ?? ($respuestas['nombre'] ?? null))),
                    'fecha' => $this->parseFlexibleDate($respuestas['fecha'] ?? null),
                    'institucion_otorgante' => $this->safeString($respuestas['institucion'] ?? ($respuestas['institucion_otorgante'] ?? null)),
                    'lugar' => $this->safeString($respuestas['lugar'] ?? null),
                ]);
        }

        return null;
    }

    protected function saveRecord($tabla, array $data)
    {
        DB::connection('mysql')->table($tabla)->insert($data);
    }

    protected function parseFlexibleDate($value)
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            // Si es un año de 4 dígitos solamente (por ejemplo, "2021")
            $val = trim($value);
            if (is_numeric($val) && strlen($val) === 4) {
                return "{$val}-01-01";
            }
            return null;
        }
    }

    protected function parseInteger($value)
    {
        if (empty($value)) {
            return null;
        }
        
        $cleaned = trim($value);
        if (is_numeric($cleaned)) {
            return (int) $cleaned;
        }

        preg_match_all('!\d+!', $cleaned, $matches);
        if (!empty($matches[0])) {
            return (int) implode('', $matches[0]);
        }

        return null;
    }

    protected function calcularDuracionMeses($inicioStr, $finStr)
    {
        if (empty($inicioStr) || empty($finStr)) {
            return null;
        }

        try {
            $inicio = Carbon::parse($inicioStr);
            $fin = Carbon::parse($finStr);

            if ($inicio->greaterThan($fin)) {
                return 0;
            }

            // Calculamos la diferencia exacta en meses
            $meses = $inicio->diffInMonths($fin);
            return (int) $meses;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function safeString($value, $limit = 255)
    {
        if (empty($value)) {
            return null;
        }
        $str = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        return mb_substr(trim($str), 0, $limit);
    }
}
