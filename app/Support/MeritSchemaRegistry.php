<?php

namespace App\Support;

class MeritSchemaRegistry
{
    /**
     * Devuelve el listado oficial y centralizado de los 7 Schemas de Méritos.
     * Esto reemplaza por completo a la tabla "tipos_documento" legacy.
     */
    public static function all(): array
    {
        return [
            [
                'id' => 1,
                'code' => 'FORMACION_ACADEMICA',
                'label' => 'Formación Académica',
                'table' => 'formaciones_academicas',
                'supports_multiple' => true,
                'score_category' => 'academic_formation',
                'required_documents' => [
                    ['id' => 'diploma', 'label' => 'Diploma Académico'],
                    ['id' => 'titulo', 'label' => 'Título en Provisión Nacional']
                ],
                'ui_metadata' => [
                    'icon' => 'school',
                    'color' => 'blue',
                    'order' => 1
                ],
                'fields' => [
                    ['key' => 'carrera', 'label' => 'Carrera / Programa', 'type' => 'text'],
                    ['key' => 'nivel_maximo', 'label' => 'Nivel', 'type' => 'select', 'options' => ['Técnico', 'Licenciatura', 'Especialidad', 'Maestría', 'Doctorado']],
                ]
            ],
            [
                'id' => 2,
                'code' => 'POSTGRADO',
                'label' => 'Formación de Posgrado',
                'table' => 'formaciones_postgrado',
                'supports_multiple' => true,
                'score_category' => 'postgraduate',
                'required_documents' => [
                    ['id' => 'certificado', 'label' => 'Certificado o Título de Posgrado']
                ],
                'ui_metadata' => [
                    'icon' => 'workspace_premium',
                    'color' => 'indigo',
                    'order' => 2
                ],
                'fields' => [
                    ['key' => 'nombre_programa', 'label' => 'Nombre del Programa', 'type' => 'text'],
                    ['key' => 'tipo_posgrado', 'label' => 'Tipo', 'type' => 'select', 'options' => ['Diplomado', 'Especialidad', 'Maestría', 'Doctorado']],
                ]
            ],
            [
                'id' => 3,
                'code' => 'EXPERIENCIA_PROFESIONAL',
                'label' => 'Experiencia Profesional',
                'table' => 'experiencias_profesionales',
                'supports_multiple' => true,
                'score_category' => 'professional_experience',
                'required_documents' => [
                    ['id' => 'certificado', 'label' => 'Certificado de Trabajo']
                ],
                'ui_metadata' => [
                    'icon' => 'work',
                    'color' => 'teal',
                    'order' => 3
                ],
                'fields' => [
                    ['key' => 'cargo', 'label' => 'Cargo Desempeñado', 'type' => 'text'],
                    ['key' => 'institucion', 'label' => 'Empresa / Institución', 'type' => 'text'],
                    ['key' => 'fecha_inicio', 'label' => 'Fecha de Inicio', 'type' => 'date'],
                    ['key' => 'fecha_fin', 'label' => 'Fecha de Fin', 'type' => 'date'],
                ]
            ],
            [
                'id' => 4,
                'code' => 'EXPERIENCIA_DOCENCIA',
                'label' => 'Experiencia en Docencia',
                'table' => 'experiencias_docencia',
                'supports_multiple' => true,
                'score_category' => 'teaching_experience',
                'required_documents' => [
                    ['id' => 'respaldo', 'label' => 'Respaldo Docente']
                ],
                'ui_metadata' => [
                    'icon' => 'cast_for_education',
                    'color' => 'orange',
                    'order' => 4
                ],
                'fields' => [
                    ['key' => 'materia_asignatura', 'label' => 'Asignatura', 'type' => 'text'],
                    ['key' => 'universidad', 'label' => 'Universidad', 'type' => 'text'],
                ]
            ],
            [
                'id' => 5,
                'code' => 'CAPACITACION',
                'label' => 'Capacitación y Cursos',
                'table' => 'capacitaciones',
                'supports_multiple' => true,
                'score_category' => 'training',
                'required_documents' => [
                    ['id' => 'certificado', 'label' => 'Certificado del Curso']
                ],
                'ui_metadata' => [
                    'icon' => 'local_library',
                    'color' => 'cyan',
                    'order' => 5
                ],
                'fields' => [
                    ['key' => 'nombre_curso', 'label' => 'Nombre del Curso', 'type' => 'text'],
                    ['key' => 'horas_academicas', 'label' => 'Horas', 'type' => 'number'],
                ]
            ],
            [
                'id' => 6,
                'code' => 'PRODUCCION_INTELECTUAL',
                'label' => 'Producción Intelectual',
                'table' => 'producciones_intelectuales',
                'supports_multiple' => true,
                'score_category' => 'intellectual_production',
                'required_documents' => [
                    ['id' => 'evidencia', 'label' => 'Evidencia (Tapa, Abstract)']
                ],
                'ui_metadata' => [
                    'icon' => 'auto_stories',
                    'color' => 'purple',
                    'order' => 6
                ],
                'fields' => [
                    ['key' => 'titulo_produccion', 'label' => 'Título de la Publicación', 'type' => 'text'],
                    ['key' => 'tipo_produccion', 'label' => 'Tipo', 'type' => 'select', 'options' => ['Libro', 'Artículo', 'Investigación']],
                ]
            ],
            [
                'id' => 7,
                'code' => 'RECONOCIMIENTO',
                'label' => 'Reconocimientos y Distinciones',
                'table' => 'reconocimientos',
                'supports_multiple' => true,
                'score_category' => 'recognitions',
                'required_documents' => [
                    ['id' => 'reconocimiento', 'label' => 'Documento de Reconocimiento']
                ],
                'ui_metadata' => [
                    'icon' => 'emoji_events',
                    'color' => 'yellow-8',
                    'order' => 7
                ],
                'fields' => [
                    ['key' => 'titulo_reconocimiento', 'label' => 'Título', 'type' => 'text'],
                    ['key' => 'institucion_otorgante', 'label' => 'Institución', 'type' => 'text'],
                ]
            ]
        ];
    }
}
