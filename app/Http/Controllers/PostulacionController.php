<?php

namespace App\Http\Controllers;

use App\Models\Postulacion;
use App\Models\Convocatoria;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PostulacionController extends Controller
{
    public function index(Request $request)
    {
        $query = Postulacion::with(['postulante.meritos.tipoDocumento', 'oferta.cargo', 'oferta.sede', 'evaluacion']);

        if ($request->has('convocatoria_id')) {
            $query->whereHas('oferta', function($q) use ($request) {
                $q->where('convocatoria_id', $request->convocatoria_id);
            });
        }

        if ($request->has('sede_id') && $request->sede_id !== 'null' && $request->sede_id !== '') {
            $query->whereHas('oferta', function($q) use ($request) {
                $q->where('sede_id', $request->sede_id);
            });
        }

        if ($request->has('cargo_id') && $request->cargo_id !== 'null' && $request->cargo_id !== '') {
            $query->whereHas('oferta', function($q) use ($request) {
                $q->where('cargo_id', $request->cargo_id);
            });
        }

        return $query->orderBy('id', 'desc')->get();
    }

    public function show($id)
    {
        return Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede', 'evaluacion'])->findOrFail($id);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'estado' => 'required|in:enviada,en_revision,validada,observada,rechazada'
        ]);

        $postulacion = Postulacion::findOrFail($id);
        $postulacion->estado = $validated['estado'];
        $postulacion->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'estado' => $postulacion->estado
        ]);
    }

    public function expediente($id)
    {
        $postulacion = Postulacion::with([
            'postulante.meritos.tipoDocumento',
            'postulante.meritos.archivos',
            'oferta.cargo',
            'oferta.sede',
            'oferta.convocatoria'
        ])->findOrFail($id);

        return response()->json($postulacion);
    }

    public function export($convocatoriaId = null)
    {
        try {
            if (!$convocatoriaId) {
                return $this->exportBasic();
            }

            $convocatoria = Convocatoria::findOrFail($convocatoriaId);
            $query = Postulacion::with(['postulante.meritos.tipoDocumento', 'oferta.cargo', 'oferta.sede', 'evaluacion'])
                ->whereHas('oferta', function($q) use ($convocatoriaId) {
                    $q->where('convocatoria_id', $convocatoriaId);
                });

            // Apply Filters
            if (request('search')) {
                $search = request('search');
                $query->whereHas('postulante', function($q) use ($search) {
                    $q->where('nombres', 'LIKE', "%{$search}%")
                      ->orWhere('apellidos', 'LIKE', "%{$search}%")
                      ->orWhere('ci', 'LIKE', "%{$search}%");
                });
            }
            if (request('estado')) $query->where('estado', request('estado'));
            if (request('sede_nombre')) {
                $sede = request('sede_nombre');
                $query->whereHas('oferta.sede', function($q) use ($sede) { $q->where('nombre', $sede); });
            }
            if (request('cargo_nombre')) {
                $cargo = request('cargo_nombre');
                $query->whereHas('oferta.cargo', function($q) use ($cargo) { $q->where('nombre', $cargo); });
            }
            if (request('salario_min')) $query->where('pretension_salarial', '>=', request('salario_min'));
            if (request('salario_max')) $query->where('pretension_salarial', '<=', request('salario_max'));

            $postulaciones = $query->get();

            // Prepare Dynamic Merit Headers
            $tiposIds = $convocatoria->config_requisitos_ids ?? [];
            $tiposDocumento = TipoDocumento::whereIn('id', $tiposIds)->get();
            $meritHeaders = [];
            $meritFieldKeys = [];
            foreach ($tiposDocumento as $tipo) {
                if ($tipo->campos) {
                    foreach ($tipo->campos as $campo) {
                        $key = $campo['key'] ?? $campo['name'] ?? null;
                        if (!$key) continue;
                        $meritHeaders[] = strtoupper($tipo->nombre) . ": " . strtoupper($campo['label']);
                        $meritFieldKeys[] = ['tipo_id' => $tipo->id, 'key' => $key];
                    }
                }
            }

            // Group by Sede and Cargo
            $grouped = $postulaciones->groupBy(function($item) {
                return strtoupper($item->oferta->sede->nombre ?? 'SEDE NO DEFINIDA') . ' - ' . strtoupper($item->oferta->cargo->nombre ?? 'CARGO NO DEFINIDO');
            })->sortKeys();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Matriz Técnica');

            // 1. Main Title
            $sheet->setCellValue('A1', 'MATRIZ TÉCNICA DE POSTULACIONES - ' . strtoupper($convocatoria->titulo));
            $coreHeaders = ['NO.', 'POSTULANTE', 'CI', 'CELULAR', 'EMAIL', 'ÁREA FORMACIÓN', 'AÑO TÍTULO', 'PRETENSIÓN (BS)'];
            $totalCols = count($coreHeaders) + count($meritHeaders) + 1; // +1 for Observations
            $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);
            $sheet->mergeCells("A1:{$lastColLetter}1");
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A148C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ]);
            $sheet->getRowDimension(1)->setRowHeight(35);

            $currentRow = 3;
            // Removed statusLabels as 'ESTADO' is no longer a core header

            foreach ($grouped as $groupName => $items) {
                // Group Header
                $sheet->setCellValue('A' . $currentRow, $groupName);
                $sheet->mergeCells("A{$currentRow}:{$lastColLetter}{$currentRow}");
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '009688']],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(25);
                $currentRow++;

                // Table Headers
                $headers = array_merge($coreHeaders, $meritHeaders, ['OBSERVACIONES']);
                $sheet->fromArray([$headers], null, 'A' . $currentRow);
                $headerRange = "A{$currentRow}:{$lastColLetter}{$currentRow}";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '4A148C']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3E5F5']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(45);
                $currentRow++;

                // Data
                $counter = 1;
                foreach ($items as $p) {
                    $post = $p->postulante;

                    // Extract Area and Year (Same as Matrix UI) - ADDED SAFETY CHECKS
                    $formacion = $post->meritos->where('tipoDocumento.nombre', 'FORMACIÓN ACADÉMICA')->first();
                    $area = '-';
                    $anio = '-';

                    if ($formacion && isset($formacion->respuestas)) {
                        $area = strtoupper($formacion->respuestas['profesion'] ?? '-');
                        $fechaTit = $formacion->respuestas['fecha_titulo'] ?? '';
                        $anio = $fechaTit ? substr($fechaTit, 0, 4) : '-';
                    }

                    $dataRow = [
                        $counter++,
                        strtoupper(($post->nombres ?? '') . ' ' . ($post->apellidos ?? '')),
                        $post->ci ?? '-',
                        $post->celular ?? '-',
                        strtolower($post->email ?? '-'),
                        $area,
                        $anio,
                        (float)($p->pretension_salarial ?? 0)
                    ];

                    // Dynamic Merit Values
                    foreach ($meritFieldKeys as $config) {
                        $merito = $post->meritos->where('tipo_documento_id', $config['tipo_id'])->first();
                        $val = '-';
                        if ($merito && isset($merito->respuestas)) {
                            $val = $merito->respuestas[$config['key']] ?? '-';
                            if (is_array($val)) $val = implode(', ', $val);
                        }
                        $dataRow[] = strtoupper((string)$val);
                    }

                    // Add Observations at the end
                    $obs = ($p->evaluacion) ? $p->evaluacion->observaciones : '-';
                    $dataRow[] = strtoupper((string)($obs ?: '-'));

                    $sheet->fromArray([$dataRow], null, 'A' . $currentRow);

                    // Formatting for numeric Pretension
                    $pretCol = Coordinate::stringFromColumnIndex(8);
                    $sheet->getStyle("{$pretCol}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

                    $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray([
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'EEEEEE']]]
                    ]);
                    $currentRow++;
                }
                $currentRow += 2;
            }

            // Auto-size columns
            foreach (range(1, $totalCols) as $colIdx) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = "Reporte_Matriz_" . str_replace([' ', '/', '\\'], '_', $convocatoria->titulo) . ".xlsx";

            return response()->streamDownload(function() use ($writer) {
                $writer->save('php://output');
            }, $filename);

        } catch (\Throwable $e) {
            \Log::error("Error en exportación integral: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function exportBasic()
    {
        try {
            $postulaciones = Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede'])->get();
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['SEDE','CARGO','NOMBRES','APELLIDOS','CELULAR','EMAIL','PRETENSION'];
            $sheet->fromArray($headers, null, 'A1');

            $lastCol = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6A1B9A']],
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]
            ]);
            $sheet->freezePane('A2');

            $rowIdx = 2;
            foreach ($postulaciones as $p) {
                if (!$p->postulante) continue;
                $dataRow = [
                    strtoupper($p->oferta->sede->nombre ?? 'N/A'),
                    strtoupper($p->oferta->cargo->nombre ?? 'N/A'),
                    strtoupper($p->postulante->nombres),
                    strtoupper($p->postulante->apellidos),
                    $p->postulante->celular,
                    $p->postulante->email,
                    $p->pretension_salarial
                ];
                $sheet->fromArray($dataRow, null, 'A' . $rowIdx);
                $rowIdx++;
            }

            for($i=1; $i<=count($headers); $i++) { $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true); }

            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(function() use ($writer) {
                $writer->save('php://output');
            }, 'postulaciones_general.xlsx');
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $user = auth()->user();
        if (!$user || $user->rol->nombre !== 'ADMINISTRADOR') {
            return response()->json(['message' => 'No tiene permisos para eliminar postulaciones'], 403);
        }

        $postulacion = Postulacion::findOrFail($id);
        $postulacion->delete();

        return response()->json(['success' => true, 'message' => 'Postulación eliminada correctamente']);
    }
}
