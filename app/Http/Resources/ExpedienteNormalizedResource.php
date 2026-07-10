<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ExpedienteNormalizedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $postulante = $this->resource;
        if (!$postulante) {
            return [];
        }

        return [
            'id' => $postulante->id,
            'user_id' => $postulante->user_id,
            'nombres' => $postulante->nombres,
            'apellidos' => $postulante->apellidos,
            'ci' => $postulante->ci,
            'ci_expedido' => $postulante->ci_expedido,
            'ci_archivo_path' => $postulante->ci_archivo_path,
            'nacionalidad' => $postulante->nacionalidad,
            'direccion_domicilio' => $postulante->direccion_domicilio,
            'celular' => $postulante->celular,
            'email' => $postulante->email,
            'email_institucional' => $postulante->email_institucional,
            'foto_perfil_path' => $postulante->foto_perfil_path,
            'cv_pdf_path' => $postulante->cv_pdf_path,
            'carta_postulacion_path' => $postulante->carta_postulacion_path,
            'ref_personal_celular' => $postulante->ref_personal_celular,
            'ref_personal_parentesco' => $postulante->ref_personal_parentesco,
            'ref_laboral_celular' => $postulante->ref_laboral_celular,
            'ref_laboral_detalle' => $postulante->ref_laboral_detalle,
            'pretension_salarial' => $postulante->pretension_salarial,
            'porque_cargo' => $postulante->porque_cargo,

            // 7 Normalized Merits Tables with Legacy Fallback
            'formaciones_academicas' => $this->mapFormacionesAcademicas($postulante),
            'postgrados' => $this->mapFormacionesPostgrado($postulante),
            'experiencias_profesionales' => $this->mapExperienciasProfesionales($postulante),
            'experiencias_docencia' => $this->mapExperienciasDocencia($postulante),
            'capacitaciones' => $this->mapCapacitaciones($postulante),
            'producciones' => $this->mapProduccionesIntelectuales($postulante),
            'reconocimientos' => $this->mapReconocimientos($postulante),
            
            // Compatibility layer: virtual flat merits array for retro-compatibility
            'meritos' => $this->when(config('sispo_legacy.include_virtual_meritos'), function () use ($postulante) {
                return $this->buildVirtualMeritos($postulante);
            }),

            // Experience & Training summaries
            'experience_summary' => $postulante->experienceSummary,
            'training_summary' => $postulante->trainingSummary,
            'sede' => $postulante->sede,
        ];
    }

    private function getFileWithFallback($row, string $directPathCol, string $configArchivoId): ?string
    {
        if (!empty($row->$directPathCol)) {
            return $row->$directPathCol;
        }

        if (!empty($row->source_merito_id)) {
            $legacyFile = DB::table('merito_archivos')
                ->where('merito_id', $row->source_merito_id)
                ->where('config_archivo_id', $configArchivoId)
                ->first();
            if ($legacyFile) {
                \Illuminate\Support\Facades\Log::warning('LEGACY_FALLBACK_TRIGGERED: Lectura de archivo legacy detectada', [
                    'postulante_id' => $row->postulante_id ?? 'unknown',
                    'source_merito_id' => $row->source_merito_id,
                    'config_archivo_id' => $configArchivoId,
                    'service' => 'ExpedienteNormalizedResource'
                ]);
                return $legacyFile->archivo_path;
            }
        }

        return null;
    }

    private function mapFormacionesAcademicas($postulante): array
    {
        $rows = $postulante->formacionesAcademicas ?? collect();
        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'nivel_academico' => $row->nivel_academico_raw,
                'universidad' => $row->universidad,
                'carrera' => $row->carrera_raw,
                'fecha_diploma' => $row->fecha_diploma,
                'fecha_titulo' => $row->fecha_titulo,
                'diploma_archivo_path' => $this->getFileWithFallback($row, 'diploma_archivo_path', 'diploma'),
                'titulo_archivo_path' => $this->getFileWithFallback($row, 'titulo_archivo_path', 'titulo'),
            ];
        })->toArray();
    }

    private function mapFormacionesPostgrado($postulante): array
    {
        $rows = $postulante->formacionesPostgrado ?? collect();
        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'tipo_posgrado' => $row->tipo_posgrado_raw,
                'nombre_programa' => $row->nombre_programa,
                'fecha_certificacion' => $row->fecha_certificacion,
                'institucion' => $row->institucion,
                'certificado_archivo_path' => $this->getFileWithFallback($row, 'certificado_archivo_path', 'certificado'),
            ];
        })->toArray();
    }

    private function mapExperienciasProfesionales($postulante): array
    {
        $rows = $postulante->experienciasProfesionales ?? collect();
        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'cargo' => $row->cargo_raw,
                'empresa' => $row->empresa,
                'fecha_inicio' => $row->fecha_inicio,
                'fecha_fin' => $row->fecha_fin,
                'duracion_meses' => $row->duracion_meses,
                'certificado_archivo_path' => $this->getFileWithFallback($row, 'certificado_archivo_path', 'certificado'),
            ];
        })->toArray();
    }

    private function mapExperienciasDocencia($postulante): array
    {
        $rows = $postulante->experienciasDocencia ?? collect();
        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'universidad' => $row->universidad,
                'carrera' => $row->carrera_raw,
                'asignaturas' => $row->asignaturas,
                'gestion_periodo' => $row->gestion_periodo,
                'respaldo_archivo_path' => $this->getFileWithFallback($row, 'respaldo_archivo_path', 'respaldo'),
            ];
        })->toArray();
    }

    private function mapCapacitaciones($postulante): array
    {
        $rows = $postulante->capacitaciones ?? collect();
        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'nombre_curso' => $row->nombre_curso,
                'fecha' => $row->fecha,
                'institucion_organizadora' => $row->institucion_organizadora,
                'carga_horaria' => $row->carga_horaria,
                'certificado_archivo_path' => $this->getFileWithFallback($row, 'certificado_archivo_path', 'certificado'),
            ];
        })->toArray();
    }

    private function mapProduccionesIntelectuales($postulante): array
    {
        $rows = $postulante->produccionesIntelectuales ?? collect();
        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'tipo_produccion' => $row->tipo_produccion_raw,
                'titulo' => $row->titulo,
                'fecha_publicacion' => $row->fecha_publicacion,
                'editorial_revista' => $row->editorial_revista,
                'lugar' => $row->lugar,
                'evidencia_archivo_path' => $this->getFileWithFallback($row, 'evidencia_archivo_path', 'evidencia'),
            ];
        })->toArray();
    }

    private function mapReconocimientos($postulante): array
    {
        $rows = $postulante->reconocimientos ?? collect();
        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'titulo_reconocimiento' => $row->titulo_reconocimiento,
                'fecha' => $row->fecha,
                'institucion_otorgante' => $row->institucion_otorgante,
                'lugar' => $row->lugar,
                'reconocimiento_archivo_path' => $this->getFileWithFallback($row, 'reconocimiento_archivo_path', 'reconocimiento'),
            ];
        })->toArray();
    }

    private function buildVirtualMeritos($postulante): array
    {
        $virtual = [];

        // 1. Formación Académica
        foreach ($postulante->formacionesAcademicas ?? [] as $fa) {
            $archivos = [];
            $dip = $this->getFileWithFallback($fa, 'diploma_archivo_path', 'diploma');
            if ($dip) {
                $archivos[] = [
                    'id' => 'vfa_d_' . $fa->id,
                    'merito_id' => $fa->source_merito_id ?? $fa->id,
                    'config_archivo_id' => 'diploma',
                    'archivo_path' => $dip,
                ];
            }
            $tit = $this->getFileWithFallback($fa, 'titulo_archivo_path', 'titulo');
            if ($tit) {
                $archivos[] = [
                    'id' => 'vfa_t_' . $fa->id,
                    'merito_id' => $fa->source_merito_id ?? $fa->id,
                    'config_archivo_id' => 'titulo',
                    'archivo_path' => $tit,
                ];
            }

            $virtual[] = [
                'id' => $fa->source_merito_id ?? ('vfa_' . $fa->id),
                'tipo_documento_id' => 1,
                'respuestas' => [
                    'nivel' => $fa->nivel_academico_raw,
                    'universidad' => $fa->universidad,
                    'profesion' => $fa->carrera_raw,
                    'fecha_diploma' => $fa->fecha_diploma,
                    'fecha_titulo' => $fa->fecha_titulo,
                ],
                'archivos' => $archivos,
            ];
        }

        // 2. Formación Postgrado
        foreach ($postulante->formacionesPostgrado ?? [] as $fp) {
            $archivos = [];
            $cert = $this->getFileWithFallback($fp, 'certificado_archivo_path', 'certificado');
            if ($cert) {
                $archivos[] = [
                    'id' => 'vfp_c_' . $fp->id,
                    'merito_id' => $fp->source_merito_id ?? $fp->id,
                    'config_archivo_id' => 'certificado',
                    'archivo_path' => $cert,
                ];
            }

            $virtual[] = [
                'id' => $fp->source_merito_id ?? ('vfp_' . $fp->id),
                'tipo_documento_id' => 2,
                'respuestas' => [
                    'tipo_posgrado' => $fp->tipo_posgrado_raw,
                    'nombre_programa' => $fp->nombre_programa,
                    'fecha_certificacion' => $fp->fecha_certificacion,
                    'institucion' => $fp->institucion,
                ],
                'archivos' => $archivos,
            ];
        }

        // 3. Experiencia Docencia
        foreach ($postulante->experienciasDocencia ?? [] as $ed) {
            $archivos = [];
            $resp = $this->getFileWithFallback($ed, 'respaldo_archivo_path', 'respaldo');
            if ($resp) {
                $archivos[] = [
                    'id' => 'ved_r_' . $ed->id,
                    'merito_id' => $ed->source_merito_id ?? $ed->id,
                    'config_archivo_id' => 'respaldo',
                    'archivo_path' => $resp,
                ];
            }

            $virtual[] = [
                'id' => $ed->source_merito_id ?? ('ved_' . $ed->id),
                'tipo_documento_id' => 3,
                'respuestas' => [
                    'universidad' => $ed->universidad,
                    'carrera' => $ed->carrera_raw,
                    'asignaturas' => $ed->asignaturas,
                    'gestion_periodo' => $ed->gestion_periodo,
                ],
                'archivos' => $archivos,
            ];
        }

        // 4. Experiencia Profesional
        foreach ($postulante->experienciasProfesionales ?? [] as $ep) {
            $archivos = [];
            $cert = $this->getFileWithFallback($ep, 'certificado_archivo_path', 'certificado');
            if ($cert) {
                $archivos[] = [
                    'id' => 'vep_c_' . $ep->id,
                    'merito_id' => $ep->source_merito_id ?? $ep->id,
                    'config_archivo_id' => 'certificado',
                    'archivo_path' => $cert,
                ];
            }

            $virtual[] = [
                'id' => $ep->source_merito_id ?? ('vep_' . $ep->id),
                'tipo_documento_id' => 4,
                'respuestas' => [
                    'cargo' => $ep->cargo_raw,
                    'empresa' => $ep->empresa,
                    'fecha_inicio' => $ep->fecha_inicio,
                    'fecha_fin' => $ep->fecha_fin,
                ],
                'archivos' => $archivos,
            ];
        }

        // 5. Capacitaciones
        foreach ($postulante->capacitaciones ?? [] as $c) {
            $archivos = [];
            $cert = $this->getFileWithFallback($c, 'certificado_archivo_path', 'certificado');
            if ($cert) {
                $archivos[] = [
                    'id' => 'vc_c_' . $c->id,
                    'merito_id' => $c->source_merito_id ?? $c->id,
                    'config_archivo_id' => 'certificado',
                    'archivo_path' => $cert,
                ];
            }

            $virtual[] = [
                'id' => $c->source_merito_id ?? ('vc_' . $c->id),
                'tipo_documento_id' => 5,
                'respuestas' => [
                    'nombre' => $c->nombre_curso,
                    'fecha' => $c->fecha,
                    'institucion' => $c->institucion_organizadora,
                    'horas' => $c->carga_horaria,
                ],
                'archivos' => $archivos,
            ];
        }

        // 6. Producciones Intelectuales
        foreach ($postulante->produccionesIntelectuales ?? [] as $pi) {
            $archivos = [];
            $ev = $this->getFileWithFallback($pi, 'evidencia_archivo_path', 'evidencia');
            if ($ev) {
                $archivos[] = [
                    'id' => 'vpi_e_' . $pi->id,
                    'merito_id' => $pi->source_merito_id ?? $pi->id,
                    'config_archivo_id' => 'evidencia',
                    'archivo_path' => $ev,
                ];
            }

            $virtual[] = [
                'id' => $pi->source_merito_id ?? ('vpi_' . $pi->id),
                'tipo_documento_id' => 6,
                'respuestas' => [
                    'tipo' => $pi->tipo_produccion_raw,
                    'titulo' => $pi->titulo,
                    'fecha' => $pi->fecha_publicacion,
                    'editorial' => $pi->editorial_revista,
                    'lugar' => $pi->lugar,
                ],
                'archivos' => $archivos,
            ];
        }

        // 7. Reconocimientos
        foreach ($postulante->reconocimientos ?? [] as $r) {
            $archivos = [];
            $rec = $this->getFileWithFallback($r, 'reconocimiento_archivo_path', 'reconocimiento');
            if ($rec) {
                $archivos[] = [
                    'id' => 'vr_r_' . $r->id,
                    'merito_id' => $r->source_merito_id ?? $r->id,
                    'config_archivo_id' => 'reconocimiento',
                    'archivo_path' => $rec,
                ];
            }

            $virtual[] = [
                'id' => $r->source_merito_id ?? ('vr_' . $r->id),
                'tipo_documento_id' => 7,
                'respuestas' => [
                    'titulo' => $r->titulo_reconocimiento,
                    'fecha' => $r->fecha,
                    'institucion' => $r->institucion_otorgante,
                    'lugar' => $r->lugar,
                ],
                'archivos' => $archivos,
            ];
        }

        return $virtual;
    }
}
