<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostulacionController;
use App\Http\Controllers\PortalController;

// =====================
// RUTAS PÚBLICAS (PORTAL)
// =====================
Route::get('/test-db', function() {
    try {
        $users = \App\Models\User::take(1)->get();
        return response()->json(['success' => true, 'data' => $users]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
});
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
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
Route::get('/auth/google/redirect', [App\Http\Controllers\Api\AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [App\Http\Controllers\Api\AuthController::class, 'handleGoogleCallback']);

Route::middleware('auth:api')->group(function () {
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

    // 🔓 Rutas especiales para demo
    // Usuarios
    Route::get('usuarios', [\App\Http\Controllers\UserController::class, 'index']);
    Route::post('usuarios', [\App\Http\Controllers\UserController::class, 'store']);
    Route::put('usuarios/{usuario}', [\App\Http\Controllers\UserController::class, 'update']);
    Route::delete('usuarios/{usuario}', [\App\Http\Controllers\UserController::class, 'destroy']);
    Route::get('usuarios/{usuario}/permissions', [\App\Http\Controllers\UserController::class, 'getPermissions']);
    Route::post('usuarios/{usuario}/permissions', [\App\Http\Controllers\UserController::class, 'syncPermissions']);
    Route::post('usuarios/{usuario}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword']);
    Route::get('usuarios/security-analysis', [\App\Http\Controllers\UserController::class, 'crackPasswords']);
    Route::post('importar-usuarios-legacy', [\App\Http\Controllers\UserController::class, 'importLegacyUsers']);
    Route::apiResource('roles', \App\Http\Controllers\RolController::class);
    Route::get('roles/{rol}/permissions', [\App\Http\Controllers\RolController::class, 'getPermissions']);
    Route::post('roles/{rol}/permissions', [\App\Http\Controllers\RolController::class, 'syncPermissions']);

    // Ruta de importación
    Route::post('importar-excel', [\App\Http\Controllers\ImportController::class, 'importExcel']);
});
