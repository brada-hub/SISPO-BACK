<?php

namespace Database\Seeders;

use App\Models\CatalogProfessionalArea;
use App\Models\CatalogCareer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AcademicAliasExpansionSeeder extends Seeder
{
    /**
     * Run the database seeds to expand academic catalogs and aliases.
     */
    public function run(): void
    {
        // 1. Create or Update Professional Areas for new categories
        $newAreas = [
            [
                'code' => 'salud_medicina',
                'name' => 'Medicina y Salud General',
                'description' => 'Servicios médicos generales, cirugía, especialidades médicas y salud pública.',
            ],
            [
                'code' => 'salud_odontologia',
                'name' => 'Odontología y Salud Bucal',
                'description' => 'Odontología general, cirugía bucal, ortodoncia y odontopediatría.',
            ],
            [
                'code' => 'salud_enfermeria',
                'name' => 'Enfermería y Asistencia de Salud',
                'description' => 'Cuidados generales de enfermería, instrumentación quirúrgica y asistencia médica.',
            ],
            [
                'code' => 'salud_bioquimica',
                'name' => 'Bioquímica y Farmacia',
                'description' => 'Análisis bioquímico, laboratorios clínicos, regencia de farmacia y química farmacéutica.',
            ],
            [
                'code' => 'ingenieria_operaciones',
                'name' => 'Ingeniería Industrial y Operaciones',
                'description' => 'Gestión de operaciones, ingeniería de procesos, logística y seguridad industrial.',
            ],
        ];

        foreach ($newAreas as $a) {
            CatalogProfessionalArea::updateOrCreate(['code' => $a['code']], $a);
        }

        // Fetch area IDs
        $areaMed = CatalogProfessionalArea::where('code', 'salud_medicina')->first();
        $areaOdo = CatalogProfessionalArea::where('code', 'salud_odontologia')->first();
        $areaEnf = CatalogProfessionalArea::where('code', 'salud_enfermeria')->first();
        $areaBio = CatalogProfessionalArea::where('code', 'salud_bioquimica')->first();
        $areaInd = CatalogProfessionalArea::where('code', 'ingenieria_operaciones')->first();

        // Existing areas for reference
        $areaEdu = CatalogProfessionalArea::where('code', 'educacion')->first();
        $areaFin = CatalogProfessionalArea::where('code', 'contabilidad_finanzas')->first();
        $areaAdmin = CatalogProfessionalArea::where('code', 'administracion_gestion')->first();

        // 2. Careers list expansion with high-coverage aliases
        $newCareers = [
            // Medicina
            [
                'canonical_name' => 'Medicina',
                'professional_area_id' => $areaMed->id,
                'aliases_json' => [
                    'medicina', 'medico cirujano', 'medico', 'médico', 'médico cirujano',
                    'medicina general', 'cirujano general', 'médico general', 'lic. en medicina',
                    'lic. medicina', 'cirugia'
                ],
            ],
            // Odontología
            [
                'canonical_name' => 'Odontología',
                'professional_area_id' => $areaOdo->id,
                'aliases_json' => [
                    'odontología', 'odontologia', 'cirujano dentista', 'cirujana dentista',
                    'odontologo', 'odontologa', 'cirujano odontologo', 'lic. en odontología',
                    'lic. en odontologia', 'protesis dental', 'odontopediatra'
                ],
            ],
            // Enfermería
            [
                'canonical_name' => 'Enfermería',
                'professional_area_id' => $areaEnf->id,
                'aliases_json' => [
                    'enfermeria', 'enfermería', 'lic. en enfermeria', 'lic. en enfermerìa',
                    'auxiliar de enfermeria', 'lic. enfermeria', 'enfermera'
                ],
            ],
            // Bioquímica y Farmacia
            [
                'canonical_name' => 'Bioquímica y Farmacia',
                'professional_area_id' => $areaBio->id,
                'aliases_json' => [
                    'bioquimica y farmacia', 'bioquímica y farmacia', 'bioquimico', 'bioquimica',
                    'bioquimico farmaceutico', 'bioquimica farmaceutica', 'lic. en bioquímica y farmacia',
                    'lic. en bioqumica y farmacia', 'bioquímico farmacéutico', 'lic. en bioquimica y farmacia',
                    'químico farmacéutico', 'química farmacéutica'
                ],
            ],
            // Ingeniería Industrial
            [
                'canonical_name' => 'Ingeniería Industrial',
                'professional_area_id' => $areaInd->id,
                'aliases_json' => [
                    'ingenieria industrial', 'ing. industrial', 'ingeniería industrial',
                    'ingeniero industrial', 'ing. ind.'
                ],
            ],
            // Ciencias de la Educación
            [
                'canonical_name' => 'Ciencias de la Educación',
                'professional_area_id' => $areaEdu->id,
                'aliases_json' => [
                    'ciencias de la educacion', 'ciencias de la educación', 'lic. en ciencias de la educacion',
                    'lic. en ciencias de la educación', 'pedagogia', 'pedagogía', 'lic. en pedagogia',
                    'educacion superior', 'educación superior', 'educacion', 'educación'
                ],
            ],
            // Economía
            [
                'canonical_name' => 'Economía',
                'professional_area_id' => $areaFin->id,
                'aliases_json' => [
                    'economia', 'economía', 'economista', 'economísta', 'lic. en economia',
                    'licenciado en economía', 'lic. en economía', 'lic. economia'
                ],
            ],
            // Psicología
            [
                'canonical_name' => 'Psicología',
                'professional_area_id' => $areaMed->id,
                'aliases_json' => [
                    'psicologia', 'psicología', 'psicologo', 'psicóloga', 'psicóloga', 
                    'lic. en psicología', 'lic. en psicologia', 'licenciado en psicologia'
                ],
            ],
            // Idiomas
            [
                'canonical_name' => 'Idiomas',
                'professional_area_id' => $areaEdu->id,
                'aliases_json' => [
                    'idiomas', 'tecnico superior en el idioma ingles', 'ingles', 'aymara', 
                    'linguista', 'lingüista', 'idioma ingles', 'idioma ingles y aymara', 
                    'idioma ingles y aymara cada uno con 160 horas'
                ],
            ],
        ];

        foreach ($newCareers as $c) {
            $slug = Str::slug($c['canonical_name']);
            
            // Normalize aliases to lowercase for robust mapping
            $normalizedAliases = array_map(function ($alias) {
                return mb_strtolower(trim($alias), 'UTF-8');
            }, $c['aliases_json']);

            CatalogCareer::updateOrCreate(
                ['normalized_slug' => $slug],
                [
                    'canonical_name' => $c['canonical_name'],
                    'professional_area_id' => $c['professional_area_id'],
                    'aliases_json' => $normalizedAliases,
                    'is_active' => true,
                ]
            );
        }

        // 3. Update Pre-existing careers from SemanticCatalogSeeder with new aliases
        // Systems & Technology Aliases
        $sysCareer = CatalogCareer::where('normalized_slug', 'ingenieria-de-sistemas')->first();
        if ($sysCareer) {
            $existing = $sysCareer->aliases_json ?? [];
            $newAliases = array_unique(array_merge($existing, [
                'ingeniero de sistemas', 'licenciado en informatica', 'licenciatura en informatica', 
                'ingenieria de sistema', 'ingeneiria de sistema', 'sistemas electronicos'
            ]));
            $sysCareer->update(['aliases_json' => array_values($newAliases)]);
        }

        // Law & Legal Aliases
        $lawCareer = CatalogCareer::where('normalized_slug', 'derecho')->first();
        if ($lawCareer) {
            $existing = $lawCareer->aliases_json ?? [];
            $newAliases = array_unique(array_merge($existing, [
                'abogada', 'derecho2', 'ciencias juridicas', 'ciencias juridicas y politicas'
            ]));
            $lawCareer->update(['aliases_json' => array_values($newAliases)]);
        }

        // Accounting & Finance Aliases
        $accountingCareer = CatalogCareer::where('normalized_slug', 'contaduria-publica')->first();
        if ($accountingCareer) {
            $existing = $accountingCareer->aliases_json ?? [];
            $newAliases = array_unique(array_merge($existing, [
                'contador general', 'auditorioa/auditora financiera', 'auditor financiero'
            ]));
            $accountingCareer->update(['aliases_json' => array_values($newAliases)]);
        }
    }
}
