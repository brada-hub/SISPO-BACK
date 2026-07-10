<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Models\Postulante;
use App\Models\Postulacion;
use App\Models\FormacionAcademica;
use App\Models\FormacionPostgrado;
use App\Models\ExperienciaProfesional;
use App\Models\Capacitacion;

echo "===============================================================\n";
echo "           SISPO RIGOROUS E2E FIRE-TEST RUNNER                 \n";
echo "===============================================================\n";

// 1. Capture Stats BEFORE the Test
$legacyMeritosBefore = DB::table('postulante_meritos')->count();
$legacyArchivosBefore = DB::table('merito_archivos')->count();

$faBefore = FormacionAcademica::count();
$fpBefore = FormacionPostgrado::count();
$epBefore = ExperienciaProfesional::count();
$cpBefore = Capacitacion::count();

echo " Baseline Counts:\n";
echo "  - Legacy postulante_meritos: {$legacyMeritosBefore}\n";
echo "  - Legacy merito_archivos   : {$legacyArchivosBefore}\n";
echo "  - Formacion Academica      : {$faBefore}\n";
echo "  - Formacion Postgrado      : {$fpBefore}\n";
echo "  - Experiencia Profesional  : {$epBefore}\n";
echo "  - Capacitacion             : {$cpBefore}\n\n";

// 2. Generate Unique Candidate CI
$ci = '999999-QA-' . rand(1000, 9999);
echo " Simulating direct/portal registration for CI: {$ci}...\n";

// 3. Construct Programmatic Request
$controller = $app->make(\App\Http\Controllers\PortalController::class);

$reqData = [
    'ci' => $ci,
    'ci_expedido' => 'CB',
    'nombres' => 'QA_TESTER',
    'apellidos' => 'NATIVE_FLOW',
    'email' => 'qa_native_' . rand(100, 999) . '@unitepc.edu',
    'celular' => '77777777',
    'nacionalidad' => 'Boliviana',
    'direccion_domicilio' => 'Avenida Blanco Galindo Km 5',
    'clasificacion' => 'ADMINISTRATIVO',
    'meritos' => [
        [
            'tipo_documento_id' => 1, // Formación Académica
            'respuestas' => json_encode([
                'nivel' => 'LICENCIATURA',
                'universidad' => 'UNITEPC',
                'profesion' => 'ODONTOLOGÍA',
                'fecha_diploma' => '2020-05-10',
                'fecha_titulo' => '2020-06-15',
            ])
        ],
        [
            'tipo_documento_id' => 2, // Postgrados
            'respuestas' => json_encode([
                'tipo_posgrado' => 'MAESTRÍA',
                'nombre_programa' => 'SALUD PÚBLICA',
                'fecha' => '2022-10-20',
                'institucion' => 'UNITEPC',
            ])
        ],
        [
            'tipo_documento_id' => 4, // Experiencia Profesional
            'respuestas' => json_encode([
                'cargo' => 'ODONTÓLOGO GENERAL',
                'empresa' => 'CLÍNICA DENTAL',
                'fecha_inicio' => '2021-01-01',
                'fecha_fin' => '2023-01-01',
            ])
        ],
        [
            'tipo_documento_id' => 5, // Capacitaciones
            'respuestas' => json_encode([
                'nombre' => 'CURSO DE ORTODONCIA AVANZADA',
                'fecha' => '2023-05-05',
                'institucion' => 'ASOCIACIÓN DENTAL',
                'horas' => '80',
            ])
        ],
    ]
];

$files = [
    'meritos' => [
        0 => [
            'archivos' => [
                'diploma' => UploadedFile::fake()->create('diploma.pdf', 100, 'application/pdf'),
                'titulo' => UploadedFile::fake()->create('titulo.pdf', 100, 'application/pdf'),
            ]
        ],
        1 => [
            'archivos' => [
                'certificado' => UploadedFile::fake()->create('certificado_pg.pdf', 100, 'application/pdf'),
            ]
        ],
        2 => [
            'archivos' => [
                'certificado' => UploadedFile::fake()->create('certificado_exp.pdf', 100, 'application/pdf'),
            ]
        ],
        3 => [
            'archivos' => [
                'certificado' => UploadedFile::fake()->create('certificado_cap.pdf', 100, 'application/pdf'),
            ]
        ]
    ]
];

$request = new Request($reqData, $reqData, [], [], $files, $_SERVER);

// 4. Run Controller Action
$response = $controller->registrarDirecto($request);
$resData = json_decode($response->getContent(), true);

if (!$resData || !($resData['success'] ?? false)) {
    echo "❌ FAILED: Controller registrarDirecto failed!\n";
    if ($resData) {
        echo "Error message: " . ($resData['message'] ?? 'Unknown error') . "\n";
    }
    exit(1);
}

echo "✅ Success: Direct registration processed correctly.\n";

// 5. Fetch Created Postulante
$postulante = Postulante::where('ci', $ci)->first();
if (!$postulante) {
    echo "❌ FAILED: Postulante not found in DB!\n";
    exit(1);
}
echo "   Postulante ID created: {$postulante->id}\n";

echo "   Running synchronous academic and docencia normalizations...\n";
(new \App\Jobs\NormalizeAcademicRecordsJob(null, null, true))->handle(
    $app->make(\App\Services\Normalization\AcademicLevelNormalizer::class),
    $app->make(\App\Services\Normalization\CareerNormalizer::class)
);
echo "   Normalization finished successfully.\n";

