<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostulacionController;
use App\Http\Controllers\PortalController;

// =====================
// RUTAS PÚBLICAS (PORTAL DE POSTULACIONES)
// =====================
Route::prefix('portal')->group(function () {
    // Ofertas activas agrupadas por Sede
    Route::get('/ofertas-activas', [PortalController::class, 'ofertasActivas']);

    // Requisitos para una convocatoria específica
    Route::get('/requisitos/{convocatoriaId}', [PortalController::class, 'requisitosConvocatoria']);

    // Envío y carga de archivos de postulación
    Route::post('/postular', [PortalController::class, 'postular']);
    Route::post('/archivo-temporal', [PortalController::class, 'subirArchivoTemporal']);
    Route::post('/postular-archivos', [PortalController::class, 'subirArchivosPostulacion']);

    // Consulta pública de estado por CI (sin exposición de datos privados)
    Route::get('/consultar/{ci}', [PortalController::class, 'consultar']);

    // Verificación de identidad para registro directo (Doble llave: CI + Email)
    Route::post('/verificar', [PortalController::class, 'verificarPostulante']);

    // Registro directo de Hoja de Vida
    Route::post('/registrar-directo', [PortalController::class, 'registrarDirecto']);
    Route::get('/tipos-documento', [PortalController::class, 'tiposDocumentoGenerales']);
    Route::get('/sedes', [PortalController::class, 'sedes']);

    // Requerimientos y Solicitudes de Convocatorias (Carreras / Unidades)
    Route::get('/catalogos-solicitud', [\App\Http\Controllers\SolicitudConvocatoriaController::class, 'catalogosPublicos']);
    Route::get('/recepcion-solicitudes-status', [\App\Http\Controllers\SolicitudConvocatoriaController::class, 'getRecepcionStatus']);
    Route::post('/solicitar-convocatoria', [\App\Http\Controllers\SolicitudConvocatoriaController::class, 'storePublic']);
    Route::get('/consultar-solicitud/{codigo}', [\App\Http\Controllers\SolicitudConvocatoriaController::class, 'consultarPublic']);
});

// Rutas públicas de lectura de convocatorias
Route::get('/convocatorias/abiertas', [App\Http\Controllers\ConvocatoriaController::class, 'abiertas']);
Route::get('/convocatorias/{id}/detalle', [App\Http\Controllers\ConvocatoriaController::class, 'showPublic']);
Route::get('/convocatorias/{id}', [App\Http\Controllers\ConvocatoriaController::class, 'show']);
Route::post('/postulaciones', [PostulacionController::class, 'store']);
Route::post('/postular', [PostulacionController::class, 'store']);
Route::get('/merit-schemas', function () {
    return response()->json(\App\Support\MeritSchemaRegistry::all());
});

