<?php

namespace Database\Seeders;

use App\Models\CatalogProfessionalArea;
use App\Models\CatalogAcademicLevel;
use App\Models\CatalogPostgraduateType;
use App\Models\CatalogCareer;
use App\Models\CatalogJobPosition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SemanticCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ÁREAS PROFESIONALES
        $areas = [
            [
                'code' => 'sistemas_tecnologia',
                'name' => 'Sistemas y Tecnología de la Información',
                'description' => 'Desarrollo de software, administración de sistemas, redes y soporte tecnológico.',
            ],
            [
                'code' => 'administracion_gestion',
                'name' => 'Administración y Gestión Institucional',
                'description' => 'Servicios administrativos, secretariado, recursos humanos y gestión.',
            ],
            [
                'code' => 'comunicacion_social',
                'name' => 'Comunicación y Relaciones Públicas',
                'description' => 'Diseño multimedia, periodismo, relaciones corporativas y marketing.',
            ],
            [
                'code' => 'educacion',
                'name' => 'Educación y Pedagogía',
                'description' => 'Docencia universitaria, pedagogía e investigación académica.',
            ],
            [
                'code' => 'derecho',
                'name' => 'Derecho y Ciencias Jurídicas',
                'description' => 'Asesoría legal, derecho civil, penal, administrativo y laboral.',
            ],
            [
                'code' => 'marketing',
                'name' => 'Marketing y Ventas',
                'description' => 'Publicidad, marketing digital y gestión comercial.',
            ],
            [
                'code' => 'contabilidad_finanzas',
                'name' => 'Contabilidad y Finanzas',
                'description' => 'Auditoría contable, tesorería, costos e impuestos.',
            ],
        ];

        foreach ($areas as $a) {
            CatalogProfessionalArea::updateOrCreate(['code' => $a['code']], $a);
        }

        $areaSistemas = CatalogProfessionalArea::where('code', 'sistemas_tecnologia')->first();
        $areaAdmin = CatalogProfessionalArea::where('code', 'administracion_gestion')->first();
        $areaCom = CatalogProfessionalArea::where('code', 'comunicacion_social')->first();
        $areaEdu = CatalogProfessionalArea::where('code', 'educacion')->first();
        $areaDer = CatalogProfessionalArea::where('code', 'derecho')->first();
        $areaFin = CatalogProfessionalArea::where('code', 'contabilidad_finanzas')->first();

        // 2. NIVELES ACADÉMICOS
        $academicLevels = [
            [
                'code' => 'tecnico_medio',
                'name' => 'Técnico Medio',
                'hierarchy_order' => 1,
                'aliases_json' => ['Tec. Medio', 'Tecnico Medio', 'Auxiliar Tecnico'],
            ],
            [
                'code' => 'tecnico_superior',
                'name' => 'Técnico Superior',
                'hierarchy_order' => 2,
                'aliases_json' => ['Tec. Superior', 'Tecnico Superior', 'Tecnologo'],
            ],
            [
                'code' => 'licenciatura',
                'name' => 'Licenciatura',
                'hierarchy_order' => 3,
                'aliases_json' => ['Licenciado', 'Lic.', 'Ingeniero', 'Ing.', 'Abogado', 'Medico', 'Licenciatura'],
            ],
            [
                'code' => 'maestria',
                'name' => 'Maestría',
                'hierarchy_order' => 4,
                'aliases_json' => ['Magister', 'MSc.', 'M.Sc.', 'MSc', 'Maestria'],
            ],
            [
                'code' => 'doctorado',
                'name' => 'Doctorado',
                'hierarchy_order' => 5,
                'aliases_json' => ['Doctor', 'PhD', 'Ph.D.', 'Dr.', 'Doctorado'],
            ],
        ];

        foreach ($academicLevels as $al) {
            CatalogAcademicLevel::updateOrCreate(['code' => $al['code']], $al);
        }

        // 3. TIPOS DE POSGRADO
        $postgraduateTypes = [
            [
                'code' => 'diplomado',
                'name' => 'Diplomado',
                'aliases_json' => ['Diplomado', 'Dipl.', 'Dip.'],
            ],
            [
                'code' => 'especialidad',
                'name' => 'Especialidad',
                'aliases_json' => ['Especialidad', 'Especialidad Medica', 'Esp.'],
            ],
            [
                'code' => 'maestria',
                'name' => 'Maestría',
                'aliases_json' => ['Maestria', 'Magister', 'MSc', 'M.Sc.', 'Magíster'],
            ],
            [
                'code' => 'doctorado',
                'name' => 'Doctorado',
                'aliases_json' => ['Doctorado', 'PhD', 'Ph.D.'],
            ],
        ];

        foreach ($postgraduateTypes as $pt) {
            CatalogPostgraduateType::updateOrCreate(['code' => $pt['code']], $pt);
        }

        // 4. CARRERAS CANÓNICAS
        $careers = [
            // Sistemas y Tecnología
            [
                'canonical_name' => 'Ingeniería de Sistemas',
                'professional_area_id' => $areaSistemas->id,
                'aliases_json' => ['Ing. Sistemas', 'Ingenieria en Sistemas', 'Sistemas Informaticos', 'Lic. Sistemas', 'Ingenieria de Sistemas', 'Informatica y Sistemas'],
            ],
            [
                'canonical_name' => 'Ingeniería Informática',
                'professional_area_id' => $areaSistemas->id,
                'aliases_json' => ['Ing. Informatica', 'Ingenieria Informatica', 'Lic. Informatica', 'Ciencias de la Computacion'],
            ],
            [
                'canonical_name' => 'Ingeniería de Redes y Telecomunicaciones',
                'professional_area_id' => $areaSistemas->id,
                'aliases_json' => ['Ing. Redes', 'Ingenieria en Redes', 'Telecomunicaciones', 'Redes y Telecomunicaciones'],
            ],
            // Administración y Gestión
            [
                'canonical_name' => 'Administración de Empresas',
                'professional_area_id' => $areaAdmin->id,
                'aliases_json' => ['Lic. Administracion', 'Administracion de Empresas', 'Administrador', 'Ing. Comercial', 'Ingenieria Comercial'],
            ],
            [
                'canonical_name' => 'Secretariado Ejecutivo',
                'professional_area_id' => $areaAdmin->id,
                'aliases_json' => ['Secretaria', 'Secretariado', 'Asistente de Gerencia', 'Secretariado Ejecutivo'],
            ],
            // Comunicación y Relaciones Públicas
            [
                'canonical_name' => 'Comunicación Social',
                'professional_area_id' => $areaCom->id,
                'aliases_json' => ['Comunicadora', 'Comunicador Social', 'Periodismo', 'Ciencias de la Comunicacion', 'Comunicacion Social'],
            ],
            [
                'canonical_name' => 'Diseño Gráfico',
                'professional_area_id' => $areaCom->id,
                'aliases_json' => ['Disenador Grafico', 'Diseno Grafico', 'Diseño y Produccion Multimedia', 'Multimedia'],
            ],
            // Derecho
            [
                'canonical_name' => 'Derecho',
                'professional_area_id' => $areaDer->id,
                'aliases_json' => ['Abogacía', 'Abogado', 'Ciencias Juridicas', 'Juridico'],
            ],
            // Contabilidad y Finanzas
            [
                'canonical_name' => 'Contaduría Pública',
                'professional_area_id' => $areaFin->id,
                'aliases_json' => ['Contador Publico', 'Contaduria', 'Auditoria Financiera', 'Contabilidad', 'C.P.A.', 'Auditor'],
            ],
        ];

        foreach ($careers as $c) {
            $slug = Str::slug($c['canonical_name']);
            CatalogCareer::updateOrCreate(
                ['normalized_slug' => $slug],
                array_merge($c, ['normalized_slug' => $slug, 'is_active' => true])
            );
        }

        // 5. CARGOS / PUESTOS DE TRABAJO CANÓNICOS
        $positions = [
            // Administración y Gestión
            [
                'canonical_name' => 'Asistente Administrativo',
                'professional_area_id' => $areaAdmin->id,
                'seniority_level' => 'junior',
                'aliases_json' => ['Auxiliar Administrativo', 'Encargado Administrativo', 'Apoyo Administrativo', 'Asistente de Oficina', 'Secretaria Administrativa'],
            ],
            [
                'canonical_name' => 'Responsable Administrativo',
                'professional_area_id' => $areaAdmin->id,
                'seniority_level' => 'senior',
                'aliases_json' => ['Jefe Administrativo', 'Administrador General', 'Coordinador Administrativo', 'Encargado de Administracion'],
            ],
            // Sistemas y Tecnología
            [
                'canonical_name' => 'Desarrollador de Software',
                'professional_area_id' => $areaSistemas->id,
                'seniority_level' => 'semi-senior',
                'aliases_json' => ['Programador', 'Developer', 'Ingeniero de Software', 'Analista Programador', 'Desarrollador Fullstack'],
            ],
            [
                'canonical_name' => 'Administrador de Sistemas',
                'professional_area_id' => $areaSistemas->id,
                'seniority_level' => 'semi-senior',
                'aliases_json' => ['SysAdmin', 'Soporte Tecnico', 'Encargado de Sistemas', 'Responsable de Computo', 'Encargado de Soporte'],
            ],
            // Comunicación y Relaciones Públicas
            [
                'canonical_name' => 'Responsable de Contenido Multimedia',
                'professional_area_id' => $areaCom->id,
                'seniority_level' => 'semi-senior',
                'aliases_json' => ['Diseñador Multimedia', 'Creador de Contenido', 'Encargado de Marketing Digital', 'Editor Multimedia', 'Productor Audiovisual'],
            ],
        ];

        foreach ($positions as $p) {
            $slug = Str::slug($p['canonical_name']);
            CatalogJobPosition::updateOrCreate(
                ['normalized_slug' => $slug],
                array_merge($p, ['normalized_slug' => $slug, 'is_active' => true])
            );
        }
    }
}