// 6. Create simulated Postulacion for Convocatoria/Oferta ID = 8
$postulacion = Postulacion::create([
    'postulante_id' => $postulante->id,
    'oferta_id' => 8,
    'pretension_salarial' => 5000,
    'porque_cargo' => 'Prueba QA de baremo y integridad documental.',
    'estado' => 'enviada',
    'fecha_postulacion' => now(),
]);
echo "✅ Success: Postulación ID created: {$postulacion->id} linked to Oferta ID: 8 (Odontología).\n\n";

// 7. Capture Stats AFTER the Test
$legacyMeritosAfter = DB::table('postulante_meritos')->count();
$legacyArchivosAfter = DB::table('merito_archivos')->count();

$faAfter = FormacionAcademica::count();
$fpAfter = FormacionPostgrado::count();
$epAfter = ExperienciaProfesional::count();
$cpAfter = Capacitacion::count();

echo "===============================================================\n";
echo "           VERIFICACIÓN DE INTEGRIDAD SQL                      \n";
echo "===============================================================\n";

$legacyMeritsDiff = $legacyMeritosAfter - $legacyMeritosBefore;
$legacyArchivosDiff = $legacyArchivosAfter - $legacyArchivosBefore;

$faDiff = $faAfter - $faBefore;
$fpDiff = $fpAfter - $fpBefore;
$epDiff = $epAfter - $epBefore;
$cpDiff = $cpAfter - $cpBefore;

$pass = true;

// Assertion 1: Legacy counts should NOT grow
if ($legacyMeritsDiff !== 0) {
    echo "❌ ASSERTION FAILED: postulante_meritos grew by {$legacyMeritsDiff}!\n";
    $pass = false;
} else {
    echo "🛡️ OK: postulante_meritos did NOT grow (+0 records).\n";
}

if ($legacyArchivosDiff !== 0) {
    echo "❌ ASSERTION FAILED: merito_archivos grew by {$legacyArchivosDiff}!\n";
    $pass = false;
} else {
    echo "🛡️ OK: merito_archivos did NOT grow (+0 records).\n";
}

// Assertion 2: Normalized counts MUST grow by 1
if ($faDiff === 1) {
    echo "✅ OK: formaciones_academicas grew correctly (+1 record).\n";
} else {
    echo "❌ ASSERTION FAILED: formaciones_academicas grew by {$faDiff} instead of +1!\n";
    $pass = false;
}

if ($fpDiff === 1) {
    echo "✅ OK: formaciones_postgrado grew correctly (+1 record).\n";
} else {
    echo "❌ ASSERTION FAILED: formaciones_postgrado grew by {$fpDiff} instead of +1!\n";
    $pass = false;
}

if ($epDiff === 1) {
    echo "✅ OK: experiencias_profesionales grew correctly (+1 record).\n";
} else {
    echo "❌ ASSERTION FAILED: experiencias_profesionales grew by {$epDiff} instead of +1!\n";
    $pass = false;
}

if ($cpDiff === 1) {
    echo "✅ OK: capacitaciones grew correctly (+1 record).\n";
} else {
    echo "❌ ASSERTION FAILED: capacitaciones grew by {$cpDiff} instead of +1!\n";
    $pass = false;
}

// Assertion 3: Verify direct file columns and physically existing files in storage
echo "\n Verificando almacenamiento físico de archivos:\n";
$createdFA = FormacionAcademica::where('postulante_id', $postulante->id)->first();
$createdFP = FormacionPostgrado::where('postulante_id', $postulante->id)->first();
$createdEP = ExperienciaProfesional::where('postulante_id', $postulante->id)->first();
$createdCP = Capacitacion::where('postulante_id', $postulante->id)->first();

$filesToCheck = [
    'FA Diploma' => $createdFA->diploma_archivo_path,
    'FA Título' => $createdFA->titulo_archivo_path,
    'FP Certificado' => $createdFP->certificado_archivo_path,
    'EP Certificado' => $createdEP->certificado_archivo_path,
    'CP Certificado' => $createdCP->certificado_archivo_path,
];

foreach ($filesToCheck as $name => $path) {
    if (!$path) {
        echo "❌ ASSERTION FAILED: Column for {$name} is empty!\n";
        $pass = false;
        continue;
    }
    $exists = Storage::disk('public')->exists($path);
    if ($exists) {
        echo "✅ File exists: {$name} | Path: {$path}\n";
    } else {
        echo "❌ ASSERTION FAILED: File missing from storage disk: {$name} | Path: {$path}\n";
        $pass = false;
    }
}

// Assertion 4: source_merito_id must be null
if ($createdFA->source_merito_id === null && $createdFP->source_merito_id === null) {
    echo "✅ OK: source_merito_id is correctly NULL (zero legacy dependencies).\n";
} else {
    echo "⚠️ Warning: source_merito_id is not null! FA: {$createdFA->source_merito_id}, FP: {$createdFP->source_merito_id}\n";
}

echo "\n===============================================================\n";
if ($pass) {
    echo "🌟 RESULTADO DE PRUEBA DE FUEGO: 100% EXCELENTE / APROBADO    \n";
    echo "   ¡La escritura y normalización de méritos funciona perfecto!\n";
} else {
    echo "❌ RESULTADO DE PRUEBA DE FUEGO: RECHAZADO / CON FALLAS      \n";
}
echo "===============================================================\n";

// Save postulation ID to print it in final report
echo "POSTULACION_ID_CREATED: {$postulacion->id}\n";
