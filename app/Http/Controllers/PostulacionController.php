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

class PostulacionController extends Controller
{
    public function index(Request $request)
    {
        $query = Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede']);

        if ($request->has('convocatoria_id')) {
            $query->whereHas('oferta', function($q) use ($request) {
                $q->where('convocatoria_id', $request->convocatoria_id);
            });
        }

        return $query->orderBy('id', 'desc')->get();
    }

    public function show($id)
    {
        return Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede'])->findOrFail($id);
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
            $postulaciones = Postulacion::with(['postulante.meritos.tipoDocumento', 'oferta.cargo', 'oferta.sede'])
                ->whereHas('oferta', function($q) use ($convocatoriaId) {
                    $q->where('convocatoria_id', $convocatoriaId);
                })
                ->get();

            $storageUrl = url('/storage');

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Postulantes');

            // 1. Headers (No ID POSTULACION)
            $headers = ['ESTADO','FECHA POSTULACION','CARGO','SEDE'];
            $personalHeaders = [
                'NOMBRES','APELLIDOS','CI','EXPEDIDO','NACIONALIDAD','CELULAR','EMAIL',
                'DIRECCION','PRETENSION SALARIAL','MOTIVACION','REF PERSONAL','CEL REF PERS',
                'CEL REF LAB','DETALLE REF LAB',
                'CI LINK','FOTO LINK','CV LINK','CARTA LINK'
            ];
            $headers = array_merge($headers, $personalHeaders);

            $tiposIds = $convocatoria->config_requisitos_ids ?? [];
            $tiposDocumento = TipoDocumento::whereIn('id', $tiposIds)->get();
            $meritFieldKeys = [];
            foreach ($tiposDocumento as $tipo) {
                if ($tipo->campos) {
                    foreach ($tipo->campos as $campo) {
                        $key = $campo['key'] ?? $campo['name'] ?? null;
                        if (!$key) continue;
                        $headers[] = strtoupper($tipo->nombre) . ": " . strtoupper($campo['label']);
                        $meritFieldKeys[] = ['tipo_id' => $tipo->id, 'key' => $key];
                    }
                }
            }

            // Apply Header Styles (Morado Premium)
            $lastColIdx = count($headers);
            $lastColLetter = Coordinate::stringFromColumnIndex($lastColIdx);
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '6A1B9A']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ]);
            $sheet->getRowDimension(1)->setRowHeight(30);

            // 2. Data
            $rowIdx = 2;
            $statusColors = [
                'enviada' => 'E3F2FD',
                'en_revision' => 'FFF8E1',
                'validada' => 'E8F5E9',
                'observada' => 'FFF3E0',
                'rechazada' => 'FFEBEE'
            ];

            foreach ($postulaciones as $p) {
                $post = $p->postulante;
                if (!$post) continue;

                $dataRow = [
                    strtoupper($p->estado),
                    $p->created_at ? $p->created_at->format('d/m/Y') : '',
                    strtoupper($p->oferta->cargo->nombre ?? 'N/A'),
                    strtoupper($p->oferta->sede->nombre ?? 'N/A'),
                    strtoupper($post->nombres),
                    strtoupper($post->apellidos),
                    $post->ci,
                    strtoupper($post->ci_expedido),
                    strtoupper($post->nacionalidad),
                    $post->celular,
                    $post->email,
                    strtoupper($post->direccion_domicilio),
                    $p->pretension_salarial,
                    strtoupper($p->porque_cargo),
                    strtoupper($post->ref_personal_parentesco),
                    $post->ref_personal_celular,
                    $post->ref_laboral_celular,
                    strtoupper($post->ref_laboral_detalle),
                    $post->ci_archivo_path ? "{$storageUrl}/{$post->ci_archivo_path}" : '',
                    $post->foto_perfil_path ? "{$storageUrl}/{$post->foto_perfil_path}" : '',
                    $post->cv_pdf_path ? "{$storageUrl}/{$post->cv_pdf_path}" : '',
                    $post->carta_postulacion_path ? "{$storageUrl}/{$post->carta_postulacion_path}" : '',
                ];

                foreach ($meritFieldKeys as $config) {
                    $meritos = $post->meritos->where('tipo_documento_id', $config['tipo_id']);
                    $vals = []; $i = 1;
                    foreach ($meritos as $m) {
                        $v = $m->respuestas[$config['key']] ?? '';
                        if ($v) {
                            $vals[] = ($meritos->count() > 1 ? "{$i}. " : "") . strtoupper($v);
                            $i++;
                        }
                    }
                    $dataRow[] = implode("\n", $vals);
                }

                $sheet->fromArray($dataRow, null, 'A' . $rowIdx);

                // Style Row
                $color = $statusColors[$p->estado] ?? 'FFFFFF';
                $range = "A{$rowIdx}:{$lastColLetter}{$rowIdx}";
                $sheet->getStyle($range)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]
                ]);

                // Hyperlinks (Linking columns O-R approx)
                // Col indices: ci link is 18 (S) to carta link 21 (V)
                for($c = 19; $c <= 22; $c++) {
                    $cell = Coordinate::stringFromColumnIndex($c) . $rowIdx;
                    $val = $sheet->getCell($cell)->getValue();
                    if($val && str_starts_with($val, 'http')) {
                        $sheet->getCell($cell)->getHyperlink()->setUrl($val);
                        $sheet->setCellValue($cell, 'DESCARGAR');
                        $sheet->getStyle($cell)->getFont()->getColor()->setRGB('1565C0');
                        $sheet->getStyle($cell)->getFont()->setUnderline(true);
                    }
                }

                $rowIdx++;
            }

            // Auto-size columns
            for ($i = 1; $i <= $lastColIdx; $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = "Reporte_Postulantes_" . str_replace(' ', '_', $convocatoria->titulo) . ".xlsx";

            return response()->streamDownload(function() use ($writer) {
                $writer->save('php://output');
            }, $filename);

        } catch (\Throwable $e) {
            \Log::error("Error en exportación: " . $e->getMessage());
            return response()->json(['error' => 'Error interno en el servidor al generar el Excel.'], 500);
        }
    }

    private function exportBasic()
    {
        try {
            $postulaciones = Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede'])->get();
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['POSTULANTE','CI','PRETENSION','CELULAR','EMAIL','CARGO','SEDE','ESTADO','FECHA'];
            $sheet->fromArray($headers, null, 'A1');

            $lastCol = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6A1B9A']]
            ]);

            $rowIdx = 2;
            $statusColors = [
                'enviada' => 'E3F2FD', 'en_revision' => 'FFF8E1', 'validada' => 'E8F5E9', 'observada' => 'FFF3E0', 'rechazada' => 'FFEBEE'
            ];

            foreach ($postulaciones as $p) {
                if (!$p->postulante) continue;
                $dataRow = [
                    strtoupper($p->postulante->nombres . ' ' . $p->postulante->apellidos),
                    $p->postulante->ci,
                    $p->pretension_salarial,
                    $p->postulante->celular,
                    $p->postulante->email,
                    strtoupper($p->oferta->cargo->nombre ?? 'N/A'),
                    strtoupper($p->oferta->sede->nombre ?? 'N/A'),
                    strtoupper($p->estado),
                    $p->created_at ? $p->created_at->format('d/m/Y') : ''
                ];
                $sheet->fromArray($dataRow, null, 'A' . $rowIdx);
                $color = $statusColors[$p->estado] ?? 'FFFFFF';
                $sheet->getStyle("A{$rowIdx}:{$lastCol}{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
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
}
