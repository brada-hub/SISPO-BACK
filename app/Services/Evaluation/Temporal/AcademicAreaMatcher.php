<?php

namespace App\Services\Evaluation\Temporal;

use App\Models\CatalogProfessionalArea;
use Illuminate\Support\Str;

class AcademicAreaMatcher
{
    /**
     * Map unnormalized career names to a professional area ID using robust keyword matching.
     */
    public function match(string $carreraRaw): array
    {
        $clean = mb_strtolower(trim($carreraRaw), 'UTF-8');
        $clean = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $clean);

        // Core business classification rules by keyword mapping
        $mapping = [
            'sistemas_tecnologia' => [
                'sistemas', 'tecnologia', 'informatica', 'computacion', 'software', 'redes', 
                'programador', 'web', 'ti', 'telecomunicaciones', 'computo', 'sonido', 'electronic',
                'informacion', 'sistema'
            ],
            'administracion_gestion' => [
                'administracion', 'gestor', 'gestion', 'recursos humanos', 'secretariado', 
                'oficina', 'asistente', 'auxiliar', 'secretaria', 'comercial', 'adm', 'proyectos'
            ],
            'comunicacion_social' => [
                'comunicacion', 'periodismo', 'relaciones publicas', 'diseno', 'multimedia', 
                'audiovisual', 'radio', 'tv', 'prensa', 'artes escenicas', 'television', 'video', 
                'fotografia', 'gastronomia', 'escenicas'
            ],
            'educacion' => [
                'educacion', 'pedagogia', 'docente', 'ciencias de la educacion', 'pedagogico', 'parvularia',
                'idiomas', 'ingles', 'aymara', 'idioma', 'linguista'
            ],
            'derecho' => [
                'derecho', 'leyes', 'jurista', 'juridico', 'abogacia', 'abogado', 'abogada', 'politica', 
                'publica', 'sociologia', 'sociologo', 'antropologia', 'filosofia', 'sociales', 'militar'
            ],
            'contabilidad_finanzas' => [
                'contabilidad', 'contaduria', 'auditoria', 'finanzas', 'economia', 'economista', 
                'tesoreria', 'impuestos', 'cpa', 'auditor', 'financiera', 'auditora'
            ],
            'salud_medicina' => [
                'medicina', 'medico', 'cirujano', 'cirugia', 'salud publica', 'clinica', 
                'veterinaria', 'zootecnia', 'fisioterapia', 'kinesiologia', 'fonoaudiologia', 
                'pediatra', 'neonatologo', 'psicologia', 'psicologo'
            ],
            'salud_odontologia' => [
                'odontologia', 'dentista', 'bucal', 'protesis dental', 'odontopediatra'
            ],
            'salud_enfermeria' => [
                'enfermeria', 'enfermero', 'enfermera'
            ],
            'salud_bioquimica' => [
                'bioquimica', 'farmacia', 'farmaceutico', 'farmaceutica', 'laboratorio', 'quimico',
                'quimica'
            ],
            'ingenieria_operaciones' => [
                'industrial', 'petrolero', 'forestal', 'minas', 'civil', 'electromecanico', 
                'mecanica', 'electrica', 'procesos', 'logistica', 'mantenimiento', 'fisica',
                'fisico', 'petroleo', 'arquitecto'
            ],
        ];

        foreach ($mapping as $code => $keywords) {
            foreach ($keywords as $kw) {
                if (Str::contains($clean, $kw)) {
                    $area = CatalogProfessionalArea::where('code', $code)->first();
                    if ($area) {
                        return [
                            'professional_area_id'      => $area->id,
                            'area_confidence'           => 90.00,
                            'area_normalization_method' => 'keyword_rules_match',
                        ];
                    }
                }
            }
        }

        return [
            'professional_area_id'      => null,
            'area_confidence'           => 0.00,
            'area_normalization_method' => null,
        ];
    }
}
