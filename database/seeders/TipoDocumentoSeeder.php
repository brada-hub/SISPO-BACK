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
                'nombre' => 'Formación Académica',
                'descripcion' => 'Títulos de pregrado',
                'categoria' => 'Formación',
                'campos' => [
                    ['key' => 'nivel', 'label' => 'Nivel Académico', 'type' => 'select', 'options' => ['Licenciatura', 'Técnico Medio', 'Técnico Superior', 'Secretariado', 'Auxiliar', 'Postgrado', 'Otros']],
                    ['key' => 'universidad', 'label' => 'Universidad / Institución', 'type' => 'text'],
                    ['key' => 'profesion', 'label' => 'Carrera / Profesión', 'type' => 'text'],
                    ['key' => 'fecha_diploma', 'label' => 'Fecha Diploma', 'type' => 'date'],
                    ['key' => 'fecha_titulo', 'label' => 'Fecha Título', 'type' => 'date']
                ],
                'config_archivos' => [
                    ['id' => 'diploma', 'label' => 'Diploma Académico', 'required' => true, 'after_campo' => 'fecha_diploma'],
                    ['id' => 'titulo', 'label' => 'Título en Provisión Nacional', 'required' => true, 'after_campo' => 'fecha_titulo']
                ],
                'permite_multiples' => true,
                'orden' => 1
            ],
            [
                'nombre' => 'Formación en Posgrado',
                'descripcion' => 'Diplomados, Maestrías, Doctorados',
                'categoria' => 'Formación',
                'campos' => [
                    ['key' => 'tipo_posgrado', 'label' => 'Tipo de Posgrado', 'type' => 'select', 'options' => ['Diplomado', 'Especialidad', 'Maestría', 'Doctorado']],
                    ['key' => 'nombre_programa', 'label' => 'Nombre del Programa', 'type' => 'text'],
                    ['key' => 'fecha_certificacion', 'label' => 'Fecha de Certificación', 'type' => 'date'],
                    ['key' => 'institucion', 'label' => 'Institución', 'type' => 'text']
                ],
                'config_archivos' => [
                    ['id' => 'certificado', 'label' => 'Certificado de Posgrado', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 2
            ],
            [
                'nombre' => 'Experiencia Docencia',
                'descripcion' => 'Experiencia como docente universitario',
                'categoria' => 'Experiencia',
                'campos' => [
                    ['key' => 'universidad', 'label' => 'Universidad', 'type' => 'text'],
                    ['key' => 'carrera', 'label' => 'Carrera', 'type' => 'text'],
                    ['key' => 'asignaturas', 'label' => 'Asignaturas', 'type' => 'textarea'],
                    ['key' => 'gestion_periodo', 'label' => 'Gestión/Periodo', 'type' => 'text']
                ],
                'config_archivos' => [
                    ['id' => 'respaldo', 'label' => 'Respaldo Documental (Contrato/Certificado)', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 3
            ],
            [
                'nombre' => 'Experiencia Profesional',
                'descripcion' => 'Experiencia laboral general',
                'categoria' => 'Experiencia',
                'campos' => [
                    ['key' => 'cargo', 'label' => 'Cargo Desempeñado', 'type' => 'text'],
                    ['key' => 'empresa', 'label' => 'Empresa/Institución', 'type' => 'text'],
                    ['key' => 'fecha_inicio', 'label' => 'Fecha Inicio', 'type' => 'date'],
                    ['key' => 'fecha_fin', 'label' => 'Fecha Fin', 'type' => 'date']
                ],
                'config_archivos' => [
                    ['id' => 'certificado', 'label' => 'Certificado de Trabajo', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 4
            ],
            [
                'nombre' => 'Capacitacion',
                'descripcion' => 'Cursos, talleres, seminarios',
                'categoria' => 'Otros',
                'campos' => [
                    ['key' => 'nombre', 'label' => 'Nombre del Curso/Evento', 'type' => 'text'],
                    ['key' => 'fecha', 'label' => 'Fecha', 'type' => 'date'],
                    ['key' => 'institucion', 'label' => 'Institución Organizadora', 'type' => 'text'],
                    ['key' => 'horas', 'label' => 'Carga Horaria', 'type' => 'number']
                ],
                'config_archivos' => [
                    ['id' => 'certificado', 'label' => 'Certificado de Asistencia/Aprobación', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 5
            ],
            [
                'nombre' => 'Producción Intelectual',
                'descripcion' => 'Libros, artículos, investigaciones',
                'categoria' => 'Intelectual',
                'campos' => [
                    ['key' => 'tipo', 'label' => 'Tipo de Producción', 'type' => 'select', 'options' => ['Libro', 'Artículo Científico', 'Ensayo', 'Otro']],
                    ['key' => 'titulo', 'label' => 'Título', 'type' => 'text'],
                    ['key' => 'fecha', 'label' => 'Fecha de Publicación', 'type' => 'date'],
                    ['key' => 'editorial', 'label' => 'Editorial/Revista', 'type' => 'text'],
                    ['key' => 'lugar', 'label' => 'Lugar', 'type' => 'text']
                ],
                'config_archivos' => [
                    ['id' => 'evidencia', 'label' => 'Evidencia (Tapa, índice, artículo)', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 6
            ],
            [
                'nombre' => 'Reconocimiento',
                'descripcion' => 'Premios, distinciones',
                'categoria' => 'Otros',
                'campos' => [
                    ['key' => 'titulo', 'label' => 'Título del Reconocimiento', 'type' => 'text'],
                    ['key' => 'fecha', 'label' => 'Fecha', 'type' => 'date'],
                    ['key' => 'institucion', 'label' => 'Institución Otorgante', 'type' => 'text'],
                    ['key' => 'lugar', 'label' => 'Lugar', 'type' => 'text']
                ],
                'config_archivos' => [
                    ['id' => 'reconocimiento', 'label' => 'Documento de Reconocimiento', 'required' => true]
                ],
                'permite_multiples' => true,
                'orden' => 7
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoDocumento::create($tipo);
        }
    }
}
