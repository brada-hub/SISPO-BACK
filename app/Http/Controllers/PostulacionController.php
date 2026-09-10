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
        $user = auth()->user();
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);
        $query = Postulacion::with([
            'postulante.meritos.tipoDocumento',
            'postulante.formacionesAcademicas.academicLevel',
            'postulante.formacionesAcademicas.career',
            'postulante.formacionesAcademicas.professionalArea',
            'postulante.formacionesPostgrado',
            'postulante.experienciasDocencia',
            'postulante.experienciasProfesionales',
            'postulante.capacitaciones',
            'postulante.produccionesIntelectuales',
            'postulante.reconocimientos',
            'postulante.experienceSummary',
            'postulante.trainingSummary',
            'oferta.cargo',
            'oferta.sede',
            'oferta.convocatoria',
            'evaluacion',
            'aiMatchingResult',
            'evaluationResult'
        ]);

        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereHas('oferta', function ($q) use ($allowedConvocatorias) {
                $q->whereIn('convocatoria_id', $allowedConvocatorias);
            });
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('oferta', function ($q) use ($allowedSedes) {
                $q->whereIn('sede_id', $allowedSedes);
            });
        }

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
        $user = auth()->user();
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);
        $query = Postulacion::with([
            'postulante.meritos.tipoDocumento',
            'postulante.formacionesAcademicas.academicLevel',
            'postulante.formacionesAcademicas.career',
            'postulante.formacionesAcademicas.professionalArea',
            'postulante.experienciasProfesionales',
            'postulante.experienceSummary',
            'postulante.trainingSummary',
            'oferta.cargo',
            'oferta.sede',
            'oferta.convocatoria',
            'evaluacion',
            'aiMatchingResult'
        ]);

        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereHas('oferta', function ($q) use ($allowedConvocatorias) {
                $q->whereIn('convocatoria_id', $allowedConvocatorias);
            });
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('oferta', function ($q) use ($allowedSedes) {
                $q->whereIn('sede_id', $allowedSedes);
            });
        }

        return $query->findOrFail($id);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'estado' => 'required|in:pendiente_archivos,enviada,en_revision,validada,observada,rechazada,seleccionado'
        ]);

        $user = auth()->user();
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);
        $query = Postulacion::query();

        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereHas('oferta', function ($q) use ($allowedConvocatorias) {
                $q->whereIn('convocatoria_id', $allowedConvocatorias);
            });
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('oferta', function ($q) use ($allowedSedes) {
                $q->whereIn('sede_id', $allowedSedes);
            });
        }

        $postulacion = $query->findOrFail($id);
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
        $user = auth()->user();
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);
        $query = Postulacion::with([
            'postulante.formacionesAcademicas',
            'postulante.formacionesPostgrado',
            'postulante.experienciasDocencia',
            'postulante.experienciasProfesionales',
            'postulante.capacitaciones',
            'postulante.produccionesIntelectuales',
            'postulante.reconocimientos',
            'postulante.experienceSummary',
            'postulante.trainingSummary',
            'postulante.sede',
            'oferta.cargo',
            'oferta.sede',
            'oferta.convocatoria'
        ]);

        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereHas('oferta', function ($q) use ($allowedConvocatorias) {
                $q->whereIn('convocatoria_id', $allowedConvocatorias);
            });
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('oferta', function ($q) use ($allowedSedes) {
                $q->whereIn('sede_id', $allowedSedes);
            });
        }

        $postulacion = $query->findOrFail($id);
        $data = $postulacion->toArray();
        if ($postulacion->postulante) {
            $data['postulante'] = (new \App\Http\Resources\ExpedienteNormalizedResource($postulacion->postulante))->toArray(request());
        }
        return response()->json($data);
    }

    public function export($convocatoriaId = null)
    {
        try {
            if (!$convocatoriaId) {
                return $this->exportBasic();
            }

            $convocatoria = Convocatoria::findOrFail($convocatoriaId);
            $user = auth()->user();
            $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
            $allowedSedes = $this->allowedSedeIds($user);
            $query = Postulacion::with([
                'postulante.meritos.tipoDocumento',
                'postulante.formacionesAcademicas.academicLevel',
                'postulante.formacionesAcademicas.career',
                'postulante.formacionesAcademicas.professionalArea',
                'postulante.formacionesPostgrado',
                'postulante.experienciasDocencia',
                'postulante.experienciasProfesionales',
                'postulante.capacitaciones',
                'postulante.produccionesIntelectuales',
                'postulante.reconocimientos',
                'oferta.cargo',
                'oferta.sede',
                'oferta.convocatoria',
                'evaluacion',
                'evaluationResult',
                'aiMatchingResult'
            ])
                ->whereHas('oferta', function($q) use ($convocatoriaId, $user, $allowedConvocatorias, $allowedSedes) {
                    $q->where('convocatoria_id', $convocatoriaId);
                    if ($this->shouldLimitByConvocatoria($user)) {
                        $q->whereIn('convocatoria_id', $allowedConvocatorias);
                    } elseif (!empty($allowedSedes)) {
                        $q->whereIn('sede_id', $allowedSedes);
                    }
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
                $query->whereHas('oferta', function($q) use ($sede) {
                    $q->whereExists(function ($sub) use ($sede) {
                        $sub->select(\DB::raw(1))
                            ->from('sedes')
                            ->whereColumn('ofertas.sede_id', 'sedes.id')
                            ->where('sedes.nombre', $sede);
                    });
                });
            }
            if (request('cargo_nombre')) {
                $cargo = request('cargo_nombre');
                $query->whereHas('oferta', function($q) use ($cargo) {
                    $q->whereExists(function ($sub) use ($cargo) {
                        $sub->select(\DB::raw(1))
                            ->from('cargos')
                            ->whereColumn('ofertas.cargo_id', 'cargos.id')
                            ->where('cargos.nombre', $cargo);
                    });
                });
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
            $sheet->setTitle('Reporte Convocatoria');

            // Status label mapping
            $statusLabels = [
                'enviada' => 'POSTULADO',
                'en_revision' => 'EN EVALUACIÓN',
                'validada' => 'PRESELECCIONADO',
                'observada' => 'CON OBSERVACIÓN',
                'rechazada' => 'NO SELECCIONADO',
                'seleccionado' => 'SELECCIONADO',
            ];

            // 1. Main Title
            $sheet->setCellValue('A1', 'REPORTE GENERAL INTEGRAL DE POSTULACIONES Y MÉRITOS - ' . strtoupper($convocatoria->titulo));
            $coreHeaders = [
                'NO.',
                'ESTADO',
                'POSTULANTE',
                'CI',
                'CELULAR',
                'EMAIL',
                'SCORE EVALUACIÓN',
                'PRETENSIÓN (BS)',
                'FORMACIÓN ACADÉMICA (PREGRADO)',
                'FORMACIÓN POSTGRADO',
                'DOCENCIA UNIVERSITARIA',
                'EXPERIENCIA PROFESIONAL',
                'CAPACITACIONES Y CURSOS',
                'PRODUCCIÓN INTELECTUAL',
                'RECONOCIMIENTOS',
                'REFERENCIAS'
            ];
            $totalCols = count($coreHeaders) + count($meritHeaders) + 1; // +1 for Observations
            $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);
            $sheet->mergeCells("A1:{$lastColLetter}1");
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A148C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ]);
            $sheet->getRowDimension(1)->setRowHeight(35);

            $currentRow = 3;

            foreach ($grouped as $groupName => $items) {
                // Group Header
                $sheet->setCellValue('A' . $currentRow, $groupName . ' (' . count($items) . ' POSTULANTES)');
                $sheet->mergeCells("A{$currentRow}:{$lastColLetter}{$currentRow}");
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
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
                $sheet->getRowDimension($currentRow)->setRowHeight(35);
                $currentRow++;

                // Data
                $counter = 1;
                foreach ($items as $p) {
                    $post = $p->postulante;

                    // 1. Formaciones Académicas (Pregrado)
                    $formList = [];
                    if ($post && $post->formacionesAcademicas && count($post->formacionesAcademicas) > 0) {
                        foreach ($post->formacionesAcademicas as $idx => $f) {
                            $nivel = $f->academicLevel->name ?? $f->nivel_academico_raw ?? 'LICENCIATURA';
                            $carrera = $f->career->name ?? $f->carrera_raw ?? 'CARRERA';
                            $univ = $f->universidad ?? '';
                            $anio = $f->fecha_titulo ? substr($f->fecha_titulo, 0, 4) : '';
                            $formList[] = "[" . ($idx + 1) . "] {$nivel}: {$carrera} | {$univ} | Año: {$anio}";
                        }
                    }
                    if (empty($formList) && $post && $post->meritos) {
                        $meritoPre = $post->meritos->where('tipoDocumento.nombre', 'FORMACIÓN ACADÉMICA')->first();
                        if ($meritoPre && !empty($meritoPre->respuestas)) {
                            $resp = is_array($meritoPre->respuestas) ? $meritoPre->respuestas : json_decode($meritoPre->respuestas, true);
                            if (is_array($resp)) {
                                $prof = $resp['profesion'] ?? $resp['carrera'] ?? '';
                                $univ = $resp['universidad'] ?? '';
                                $anio = !empty($resp['fecha_titulo']) ? substr($resp['fecha_titulo'], 0, 4) : '';
                                if ($prof) $formList[] = "[1] {$prof} | {$univ} | Año: {$anio}";
                            }
                        }
                    }
                    $formacionStr = !empty($formList) ? implode("\n", $formList) : '-';

                    // 2. Formaciones Postgrado
                    $posList = [];
                    if ($post && $post->formacionesPostgrado && count($post->formacionesPostgrado) > 0) {
                        foreach ($post->formacionesPostgrado as $idx => $pos) {
                            $tipo = $pos->tipo_postgrado ?? 'POSTGRADO';
                            $tit = $pos->titulo_postgrado ?? 'TÍTULO';
                            $univ = $pos->universidad ?? '';
                            $posList[] = "[" . ($idx + 1) . "] {$tipo}: {$tit} | {$univ}";
                        }
                    }
                    if (empty($posList) && $post && $post->meritos) {
                        $meritoPos = $post->meritos->first(function($m) {
                            $nom = strtoupper($m->tipoDocumento->nombre ?? '');
                            return str_contains($nom, 'POSTGRADO') || str_contains($nom, 'POSGRADO');
                        });
                        if ($meritoPos && !empty($meritoPos->respuestas)) {
                            $resp = is_array($meritoPos->respuestas) ? $meritoPos->respuestas : json_decode($meritoPos->respuestas, true);
                            if (is_array($resp)) {
                                $posList[] = "[1] " . ($resp['titulo'] ?? $resp['tipo'] ?? 'POSTGRADO') . " | " . ($resp['institucion'] ?? '');
                            }
                        }
                    }
                    $postgradoStr = !empty($posList) ? implode("\n", $posList) : '-';

                    // 3. Experiencia Docencia
                    $docList = [];
                    if ($post && $post->experienciasDocencia && count($post->experienciasDocencia) > 0) {
                        foreach ($post->experienciasDocencia as $idx => $doc) {
                            $asig = $doc->asignatura ?? 'ASIGNATURA';
                            $univ = $doc->universidad ?? '';
                            $tipo = $doc->tipo_docencia ?? '';
                            $docList[] = "[" . ($idx + 1) . "] {$asig} | {$univ} | {$tipo}";
                        }
                    }
                    $docenciaStr = !empty($docList) ? implode("\n", $docList) : '-';

                    // 4. Experiencia Profesional
                    $expList = [];
                    if ($post && $post->experienciasProfesionales && count($post->experienciasProfesionales) > 0) {
                        foreach ($post->experienciasProfesionales as $idx => $exp) {
                            $carg = $exp->cargo_desempenado ?? 'CARGO';
                            $inst = $exp->institucion_empresa ?? '';
                            $expList[] = "[" . ($idx + 1) . "] {$carg} | {$inst}";
                        }
                    }
                    $expProfStr = !empty($expList) ? implode("\n", $expList) : '-';

                    // 5. Capacitaciones
                    $capList = [];
                    if ($post && $post->capacitaciones && count($post->capacitaciones) > 0) {
                        foreach ($post->capacitaciones as $idx => $cap) {
                            $nom = $cap->nombre_curso ?? 'CURSO';
                            $inst = $cap->institucion ?? '';
                            $hrs = $cap->horas_academicas ? "({$cap->horas_academicas} hrs)" : '';
                            $capList[] = "[" . ($idx + 1) . "] {$nom} | {$inst} {$hrs}";
                        }
                    }
                    $capStr = !empty($capList) ? implode("\n", $capList) : '-';

                    // 6. Producción Intelectual
                    $prodList = [];
                    if ($post && $post->produccionesIntelectuales && count($post->produccionesIntelectuales) > 0) {
                        foreach ($post->produccionesIntelectuales as $idx => $prod) {
                            $tipo = $prod->tipo_produccion ?? 'PUBLICACIÓN';
                            $tit = $prod->titulo_obra ?? 'TÍTULO';
                            $prodList[] = "[" . ($idx + 1) . "] {$tipo}: {$tit}";
                        }
                    }
                    $prodStr = !empty($prodList) ? implode("\n", $prodList) : '-';

                    // 7. Reconocimientos
                    $recList = [];
                    if ($post && $post->reconocimientos && count($post->reconocimientos) > 0) {
                        foreach ($post->reconocimientos as $idx => $rec) {
                            $desc = $rec->descripcion_reconocimiento ?? 'DISTINCIÓN';
                            $inst = $rec->institucion_otorgante ?? '';
                            $recList[] = "[" . ($idx + 1) . "] {$desc} | {$inst}";
                        }
                    }
                    $recStr = !empty($recList) ? implode("\n", $recList) : '-';

                    // 8. Referencias
                    $refList = [];
                    if ($post && $post->ref_personal_celular) {
                        $refList[] = "Personal: " . ($post->ref_personal_parentesco ? "({$post->ref_personal_parentesco}) " : "") . "Cel: {$post->ref_personal_celular}";
                    }
                    if ($post && ($post->ref_laboral_celular || $post->ref_laboral_detalle)) {
                        $refList[] = "Laboral: " . ($post->ref_laboral_detalle ?? '') . " Cel: " . ($post->ref_laboral_celular ?? '-');
                    }
                    $refStr = !empty($refList) ? implode("\n", $refList) : '-';

                    // Score
                    $scoreVal = $p->evaluacion->score_total ?? $p->evaluacion->puntaje_total ?? null;
                    $scoreStr = ($scoreVal !== null && $scoreVal !== '') ? (string)round((float)$scoreVal, 2) . ' pts' : 'SIN EVALUAR';

                    $estadoStr = $statusLabels[$p->estado] ?? strtoupper($p->estado ?? '-');

                    $dataRow = [
                        $counter++,
                        $estadoStr,
                        strtoupper(($post->nombres ?? '') . ' ' . ($post->apellidos ?? '')),
                        $post->ci ?? '-',
                        $post->celular ?? '-',
                        strtolower($post->email ?? '-'),
                        $scoreStr,
                        (float)($p->pretension_salarial ?? 0),
                        $formacionStr,
                        $postgradoStr,
                        $docenciaStr,
                        $expProfStr,
                        $capStr,
                        $prodStr,
                        $recStr,
                        $refStr
                    ];

                    // Dynamic Merit Values
                    foreach ($meritFieldKeys as $config) {
                        $merito = $post->meritos->where('tipo_documento_id', $config['tipo_id'])->first();
                        $val = '-';
                        if ($merito && !empty($merito->respuestas)) {
                            $respuestas = is_array($merito->respuestas) ? $merito->respuestas : json_decode($merito->respuestas, true);
                            if (is_array($respuestas)) {
                                $val = $respuestas[$config['key']] ?? '-';
                                if (is_array($val)) $val = implode(', ', $val);
                            }
                        }
                        $dataRow[] = strtoupper((string)$val);
                    }

                    // Add Observations at the end
                    $obs = '-';
                    if ($p->evaluacion) {
                        $obs = $p->evaluacion->review_reason_summary ?: ($p->evaluacion->observaciones ?? '-');
                    }
                    $dataRow[] = strtoupper((string)($obs ?: '-'));

                    $sheet->fromArray([$dataRow], null, 'A' . $currentRow);

                    // Formatting for numeric Pretension (column 8)
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
            $user = auth()->user();
            $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
            $allowedSedes = $this->allowedSedeIds($user);
            $query = Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede']);

            if ($this->shouldLimitByConvocatoria($user)) {
                $query->whereHas('oferta', function ($q) use ($allowedConvocatorias) {
                    $q->whereIn('convocatoria_id', $allowedConvocatorias);
                });
            } elseif (!empty($allowedSedes)) {
                $query->whereHas('oferta', function ($q) use ($allowedSedes) {
                    $q->whereIn('sede_id', $allowedSedes);
                });
            }

            $postulaciones = $query->get();
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

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // Allow admins or users with management permissions
        $hasPermission = $user->isAdminUser()
            || $user->can('usuarios')
            || $user->can('roles');

        if (!$hasPermission) {
            return response()->json(['message' => 'No tiene permisos para eliminar postulaciones'], 403);
        }

        $postulacion = Postulacion::findOrFail($id);
        $postulacion->delete();

        return response()->json(['success' => true, 'message' => 'Postulación eliminada correctamente']);
    }

    public function updateEvaluationStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'evaluation_status' => 'required|in:manually_approved,manually_rejected,requires_human_review',
            'manual_comment' => 'nullable|string'
        ]);

        $postulacion = Postulacion::findOrFail($id);
        $evalResult = $postulacion->evaluacion;

        if ($evalResult) {
            $evalResult->evaluation_status = $validated['evaluation_status'];
            if (!empty($validated['manual_comment'])) {
                $evalResult->review_reason_summary = $validated['manual_comment'];
            }
            $evalResult->requires_human_review = ($validated['evaluation_status'] === 'requires_human_review');
            $evalResult->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Decisión de auditoría registrada correctamente',
            'evaluation_status' => $evalResult ? $evalResult->evaluation_status : null
        ]);
    }

    private function shouldLimitByConvocatoria($user): bool
    {
        return $user && !$user->isAdminUser() && $user->hasConvocatoriaScope();
    }

    private function allowedConvocatoriaIds($user): array
    {
        return $user ? $user->allowedConvocatoriaIds() : [];
    }

    private function allowedSedeIds($user): array
    {
        if (!$user || $user->isAdminUser() || $user->hasConvocatoriaScope()) {
            return [];
        }

        return $user->allowedSedeIds();
    }
}
