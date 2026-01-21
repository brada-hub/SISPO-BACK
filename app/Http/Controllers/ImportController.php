<?php

namespace App\Http\Controllers;

use App\Models\Postulante;
use App\Models\Postulacion;
use App\Models\Convocatoria;
use App\Models\Oferta;
use App\Models\Sede;
use App\Models\Cargo;
use App\Models\PostulanteMerito;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'convocatoria_id' => 'nullable|exists:convocatorias,id'
        ]);

        $file = $request->file('file');

        // Check if it's a CSV to avoid ZipArchive requirement
        $extension = $file->getClientOriginalExtension();

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'No se pudo leer el archivo. Si es Excel (.xlsx), asegúrese que la extensión ZIP de PHP esté activa. Si no, use formato .csv',
                'message' => $e->getMessage()
            ], 500);
        }

        $targetSheets = ['EAL', 'IVI', 'LPZ', 'PTO', 'SCZ'];
        $availableSheets = $spreadsheet->getSheetNames();

        // Filter target sheets that actually exist in the file
        $sheetsToProcess = array_intersect($targetSheets, $availableSheets);

        // If none of the specific sheets are found, use all sheets
        if (empty($sheetsToProcess)) {
            $sheetsToProcess = $availableSheets;
        }

        $results = [
            'total' => 0,
            'imported' => 0,
            'errors' => [],
            'sheets_processed' => $sheetsToProcess
        ];

        // Create or find global migration convocatoria
        $convocatoriaId = $request->convocatoria_id;
        if (!$convocatoriaId) {
            $convocatoria = Convocatoria::firstOrCreate(
                ['titulo' => 'MIGRACIÓN DATA EXTERNA (GOOGLE FORMS)'],
                [
                    'descripcion' => 'Importación de datos históricos desde formularios externos por sedes',
                    'fecha_inicio' => '2026-01-12',
                    'fecha_cierre' => '2026-01-13',
                    'config_requisitos_ids' => [1], // Formación Académica
                    'gestion' => date('Y')
                ]
            );
            $convocatoriaId = $convocatoria->id;
        }

        foreach ($sheetsToProcess as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $rows = $sheet->toArray();

            // Find headers
            $idxSede = -1; $idxCarrera = -1; $idxNombre = -1; $idxProfesion = -1; $idxFechaTitulo = -1; $idxCI = -1;
            $idxPretension = -1;
            $headerRowIndex = 0;

            foreach ($rows as $r => $columns) {
                $hasHeaders = false;
                foreach ($columns as $c => $cellValue) {
                    $header = strtoupper(trim((string)$cellValue));
                    if (str_contains($header, 'SEDE')) { $idxSede = $c; $hasHeaders = true; }
                    if (str_contains($header, 'CARRERA')) { $idxCarrera = $c; $hasHeaders = true; }
                    if (str_contains($header, 'NOMBRE COMPLETO')) { $idxNombre = $c; $hasHeaders = true; }
                    if (str_contains($header, 'PROFESIÓN')) { $idxProfesion = $c; $hasHeaders = true; }
                    if (str_contains($header, 'FECHA DEL TÍTULO')) { $idxFechaTitulo = $c; $hasHeaders = true; }
                    if (str_contains($header, 'CI') || str_contains($header, 'CEDULA')) { $idxCI = $c; $hasHeaders = true; }
                    if (str_contains($header, 'PRETENSIÓN') || str_contains($header, 'SALARIAL')) { $idxPretension = $c; $hasHeaders = true; }
                }
                if ($hasHeaders && $idxNombre !== -1) {
                    $headerRowIndex = $r;
                    break;
                }
            }

            // Fallback indices if header scan fails
            if ($idxNombre == -1) {
                $idxSede = 1; $idxCarrera = 2; $idxNombre = 3; $idxProfesion = 4; $idxFechaTitulo = 5;
                $headerRowIndex = 1;
            }

            // Process data rows
            for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (!isset($row[$idxNombre]) || empty(trim((string)$row[$idxNombre]))) continue;

                try {
                    DB::beginTransaction();

                    $sedeNombre = trim((string)($row[$idxSede] ?? $sheetName));
                    $cargoNombre = trim((string)($row[$idxCarrera] ?? 'CARGO NO DEFINIDO'));
                    $nombreCompleto = trim((string)$row[$idxNombre]);
                    $profesion = trim((string)($row[$idxProfesion] ?? '-'));
                    $fechaTituloRaw = trim((string)($row[$idxFechaTitulo] ?? ''));
                    $pretension = ($idxPretension != -1) ? floatval(preg_replace('/[^0-9.]/', '', (string)$row[$idxPretension])) : 0;

                    // 1. Sede y Cargo
                    $sede = Sede::firstOrCreate(['nombre' => strtoupper($sedeNombre)]);
                    $cargo = Cargo::firstOrCreate(['nombre' => strtoupper($cargoNombre)]);

                    // 2. Oferta
                    $oferta = Oferta::firstOrCreate([
                        'convocatoria_id' => $convocatoriaId,
                        'sede_id' => $sede->id,
                        'cargo_id' => $cargo->id
                    ], ['vacantes' => 1]);

                    // 3. Postulante (Identification by CI or Name-based logic if CI missing)
                    $ci = ($idxCI != -1 && !empty($row[$idxCI])) ? trim((string)$row[$idxCI]) : null;

                    // Name splitting logic
                    $parts = array_filter(explode(' ', $nombreCompleto));
                    $parts = array_values($parts);
                    if (count($parts) >= 4) {
                        $nombres = $parts[0] . ' ' . $parts[1];
                        $apellidos = implode(' ', array_slice($parts, 2));
                    } elseif (count($parts) == 3) {
                        $nombres = $parts[0];
                        $apellidos = $parts[1] . ' ' . $parts[2];
                    } else {
                        $nombres = $parts[0] ?? $nombreCompleto;
                        $apellidos = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : 'S/A';
                    }

                    if (!$ci) {
                        // Look for existing by name to avoid duplicates if CI is missing
                        $postulante = Postulante::where('nombres', strtoupper($nombres))
                            ->where('apellidos', strtoupper($apellidos))
                            ->first();

                        if (!$postulante) {
                            $ci = "EXT-" . Str::upper(Str::random(4)) . "-" . ($results['total'] + 1);
                        } else {
                            $ci = $postulante->ci;
                        }
                    }

                    $postulante = Postulante::updateOrCreate(['ci' => $ci], [
                        'nombres' => strtoupper($nombres),
                        'apellidos' => strtoupper($apellidos),
                        'email' => Str::slug($nombreCompleto) . '@externo.com',
                    ]);

                    // 4. Postulacion (Link)
                    $postulacion = Postulacion::updateOrCreate([
                        'postulante_id' => $postulante->id,
                        'oferta_id' => $oferta->id
                    ], [
                        'pretension_salarial' => $pretension ?: DB::raw('pretension_salarial'),
                        // We keep the first state or specific updates
                    ]);

                    // 5. Merit Data
                    $tipoFormacion = TipoDocumento::where('nombre', 'LIKE', '%FORMACIÓN%')->first();
                    if ($tipoFormacion) {
                        $fechaTitulo = null;
                        if ($fechaTituloRaw) {
                            try {
                                $fechaTitulo = date('Y-m-d', strtotime(str_replace('/', '-', $fechaTituloRaw)));
                            } catch (\Exception $e) {}
                        }

                        PostulanteMerito::updateOrCreate([
                            'postulante_id' => $postulante->id,
                            'tipo_documento_id' => $tipoFormacion->id
                        ], [
                            'respuestas' => [
                                'nivel' => 'LICENCIATURA',
                                'profesion' => strtoupper($profesion),
                                'fecha_titulo' => $fechaTitulo,
                                'universidad' => 'MIGRACIÓN'
                            ],
                            'estado_verificacion' => 'validado'
                        ]);
                    }

                    DB::commit();
                    $results['imported']++;
                    $results['total']++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $results['errors'][] = "Error en hoja $sheetName, fila " . ($i+1) . ": " . $e->getMessage();
                }
            }
        }

        return response()->json($results);
    }
}
