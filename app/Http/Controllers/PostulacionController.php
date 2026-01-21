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

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Postulantes');

            // 1. Headers (ONLY requested columns)
            $coreHeaders = ['SEDE', 'CARGO', 'NOMBRES', 'APELLIDOS', 'CELULAR', 'EMAIL', 'PRETENSION SALARIAL'];

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
                        $meritFieldKeys[] = [
                            'tipo_id' => $tipo->id,
                            'key' => $key
                        ];
                    }
                }
            }

            $headers = array_merge($coreHeaders, $meritHeaders);

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
            $sheet->freezePane('A2');

            // 2. Data
            $rowIdx = 2;
            foreach ($postulaciones as $p) {
                $post = $p->postulante;
                if (!$post) continue;

                $dataRow = [
                    strtoupper($p->oferta->sede->nombre ?? 'N/A'),
                    strtoupper($p->oferta->cargo->nombre ?? 'N/A'),
                    strtoupper($post->nombres),
                    strtoupper($post->apellidos),
                    $post->celular,
                    $post->email,
                    $p->pretension_salarial,
                ];

                foreach ($meritFieldKeys as $config) {
                    $meritos = $post->meritos->where('tipo_documento_id', $config['tipo_id']);
                    $vals = []; $i = 1;
                    foreach ($meritos as $m) {
                        $v = $m->respuestas[$config['key']] ?? '';
                        if ($v) {
                            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $matches)) {
                                $v = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
                            }
                            $vals[] = ($meritos->count() > 1 ? "{$i}. " : "") . strtoupper($v);
                            $i++;
                        }
                    }
                    $dataRow[] = implode("\n", $vals);
                }

                $sheet->fromArray($dataRow, null, 'A' . $rowIdx);

                // Simple styling for row
                $range = "A{$rowIdx}:{$lastColLetter}{$rowIdx}";
                $sheet->getStyle($range)->applyFromArray([
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'wrapText' => true,
                        'indent' => 1
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ]);

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
}
