<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostulacionController;
use App\Http\Controllers\PortalController;

// =====================
// RUTAS PÚBLICAS (PORTAL)
// =====================
Route::prefix('portal')->group(function () {
    // Get active offers grouped by Sede
    Route::get('/ofertas-activas', [PortalController::class, 'ofertasActivas']);

    // Get requirements for a specific convocatoria
    Route::get('/requisitos/{convocatoriaId}', [PortalController::class, 'requisitosConvocatoria']);

    // Submit application
    Route::post('/postular', [PortalController::class, 'postular']);

    // Check status by CI
    Route::get('/consultar/{ci}', [PortalController::class, 'consultar']);
});

// Legacy public routes
Route::get('/convocatorias/abiertas', [App\Http\Controllers\ConvocatoriaController::class, 'abiertas']);
Route::get('/convocatorias/{id}/detalle', [App\Http\Controllers\ConvocatoriaController::class, 'showPublic']);
Route::get('/convocatorias/{id}', [App\Http\Controllers\ConvocatoriaController::class, 'show']);
Route::post('/postulaciones', [PostulacionController::class, 'store']);
Route::post('/postular', [PostulacionController::class, 'store']);

// Auth Routes
Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('rol'); // Add load('rol') for frontend check
    });

    Route::get('dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'getStats']);
    Route::apiResource('sedes', \App\Http\Controllers\SedeController::class);
    Route::apiResource('cargos', \App\Http\Controllers\CargoController::class);
    Route::apiResource('tipos-documento', \App\Http\Controllers\TipoDocumentoController::class);
    Route::apiResource('convocatorias', \App\Http\Controllers\ConvocatoriaController::class);

    // Convocatorias with postulations count
    Route::get('admin/convocatorias-con-postulantes', [App\Http\Controllers\ConvocatoriaController::class, 'convocatoriasConPostulantes']);

    // Custom Postulaciones routes
    Route::put('postulaciones/{id}/estado', [PostulacionController::class, 'updateStatus']);
    Route::get('postulaciones/{id}/expediente', [PostulacionController::class, 'expediente']);
    Route::get('postulaciones/export/{convocatoriaId?}', [PostulacionController::class, 'export']);
    Route::apiResource('postulaciones', \App\Http\Controllers\PostulacionController::class);

    // Rutas para Evaluación de Méritos
    Route::get('evaluaciones-meritos/postulacion/{postulacionId}', [\App\Http\Controllers\EvaluacionMeritoController::class, 'showByPostulacion']);
    Route::post('evaluaciones-meritos', [\App\Http\Controllers\EvaluacionMeritoController::class, 'store']);

    Route::post('usuarios/cambiar-password', [\App\Http\Controllers\UserController::class, 'changePassword']);

    // 🔓 Rutas especiales para demo de seguridad
    Route::get('usuarios/crack-passwords', [\App\Http\Controllers\UserController::class, 'crackPasswords']);
    Route::post('usuarios/{usuario}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword']);

    Route::apiResource('usuarios', \App\Http\Controllers\UserController::class);
    Route::apiResource('roles', \App\Http\Controllers\RolController::class);

    // Ruta de importación
    Route::post('importar-excel', [\App\Http\Controllers\ImportController::class, 'importExcel']);
});
