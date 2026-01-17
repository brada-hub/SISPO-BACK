<?php

namespace App\Http\Controllers;

use App\Models\Postulacion;
use Illuminate\Http\Request;

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

    /**
     * Update the status of a postulation
     */
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

    /**
     * Get full detail for the case file (Expediente)
     */
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

    /**
     * Export applicants of a specific convocatoria or all
     */
    public function export($convocatoriaId = null)
    {
        if (!$convocatoriaId) {
            return $this->exportBasic();
        }

        $convocatoria = \App\Models\Convocatoria::findOrFail($convocatoriaId);
        $postulaciones = Postulacion::with(['postulante.meritos.tipoDocumento', 'oferta.cargo', 'oferta.sede'])
            ->whereHas('oferta', function($q) use ($convocatoriaId) {
                $q->where('convocatoria_id', $convocatoriaId);
            })
            ->get();

        $storageUrl = url('/storage');

        // 1. Build Headers
        $headers = [
            'ID POSTULACION', 'ESTADO', 'FECHA POSTULACION', 'CARGO', 'SEDE'
        ];

        $personalHeaders = [
            'NOMBRES', 'APELLIDOS', 'CI', 'EXPEDIDO', 'NACIONALIDAD', 'CELULAR', 'EMAIL',
            'DIRECCION', 'REF PERSONAL PARENTESCO', 'REF PERSONAL CELULAR',
            'REF LABORAL CELULAR', 'REF LABORAL DETALLE',
            'LINK CI', 'LINK FOTO', 'LINK CV', 'LINK CARTA'
        ];
        $headers = array_merge($headers, $personalHeaders);

        // Merit headers based on convocatoria config
        $tiposIds = $convocatoria->config_requisitos_ids ?? [];
        $tiposDocumento = \App\Models\TipoDocumento::whereIn('id', $tiposIds)->get();

        $meritFieldKeys = [];
        foreach ($tiposDocumento as $tipo) {
            if ($tipo->campos) {
                foreach ($tipo->campos as $campo) {
                    $key = $campo['key'] ?? $campo['name'] ?? null;
                    if (!$key) continue;

                    $headers[] = strtoupper($tipo->nombre) . ": " . strtoupper($campo['label']);
                    $meritFieldKeys[] = [
                        'tipo_id' => $tipo->id,
                        'key' => $key
                    ];
                }
            }
        }

        // 2. Build HTML Table (Excel native-like with Microsoft namespaces for better compatibility)
        $output = "
        <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
        <head>
            <meta charset='UTF-8'>
            <!--[if gte mso 9]>
            <xml>
                <x:ExcelWorkbook>
                    <x:ExcelWorksheets>
                        <x:ExcelWorksheet>
                            <x:Name>Reporte de Postulantes</x:Name>
                            <x:WorksheetOptions>
                                <x:DisplayGridlines/>
                            </x:WorksheetOptions>
                        </x:ExcelWorksheet>
                    </x:ExcelWorksheets>
                </x:ExcelWorkbook>
            </xml>
            <![endif]-->
        </head>
        <body>
            <table border='1'>";

        // Header Row
        $output .= "<tr style='background-color:#663399; color:white; font-weight:bold;'>";
        foreach ($headers as $header) {
            $output .= "<td>" . strtoupper($header) . "</td>";
        }
        $output .= "</tr>";

        foreach ($postulaciones as $p) {
            $post = $p->postulante;
            if (!$post) continue;

            $row = [
                $p->id,
                strtoupper($p->estado),
                $p->fecha_postulacion ? $p->fecha_postulacion->format('d/m/Y') : '',
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
                strtoupper($post->ref_personal_parentesco),
                $post->ref_personal_celular,
                $post->ref_laboral_celular,
                strtoupper($post->ref_laboral_detalle),
                $post->ci_archivo_path ? "{$storageUrl}/{$post->ci_archivo_path}" : '',
                $post->foto_perfil_path ? "{$storageUrl}/{$post->foto_perfil_path}" : '',
                $post->cv_pdf_path ? "{$storageUrl}/{$post->cv_pdf_path}" : '',
                $post->carta_postulacion_path ? "{$storageUrl}/{$post->carta_postulacion_path}" : '',
            ];

            // Add merit values
            foreach ($meritFieldKeys as $config) {
                $meritos = $post->meritos->where('tipo_documento_id', $config['tipo_id']);
                $vals = [];
                $i = 1;
                foreach ($meritos as $m) {
                    $v = $m->respuestas[$config['key']] ?? '';
                    if ($v) {
                        $vals[] = ($meritos->count() > 1 ? "{$i}. " : "") . strtoupper($v);
                        $i++;
                    }
                }
                $row[] = implode("<br style='mso-data-placement:same-cell;'>", $vals);
            }

            $output .= "<tr>";
            foreach ($row as $val) {
                $output .= "<td>" . $val . "</td>";
            }
            $output .= "</tr>";
        }

        $output .= "</table></body></html>";

        $filename = "Reporte_Postulantes_" . str_replace(' ', '_', $convocatoria->titulo) . ".xls";

        return response($output)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }

    private function exportBasic()
    {
        $postulaciones = Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede'])->get();

        $output = "<html><head><meta charset='UTF-8'></head><body><table border='1'>";
        $output .= "<tr style='background-color:#663399; color:white; font-weight:bold;'>";
        $headers = ['ID','POSTULANTE','CI','CELULAR','EMAIL','CARGO','SEDE','ESTADO','FECHA'];
        foreach ($headers as $h) {
            $output .= "<td>$h</td>";
        }
        $output .= "</tr>";

        foreach ($postulaciones as $p) {
            if (!$p->postulante) continue;
            $output .= "<tr>";
            $output .= "<td>{$p->id}</td>";
            $output .= "<td>" . strtoupper($p->postulante->nombres . ' ' . $p->postulante->apellidos) . "</td>";
            $output .= "<td>{$p->postulante->ci}</td>";
            $output .= "<td>{$p->postulante->celular}</td>";
            $output .= "<td>{$p->postulante->email}</td>";
            $output .= "<td>" . strtoupper($p->oferta->cargo->nombre ?? 'N/A') . "</td>";
            $output .= "<td>" . strtoupper($p->oferta->sede->nombre ?? 'N/A') . "</td>";
            $output .= "<td>" . strtoupper($p->estado) . "</td>";
            $output .= "<td>" . ($p->fecha_postulacion ? $p->fecha_postulacion->format('d/m/Y') : '') . "</td>";
            $output .= "</tr>";
        }

        $output .= "</table></body></html>";

        return response($output)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="postulaciones_general.xls"');
    }
}
