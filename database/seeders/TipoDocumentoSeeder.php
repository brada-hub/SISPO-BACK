<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoDocumento;

class TipoDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'nombre' => 'FORMACIÓN ACADÉMICA',
                'descripcion' => 'TÍTULOS DE PREGRADO',
                'categoria' => 'FORMACIÓN',
                'campos' => [
                    ['key' => 'nivel', 'label' => 'NIVEL ACADÉMICO', 'type' => 'select', 'options' => ['LICENCIATURA', 'TÉCNICO MEDIO', 'TÉCNICO SUPERIOR', 'SECRETARIADO', 'AUXILIAR', 'POSTGRADO', 'OTROS'], 'required' => true],
                    ['key' => 'universidad', 'label' => 'UNIVERSIDAD / INSTITUCIÓN', 'type' => 'text', 'required' => true],
                    ['key' => 'profesion', 'label' => 'CARRERA / PROFESIÓN', 'type' => 'text', 'required' => true],
                    ['key' => 'fecha_diploma', 'label' => 'FECHA DIPLOMA', 'type' => 'date', 'required' => true],
                    ['key' => 'fecha_titulo', 'label' => 'FECHA TÍTULO', 'type' => 'date', 'required' => true]
                ],
                'config_archivos' => [
                    ['id' => 'diploma', 'label' => 'DIPLOMA ACADÉMICO', 'required' => true, 'after_campo' => 'fecha_diploma'],
                    ['id' => 'titulo', 'label' => 'TÍTULO EN PROVISIÓN NACIONAL', 'required' => true, 'after_campo' => 'fecha_titulo']
                ],
                'permite_multiples' => true,
                'orden' => 1
            ],
            [
                'nombre' => 'FORMACIÓN EN POSGRADO',
                'descripcion' => 'DIPLOMADOS, MAESTRÍAS, DOCTORADOS',
                'categoria' => 'FORMACIÓN',
                'campos' => [
                    ['key' => 'tipo_posgrado', 'label' => 'TIPO DE POSGRADO', 'type' => 'select', 'options' => ['DIPLOMADO', 'ESPECIALIDAD', 'MAESTRÍA', 'DOCTORADO'], 'required' => true],
                    ['key' => 'nombre_programa', 'label' => 'NOMBRE DEL PROGRAMA', 'type' => 'text', 'required' => true],
                    ['key' => 'fecha_certificacion', 'label' => 'FECHA DE CERTIFICACIÓN', 'type' => 'date', 'required' => true],
                    ['key' => 'institucion', 'label' => 'INSTITUCIÓN', 'type' => 'text', 'required' => true]
                ],
                'config_archivos' => [
                    ['id' => 'certificado', 'label' => 'CERTIFICADO DE POSGRADO', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 2
            ],
            [
                'nombre' => 'EXPERIENCIA DOCENCIA',
                'descripcion' => 'EXPERIENCIA COMO DOCENTE UNIVERSITARIO',
                'categoria' => 'EXPERIENCIA',
                'campos' => [
                    ['key' => 'universidad', 'label' => 'UNIVERSIDAD', 'type' => 'text', 'required' => true],
                    ['key' => 'carrera', 'label' => 'CARRERA', 'type' => 'text', 'required' => true],
                    ['key' => 'asignaturas', 'label' => 'ASIGNATURAS', 'type' => 'textarea', 'required' => true],
                    ['key' => 'gestion_periodo', 'label' => 'GESTIÓN/PERIODO', 'type' => 'text', 'required' => true]
                ],
                'config_archivos' => [
                    ['id' => 'respaldo', 'label' => 'RESPALDO DOCUMENTAL (CONTRATO/CERTIFICADO)', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 3
            ],
            [
                'nombre' => 'EXPERIENCIA PROFESIONAL',
                'descripcion' => 'EXPERIENCIA LABORAL GENERAL',
                'categoria' => 'EXPERIENCIA',
                'campos' => [
                    ['key' => 'cargo', 'label' => 'CARGO DESEMPEÑADO', 'type' => 'text', 'required' => true],
                    ['key' => 'empresa', 'label' => 'EMPRESA/INSTITUCIÓN', 'type' => 'text', 'required' => true],
                    ['key' => 'fecha_inicio', 'label' => 'FECHA INICIO', 'type' => 'date', 'required' => true],
                    ['key' => 'fecha_fin', 'label' => 'FECHA FIN', 'type' => 'date', 'required' => true]
                ],
                'config_archivos' => [
                    ['id' => 'certificado', 'label' => 'CERTIFICADO DE TRABAJO', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 4
            ],
            [
                'nombre' => 'CAPACITACION',
                'descripcion' => 'CURSOS, TALLERES, SEMINARIOS',
                'categoria' => 'OTROS',
                'campos' => [
                    ['key' => 'nombre', 'label' => 'NOMBRE DEL CURSO/EVENTO', 'type' => 'text', 'required' => true],
                    ['key' => 'fecha', 'label' => 'FECHA', 'type' => 'date', 'required' => true],
                    ['key' => 'institucion', 'label' => 'INSTITUCIÓN ORGANIZADORA', 'type' => 'text', 'required' => true],
                    ['key' => 'horas', 'label' => 'CARGA HORARIA', 'type' => 'number', 'required' => true]
                ],
                'config_archivos' => [
                    ['id' => 'certificado', 'label' => 'CERTIFICADO DE ASISTENCIA/APROBACIÓN', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 5
            ],
            [
                'nombre' => 'PRODUCCIÓN INTELECTUAL',
                'descripcion' => 'LIBROS, ARTÍCULOS, INVESTIGACIONES',
                'categoria' => 'INTELECTUAL',
                'campos' => [
                    ['key' => 'tipo', 'label' => 'TIPO DE PRODUCCIÓN', 'type' => 'select', 'options' => ['LIBRO', 'ARTÍCULO CIENTÍFICO', 'ENSAYO', 'OTRO'], 'required' => true],
                    ['key' => 'titulo', 'label' => 'TÍTULO', 'type' => 'text', 'required' => true],
                    ['key' => 'fecha', 'label' => 'FECHA DE PUBLICACIÓN', 'type' => 'date', 'required' => true],
                    ['key' => 'editorial', 'label' => 'EDITORIAL/REVISTA', 'type' => 'text', 'required' => true],
                    ['key' => 'lugar', 'label' => 'LUGAR', 'type' => 'text', 'required' => true]
                ],
                'config_archivos' => [
                    ['id' => 'evidencia', 'label' => 'EVIDENCIA (TAPA, ÍNDICE, ARTÍCULO)', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 6
            ],
            [
                'nombre' => 'RECONOCIMIENTO',
                'descripcion' => 'PREMIOS, DISTINCIONES',
                'categoria' => 'OTROS',
                'campos' => [
                    ['key' => 'titulo', 'label' => 'TÍTULO DEL RECONOCIMIENTO', 'type' => 'text', 'required' => true],
                    ['key' => 'fecha', 'label' => 'FECHA', 'type' => 'date', 'required' => true],
                    ['key' => 'institucion', 'label' => 'INSTITUCIÓN OTORGANTE', 'type' => 'text', 'required' => true],
                    ['key' => 'lugar', 'label' => 'LUGAR', 'type' => 'text', 'required' => true]
                ],
                'config_archivos' => [
                    ['id' => 'reconocimiento', 'label' => 'DOCUMENTO DE RECONOCIMIENTO', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 7
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoDocumento::updateOrCreate(['nombre' => $tipo['nombre']], $tipo);
        }
    }
}
