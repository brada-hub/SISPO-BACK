<?php

namespace Database\Seeders;

use App\Models\ScoreProfile;
use App\Models\ScoreProfileRule;
use Illuminate\Database\Seeder;

class ScoreProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $profiles = [
            [
                'code' => 'administrative_profile',
                'name' => 'Perfil Administrativo / Gestión',
                'description' => 'Perfil enfocado en cargos de gestión y administración, priorizando experiencia profesional y restando peso a docencia.',
                'rules' => [
                    ['academic_formation', 'Formación Académica', 20.00, 20.00, true],
                    ['postgraduate', 'Formación de Postgrado', 5.00, 5.00, true],
                    ['professional_experience', 'Experiencia Profesional', 45.00, 45.00, true],
                    ['teaching_experience', 'Experiencia en Docencia', 0.00, 0.00, false],
                    ['training', 'Capacitación y Cursos', 15.00, 15.00, true],
                    ['intellectual_production', 'Producción Intelectual y Publicaciones', 0.00, 0.00, false],
                    ['recognitions', 'Reconocimientos y Distinciones', 15.00, 15.00, true],
                ]
            ],
            [
                'code' => 'teaching_profile',
                'name' => 'Perfil Docente / Académico',
                'description' => 'Perfil para docentes de carrera, ponderando docencia universitaria activa, posgrados y producción de libros.',
                'rules' => [
                    ['academic_formation', 'Formación Académica', 20.00, 20.00, true],
                    ['postgraduate', 'Formación de Postgrado', 20.00, 20.00, true],
                    ['professional_experience', 'Experiencia Profesional', 10.00, 10.00, true],
                    ['teaching_experience', 'Experiencia en Docencia', 25.00, 25.00, true],
                    ['training', 'Capacitación y Cursos', 10.00, 10.00, true],
                    ['intellectual_production', 'Producción Intelectual y Publicaciones', 10.00, 10.00, true],
                    ['recognitions', 'Reconocimientos y Distinciones', 5.00, 5.00, true],
                ]
            ],
            [
                'code' => 'technical_profile',
                'name' => 'Perfil Técnico / Operativo',
                'description' => 'Perfil para cargos técnicos, laboratorios y soporte operativo, primando experiencia práctica y capacitación técnica.',
                'rules' => [
                    ['academic_formation', 'Formación Académica', 20.00, 20.00, true],
                    ['postgraduate', 'Formación de Postgrado', 5.00, 5.00, true],
                    ['professional_experience', 'Experiencia Profesional', 40.00, 40.00, true],
                    ['teaching_experience', 'Experiencia en Docencia', 0.00, 0.00, false],
                    ['training', 'Capacitación y Cursos', 25.00, 25.00, true],
                    ['intellectual_production', 'Producción Intelectual y Publicaciones', 0.00, 0.00, false],
                    ['recognitions', 'Reconocimientos y Distinciones', 10.00, 10.00, true],
                ]
            ],
            [
                'code' => 'generic_profile',
                'name' => 'Perfil Genérico Balanceado',
                'description' => 'Perfil de distribución estándar utilizable para convocatorias mixtas.',
                'rules' => [
                    ['academic_formation', 'Formación Académica', 25.00, 25.00, true],
                    ['postgraduate', 'Formación de Postgrado', 15.00, 15.00, true],
                    ['professional_experience', 'Experiencia Profesional', 30.00, 30.00, true],
                    ['teaching_experience', 'Experiencia en Docencia', 15.00, 15.00, true],
                    ['training', 'Capacitación y Cursos', 5.00, 5.00, true],
                    ['intellectual_production', 'Producción Intelectual y Publicaciones', 5.00, 5.00, true],
                    ['recognitions', 'Reconocimientos y Distinciones', 5.00, 5.00, true],
                ]
            ]
        ];

        foreach ($profiles as $pData) {
            $prof = ScoreProfile::updateOrCreate(
                ['code' => $pData['code']],
                [
                    'name' => $pData['name'],
                    'description' => $pData['description'],
                    'is_active' => true,
                ]
            );

            foreach ($pData['rules'] as $rData) {
                ScoreProfileRule::updateOrCreate(
                    [
                        'score_profile_id' => $prof->id,
                        'criterion_code' => $rData[0],
                    ],
                    [
                        'criterion_name' => $rData[1],
                        'weight' => $rData[2],
                        'max_points' => $rData[3],
                        'is_required' => false,
                        'is_enabled' => $rData[4],
                    ]
                );
            }
        }
    }
}
