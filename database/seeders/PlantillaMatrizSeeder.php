<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlantillaMatrizSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('plantilla_matrizs')->truncate();

        DB::table('plantilla_matrizs')->insert([
            [
                'nombre' => 'Matriz Básica de Activos Fijos',
                'descripcion' => 'Plantilla para evaluación de áreas de inventarios, bienes y afines.',
                'matriz' => json_encode([
                    [
                        'seccion' => 'FORMACIÓN PROFESIONAL',
                        'criterios' => [
                            ['nombre' => 'Título Académico', 'puntaje' => 15, 'descripcion' => 'Licenciatura en Contaduría o Adm. de Empresas.'],
                            ['nombre' => 'Postgrado', 'puntaje' => 5, 'descripcion' => 'Diplomado o Especialidad.']
                        ]
                    ],
                    [
                        'seccion' => 'CAPACITACIÓN TÉCNICA',
                        'criterios' => [
                            ['nombre' => 'Normativa y Cont.', 'puntaje' => 10, 'descripcion' => 'Normas Contables o SABS.'],
                            ['nombre' => 'Digital/ERP', 'puntaje' => 10, 'descripcion' => 'Manejo de sistemas contables y Excel.']
                        ]
                    ],
                    [
                        'seccion' => 'EXPERIENCIA LABORAL',
                        'criterios' => [
                            ['nombre' => 'Exp. General', 'puntaje' => 15, 'descripcion' => 'Mínimo 3 años.'],
                            ['nombre' => 'Gestión Activos', 'puntaje' => 25, 'descripcion' => 'Control de bienes.'],
                            ['nombre' => 'Bajas/Inv.', 'puntaje' => 10, 'descripcion' => 'Baja, transferencia y física.']
                        ]
                    ],
                    [
                        'seccion' => 'OTROS',
                        'criterios' => [
                            ['nombre' => 'Documental', 'puntaje' => 10, 'descripcion' => 'Presentación correcta.']
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'MATRIZ PRINCIPAL (Docencia / Salud)',
                'descripcion' => 'La plantilla oficial mostrada en la tabla histórica (Médicos, Docentes, Académicos).',
                'matriz' => json_encode([
                    [
                        'seccion' => 'FORMACIÓN PROFESIONAL',
                        'criterios' => [
                            ['nombre' => 'DIPLOMADO', 'puntaje' => 3, 'descripcion' => 'Diplomado'],
                            ['nombre' => 'ESPECIALIZACIÓN', 'puntaje' => 4, 'descripcion' => 'Especialización'],
                            ['nombre' => 'MAESTRÍA', 'puntaje' => 6, 'descripcion' => 'Maestría'],
                            ['nombre' => 'DOCTORADO', 'puntaje' => 7, 'descripcion' => 'Doctorado']
                        ]
                    ],
                    [
                        'seccion' => 'PERFECCIONAMIENTO PROFESIONAL',
                        'criterios' => [
                            ['nombre' => 'CURSOS AREA > 120 HRS', 'puntaje' => 3, 'descripcion' => '3 P/C MAX'],
                            ['nombre' => 'CURSILLOS/SEMIN. > 20 HRS', 'puntaje' => 1, 'descripcion' => '1 P MAX'],
                            ['nombre' => 'DISERTANTE CONGRESOS', 'puntaje' => 1, 'descripcion' => '1 P MAX'],
                            ['nombre' => 'FORMACIÓN PEDAGÓGICA', 'puntaje' => 3, 'descripcion' => '1 P MAX 3']
                        ]
                    ],
                    [
                        'seccion' => 'EXPERIENCIA ACADÉMICA',
                        'criterios' => [
                            ['nombre' => 'EJERCICIO PROFESIONAL', 'puntaje' => 15, 'descripcion' => '1 P/AÑO MAX 15'],
                            ['nombre' => 'DOCENCIA EJERCIDA', 'puntaje' => 10, 'descripcion' => '1 P/SEM MAX 10'],
                            ['nombre' => 'TUTORÍA DE TESIS', 'puntaje' => 5, 'descripcion' => '1 P MAX 5'],
                            ['nombre' => 'DOCENTE POSTGRADO', 'puntaje' => 5, 'descripcion' => '1 P MAX 5'],
                            ['nombre' => 'CARGOS SIMILARES', 'puntaje' => 15, 'descripcion' => 'MAX 15']
                        ]
                    ],
                    [
                        'seccion' => 'OTROS MERITOS',
                        'criterios' => [
                            ['nombre' => 'REVISTAS INDEXADAS', 'puntaje' => 3, 'descripcion' => '1 P MAX 3'],
                            ['nombre' => 'LIBROS/TEXTOS', 'puntaje' => 3, 'descripcion' => '1 P MAX 3'],
                            ['nombre' => 'DISTINCIONES HONORÍF.', 'puntaje' => 4, 'descripcion' => '1 P MAX 4']
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