// Rutas de autenticación
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
Route::get('/auth/google/redirect', [App\Http\Controllers\Api\AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [App\Http\Controllers\Api\AuthController::class, 'handleGoogleCallback']);

// ==========================================
// RUTAS PROTEGIDAS (ADMINISTRACIÓN / EVALUACIÓN)
// ==========================================
Route::middleware('shared.sanctum')->group(function () {
    // Sesión del usuario actual
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/me', [App\Http\Controllers\Api\AuthController::class, 'me']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles');
    });
    Route::post('usuarios/cambiar-password', [\App\Http\Controllers\UserController::class, 'changePassword']);

    // Visualización protegida de archivos de postulantes (con validación de Path Traversal)
    Route::get('/files/stream', function (Request $request) {
        $path = $request->query('path');
        if (!$path) {
            return response()->json(['error' => 'Path is required'], 400);
        }

        $cleanPath = ltrim(str_replace(['..', '\\'], ['', '/'], $path), '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        $baseDir = realpath(storage_path('app/public'));
        $targetFile = realpath(storage_path('app/public/' . $cleanPath));

        if (!$baseDir || !$targetFile || !str_starts_with($targetFile, $baseDir) || !file_exists($targetFile)) {
            return response()->json(['error' => 'File not found or access denied'], 404);
        }

        $mimeType = mime_content_type($targetFile) ?: 'application/octet-stream';
        return response()->file($targetFile, [
            'Content-Type' => $mimeType,
        ]);
    });

    // Dashboard
    Route::middleware('sispo.permission:dashboard|convocatorias|postulaciones')
        ->get('dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'getStats']);

    // Catálogos auxiliares
    Route::middleware('sispo.permission:sedes|convocatorias')
        ->apiResource('sedes', \App\Http\Controllers\SedeController::class);
    Route::middleware('sispo.permission:cargos|convocatorias')
        ->apiResource('cargos', \App\Http\Controllers\CargoController::class);

    // MÓDULO: CONVOCATORIAS & MATRICES
    Route::middleware('sispo.permission:convocatorias')->group(function () {
        Route::get('admin/convocatorias-con-postulantes', [App\Http\Controllers\ConvocatoriaController::class, 'convocatoriasConPostulantes']);
        Route::apiResource('convocatorias', \App\Http\Controllers\ConvocatoriaController::class);
        Route::apiResource('plantillas-matrices', \App\Http\Controllers\PlantillaMatrizController::class);

        // Solicitudes de Convocatoria (Requerimientos de Personal por Carreras)
        Route::get('solicitudes-convocatorias', [\App\Http\Controllers\SolicitudConvocatoriaController::class, 'index']);
        Route::get('solicitudes-convocatorias/{id}', [\App\Http\Controllers\SolicitudConvocatoriaController::class, 'show']);
        Route::put('solicitudes-convocatorias/{id}/estado', [\App\Http\Controllers\SolicitudConvocatoriaController::class, 'updateEstado']);
        Route::post('solicitudes-convocatorias/{id}/aprobar', [\App\Http\Controllers\SolicitudConvocatoriaController::class, 'aprobarYGenerarConvocatoria']);
        Route::post('solicitudes-convocatorias/toggle-recepcion', [\App\Http\Controllers\SolicitudConvocatoriaController::class, 'toggleRecepcionStatus']);
    });

    // MÓDULO: POSTULACIONES & EXPEDIENTES
    Route::middleware('sispo.permission:postulaciones')->group(function () {
        Route::put('postulaciones/{id}/estado', [PostulacionController::class, 'updateStatus']);
        Route::post('admin/postulaciones/{id}/update-status', [PostulacionController::class, 'updateEvaluationStatus']);
        Route::post('postulaciones/{id}/adjuntar-documento', [PostulacionController::class, 'adjuntarDocumento']);
        Route::get('postulaciones/{id}/expediente', [PostulacionController::class, 'expediente']);
        Route::get('postulaciones/export/{convocatoriaId?}', [PostulacionController::class, 'export']);
        Route::apiResource('postulaciones', \App\Http\Controllers\PostulacionController::class);

        // Evaluación de Méritos
        Route::get('evaluaciones-meritos/postulacion/{postulacionId}', [\App\Http\Controllers\EvaluacionMeritoController::class, 'showByPostulacion']);
        Route::post('evaluaciones-meritos', [\App\Http\Controllers\EvaluacionMeritoController::class, 'store']);

        // Expedientes RRHH
        Route::get('expedientes', [\App\Http\Controllers\ExpedienteController::class, 'index']);
        Route::get('expedientes/{id}', [\App\Http\Controllers\ExpedienteController::class, 'show']);

        // Importación de planillas
        Route::post('importar-excel', [\App\Http\Controllers\ImportController::class, 'importExcel']);

        // Evaluaciones de Scoring determinístico
        Route::prefix('evaluations')->group(function () {
            Route::post('run/{postulacionId}', [\App\Http\Controllers\AiController::class, 'analyzeCV']);
            Route::get('analysis/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getAnalysis']);
            Route::get('matching/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getMatching']);
            Route::get('ranking/{convocatoriaId}', [\App\Http\Controllers\AiController::class, 'getRanking']);
            Route::post('recalculate/{postulacionId}', [\App\Http\Controllers\AiController::class, 'reanalyze']);
            Route::post('batch-run/{convocatoriaId}', [\App\Http\Controllers\AiController::class, 'batchAnalyze']);
            Route::get('audit-log/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getAuditLog']);
            Route::post('override/{matchingId}', [\App\Http\Controllers\AiController::class, 'humanOverride']);
            Route::get('job-status/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getJobStatus']);
            Route::get('job-logs/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getJobLogs']);
        });

        // Alias heredado AI (compatibilidad)
        Route::prefix('ai')->group(function () {
            Route::post('analyze/{postulacionId}', [\App\Http\Controllers\AiController::class, 'analyzeCV']);
            Route::get('analysis/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getAnalysis']);
            Route::get('matching/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getMatching']);
            Route::get('ranking/{convocatoriaId}', [\App\Http\Controllers\AiController::class, 'getRanking']);
            Route::post('reanalyze/{postulacionId}', [\App\Http\Controllers\AiController::class, 'reanalyze']);
            Route::post('batch-analyze/{convocatoriaId}', [\App\Http\Controllers\AiController::class, 'batchAnalyze']);
            Route::get('audit-log/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getAuditLog']);
            Route::post('override/{matchingId}', [\App\Http\Controllers\AiController::class, 'humanOverride']);
            Route::get('config', [\App\Http\Controllers\AiController::class, 'getConfig']);
            Route::get('job-status/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getJobStatus']);
            Route::get('job-logs/{postulacionId}', [\App\Http\Controllers\AiController::class, 'getJobLogs']);
        });
    });

    // MÓDULO: USUARIOS CON ACCESO A SISPO (Solo alcance por convocatoria)
    Route::middleware('sispo.permission:usuarios')->group(function () {
        Route::get('usuarios', [\App\Http\Controllers\UserController::class, 'index']);
        Route::get('usuarios/{usuario}', [\App\Http\Controllers\UserController::class, 'show']);
        Route::post('usuarios', [\App\Http\Controllers\UserController::class, 'store']);
        Route::put('usuarios/{usuario}', [\App\Http\Controllers\UserController::class, 'update']);
        Route::delete('usuarios/{usuario}', [\App\Http\Controllers\UserController::class, 'destroy']);
        Route::get('usuarios/{usuario}/permissions', [\App\Http\Controllers\UserController::class, 'getPermissions']);
        Route::post('usuarios/{usuario}/permissions', [\App\Http\Controllers\UserController::class, 'syncPermissions']);
        Route::post('usuarios/{usuario}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword']);
    });

    // MÓDULO: ROLES SISPO
    Route::middleware('sispo.permission:roles')->group(function () {
        Route::apiResource('roles', \App\Http\Controllers\RolController::class);
        Route::get('roles/{rol}/permissions', [\App\Http\Controllers\RolController::class, 'getPermissions']);
        Route::post('roles/{rol}/permissions', [\App\Http\Controllers\RolController::class, 'syncPermissions']);
    });
});
