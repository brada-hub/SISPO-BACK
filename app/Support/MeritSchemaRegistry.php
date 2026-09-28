<?php

namespace App\Support;

class MeritSchemaRegistry
{
    /**
     * Devuelve el listado oficial y centralizado de los 7 Schemas de Méritos.
     * Define todos los campos requeridos, tipos, opciones y documentos de respaldo.
     */
    public static function all(): array
    {
        return [
            [
                'id' => 1,
                'code' => 'FORMACION_ACADEMICA',
                'label' => 'Formación Académica',
                'nombre' => 'FORMACIÓN ACADÉMICA',
                'descripcion' => 'TÍTULOS DE PREGRADO',
                'table' => 'formaciones_academicas',
                'supports_multiple' => true,
                'permite_multiples' => true,
                'score_category' => 'academic_formation',
                'categoria' => 'FORMACIÓN',
                'orden' => 1,
                'required_documents' => [
                    ['id' => 'diploma', 'label' => 'DIPLOMA ACADÉMICO', 'required' => true, 'after_campo' => 'fecha_diploma'],
                    ['id' => 'titulo', 'label' => 'TÍTULO EN PROVISIÓN NACIONAL', 'required' => true, 'after_campo' => 'fecha_titulo'],
                ],
                'config_archivos' => [
                    ['id' => 'diploma', 'label' => 'DIPLOMA ACADÉMICO', 'required' => true, 'after_campo' => 'fecha_diploma'],
                    ['id' => 'titulo', 'label' => 'TÍTULO EN PROVISIÓN NACIONAL', 'required' => true, 'after_campo' => 'fecha_titulo'],
                ],
                'ui_metadata' => [
                    'icon' => 'school',
                    'color' => 'blue',
                    'order' => 1,
                ],
                'fields' => [
                    [
                        'key' => 'nivel',
                        'label' => 'NIVEL ACADÉMICO',
                        'type' => 'select',
                        'options' => ['LICENCIATURA', 'TÉCNICO MEDIO', 'TÉCNICO SUPERIOR', 'SECRETARIADO', 'AUXILIAR', 'POSTGRADO', 'OTROS'],
                        'required' => true,
                    ],
                    [
                        'key' => 'universidad',
                        'label' => 'UNIVERSIDAD / INSTITUCIÓN',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'profesion',
                        'label' => 'CARRERA / PROFESIÓN',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_diploma',
                        'label' => 'FECHA DIPLOMA',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_titulo',
                        'label' => 'FECHA TÍTULO',
                        'type' => 'date',
                        'required' => true,
                    ],
                ],
                'campos' => [
                    [
                        'key' => 'nivel',
                        'label' => 'NIVEL ACADÉMICO',
                        'type' => 'select',
                        'options' => ['LICENCIATURA', 'TÉCNICO MEDIO', 'TÉCNICO SUPERIOR', 'SECRETARIADO', 'AUXILIAR', 'POSTGRADO', 'OTROS'],
                        'required' => true,
                    ],
                    [
                        'key' => 'universidad',
                        'label' => 'UNIVERSIDAD / INSTITUCIÓN',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'profesion',
                        'label' => 'CARRERA / PROFESIÓN',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_diploma',
                        'label' => 'FECHA DIPLOMA',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_titulo',
                        'label' => 'FECHA TÍTULO',
                        'type' => 'date',
                        'required' => true,
                    ],
                ],
            ],
            [
                'id' => 2,
                'code' => 'POSTGRADO',
                'label' => 'Formación de Posgrado',
                'nombre' => 'FORMACIÓN EN POSGRADO',
                'descripcion' => 'DIPLOMADOS, MAESTRÍAS, DOCTORADOS',
                'table' => 'formaciones_postgrado',
                'supports_multiple' => true,
                'permite_multiples' => true,
                'score_category' => 'postgraduate',
                'categoria' => 'FORMACIÓN',
                'orden' => 2,
                'required_documents' => [
                    ['id' => 'certificado', 'label' => 'CERTIFICADO DE POSGRADO', 'required' => true],
                ],
                'config_archivos' => [
                    ['id' => 'certificado', 'label' => 'CERTIFICADO DE POSGRADO', 'required' => true],
                ],
                'ui_metadata' => [
                    'icon' => 'workspace_premium',
                    'color' => 'indigo',
                    'order' => 2,
                ],
                'fields' => [
                    [
                        'key' => 'tipo_posgrado',
                        'label' => 'TIPO DE POSGRADO',
                        'type' => 'select',
                        'options' => ['DIPLOMADO', 'ESPECIALIDAD', 'MAESTRÍA', 'DOCTORADO'],
                        'required' => true,
                    ],
                    [
                        'key' => 'nombre_programa',
                        'label' => 'NOMBRE DEL PROGRAMA',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_certificacion',
                        'label' => 'FECHA DE CERTIFICACIÓN',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'institucion',
                        'label' => 'INSTITUCIÓN',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
                'campos' => [
                    [
                        'key' => 'tipo_posgrado',
                        'label' => 'TIPO DE POSGRADO',
                        'type' => 'select',
                        'options' => ['DIPLOMADO', 'ESPECIALIDAD', 'MAESTRÍA', 'DOCTORADO'],
                        'required' => true,
                    ],
                    [
                        'key' => 'nombre_programa',
                        'label' => 'NOMBRE DEL PROGRAMA',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_certificacion',
                        'label' => 'FECHA DE CERTIFICACIÓN',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'institucion',
                        'label' => 'INSTITUCIÓN',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
            [
                'id' => 3,
                'code' => 'EXPERIENCIA_DOCENCIA',
                'label' => 'Experiencia en Docencia',
                'nombre' => 'EXPERIENCIA DOCENCIA',
                'descripcion' => 'EXPERIENCIA COMO DOCENTE UNIVERSITARIO',
                'table' => 'experiencias_docencia',
                'supports_multiple' => true,
                'permite_multiples' => true,
                'score_category' => 'teaching_experience',
                'categoria' => 'EXPERIENCIA',
                'orden' => 3,
                'required_documents' => [
                    ['id' => 'respaldo', 'label' => 'RESPALDO DOCUMENTAL (CONTRATO/CERTIFICADO)', 'required' => true],
                ],
                'config_archivos' => [
                    ['id' => 'respaldo', 'label' => 'RESPALDO DOCUMENTAL (CONTRATO/CERTIFICADO)', 'required' => true],
                ],
                'ui_metadata' => [
                    'icon' => 'cast_for_education',
                    'color' => 'orange',
                    'order' => 3,
                ],
                'fields' => [
                    [
                        'key' => 'universidad',
                        'label' => 'UNIVERSIDAD',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'carrera',
                        'label' => 'CARRERA',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'asignaturas',
                        'label' => 'ASIGNATURAS',
                        'type' => 'textarea',
                        'required' => true,
                    ],
                    [
                        'key' => 'gestion_periodo',
                        'label' => 'GESTIÓN/PERIODO',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
                'campos' => [
                    [
                        'key' => 'universidad',
                        'label' => 'UNIVERSIDAD',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'carrera',
                        'label' => 'CARRERA',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'asignaturas',
                        'label' => 'ASIGNATURAS',
                        'type' => 'textarea',
                        'required' => true,
                    ],
                    [
                        'key' => 'gestion_periodo',
                        'label' => 'GESTIÓN/PERIODO',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
            [
                'id' => 4,
                'code' => 'EXPERIENCIA_PROFESIONAL',
                'label' => 'Experiencia Profesional',
                'nombre' => 'EXPERIENCIA PROFESIONAL',
                'descripcion' => 'EXPERIENCIA LABORAL GENERAL',
                'table' => 'experiencias_profesionales',
                'supports_multiple' => true,
                'permite_multiples' => true,
                'score_category' => 'professional_experience',
                'categoria' => 'EXPERIENCIA',
                'orden' => 4,
                'required_documents' => [
                    ['id' => 'certificado', 'label' => 'CERTIFICADO DE TRABAJO', 'required' => true],
                ],
                'config_archivos' => [
                    ['id' => 'certificado', 'label' => 'CERTIFICADO DE TRABAJO', 'required' => true],
                ],
                'ui_metadata' => [
                    'icon' => 'work',
                    'color' => 'teal',
                    'order' => 4,
                ],
                'fields' => [
                    [
                        'key' => 'cargo',
                        'label' => 'CARGO DESEMPEÑADO',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'empresa',
                        'label' => 'EMPRESA/INSTITUCIÓN',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_inicio',
                        'label' => 'FECHA INICIO',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_fin',
                        'label' => 'FECHA FIN',
                        'type' => 'date',
                        'required' => true,
                    ],
                ],
                'campos' => [
                    [
                        'key' => 'cargo',
                        'label' => 'CARGO DESEMPEÑADO',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'empresa',
                        'label' => 'EMPRESA/INSTITUCIÓN',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_inicio',
                        'label' => 'FECHA INICIO',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha_fin',
                        'label' => 'FECHA FIN',
                        'type' => 'date',
                        'required' => true,
                    ],
                ],
            ],
            [
                'id' => 5,
                'code' => 'CAPACITACION',
                'label' => 'Capacitación y Cursos',
                'nombre' => 'CAPACITACION',
                'descripcion' => 'CURSOS, TALLERES, SEMINARIOS',
                'table' => 'capacitaciones',
                'supports_multiple' => true,
                'permite_multiples' => true,
                'score_category' => 'training',
                'categoria' => 'OTROS',
                'orden' => 5,
                'required_documents' => [
                    ['id' => 'certificado', 'label' => 'CERTIFICADO DE ASISTENCIA/APROBACIÓN', 'required' => true],
                ],
                'config_archivos' => [
                    ['id' => 'certificado', 'label' => 'CERTIFICADO DE ASISTENCIA/APROBACIÓN', 'required' => true],
                ],
                'ui_metadata' => [
                    'icon' => 'local_library',
                    'color' => 'cyan',
                    'order' => 5,
                ],
                'fields' => [
                    [
                        'key' => 'nombre',
                        'label' => 'NOMBRE DEL CURSO/EVENTO',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha',
                        'label' => 'FECHA',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'institucion',
                        'label' => 'INSTITUCIÓN ORGANIZADORA',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'horas',
                        'label' => 'CARGA HORARIA',
                        'type' => 'number',
                        'required' => true,
                    ],
                ],
                'campos' => [
                    [
                        'key' => 'nombre',
                        'label' => 'NOMBRE DEL CURSO/EVENTO',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha',
                        'label' => 'FECHA',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'institucion',
                        'label' => 'INSTITUCIÓN ORGANIZADORA',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'horas',
                        'label' => 'CARGA HORARIA',
                        'type' => 'number',
                        'required' => true,
                    ],
                ],
            ],
            [
                'id' => 6,
                'code' => 'PRODUCCION_INTELECTUAL',
                'label' => 'Producción Intelectual y Publicaciones',
                'nombre' => 'PRODUCCIÓN INTELECTUAL',
                'descripcion' => 'LIBROS, ARTÍCULOS, INVESTIGACIONES',
                'table' => 'producciones_intelectuales',
                'supports_multiple' => true,
                'permite_multiples' => true,
                'score_category' => 'intellectual_production',
                'categoria' => 'INTELECTUAL',
                'orden' => 6,
                'required_documents' => [
                    ['id' => 'evidencia', 'label' => 'EVIDENCIA (TAPA, ÍNDICE, ARTÍCULO)', 'required' => true],
                ],
                'config_archivos' => [
                    ['id' => 'evidencia', 'label' => 'EVIDENCIA (TAPA, ÍNDICE, ARTÍCULO)', 'required' => true],
                ],
                'ui_metadata' => [
                    'icon' => 'auto_stories',
                    'color' => 'purple',
                    'order' => 6,
                ],
                'fields' => [
                    [
                        'key' => 'tipo',
                        'label' => 'TIPO DE PRODUCCIÓN',
                        'type' => 'select',
                        'options' => ['LIBRO', 'ARTÍCULO CIENTÍFICO', 'ENSAYO', 'OTRO'],
                        'required' => true,
                    ],
                    [
                        'key' => 'titulo',
                        'label' => 'TÍTULO',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha',
                        'label' => 'FECHA DE PUBLICACIÓN',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'editorial',
                        'label' => 'EDITORIAL/REVISTA',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'lugar',
                        'label' => 'LUGAR',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
                'campos' => [
                    [
                        'key' => 'tipo',
                        'label' => 'TIPO DE PRODUCCIÓN',
                        'type' => 'select',
                        'options' => ['LIBRO', 'ARTÍCULO CIENTÍFICO', 'ENSAYO', 'OTRO'],
                        'required' => true,
                    ],
                    [
                        'key' => 'titulo',
                        'label' => 'TÍTULO',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha',
                        'label' => 'FECHA DE PUBLICACIÓN',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'editorial',
                        'label' => 'EDITORIAL/REVISTA',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'lugar',
                        'label' => 'LUGAR',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
            [
                'id' => 7,
                'code' => 'RECONOCIMIENTO',
                'label' => 'Reconocimientos y Distinciones',
                'nombre' => 'RECONOCIMIENTO',
                'descripcion' => 'PREMIOS, DISTINCIONES',
                'table' => 'reconocimientos',
                'supports_multiple' => true,
                'permite_multiples' => true,
                'score_category' => 'recognitions',
                'categoria' => 'OTROS',
                'orden' => 7,
                'required_documents' => [
                    ['id' => 'reconocimiento', 'label' => 'DOCUMENTO DE RECONOCIMIENTO', 'required' => true],
                ],
                'config_archivos' => [
                    ['id' => 'reconocimiento', 'label' => 'DOCUMENTO DE RECONOCIMIENTO', 'required' => true],
                ],
                'ui_metadata' => [
                    'icon' => 'emoji_events',
                    'color' => 'yellow-8',
                    'order' => 7,
                ],
                'fields' => [
                    [
                        'key' => 'titulo',
                        'label' => 'TÍTULO DEL RECONOCIMIENTO',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha',
                        'label' => 'FECHA',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'institucion',
                        'label' => 'INSTITUCIÓN OTORGANTE',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'lugar',
                        'label' => 'LUGAR',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
                'campos' => [
                    [
                        'key' => 'titulo',
                        'label' => 'TÍTULO DEL RECONOCIMIENTO',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'fecha',
                        'label' => 'FECHA',
                        'type' => 'date',
                        'required' => true,
                    ],
                    [
                        'key' => 'institucion',
                        'label' => 'INSTITUCIÓN OTORGANTE',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'lugar',
                        'label' => 'LUGAR',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
        ];
    }
}
