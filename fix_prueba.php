<?php
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Rol;
use App\Models\Permission;

echo "Iniciando corrección permisos usuario prueba...\n";

// 1. Obtener usuario
$u = User::where('ci', '1326')->first();
if (!$u) exit("Usuario 1326 no encontrado.\n");

echo "Usuario: " . $u->nombres . " (Rol actual: " . $u->rol_id . ")\n";

// 2. Obtener Rol USUARIO
$rolUsuario = Rol::where('name', 'USUARIO')->first();
if (!$rolUsuario) exit("Rol USUARIO no encontrado.\n");

// 3. Asignar Rol USUARIO al usuario
$u->rol_id = $rolUsuario->id;
$u->save();
echo "Rol asignado: USUARIO (ID: " . $rolUsuario->id . ")\n";

// 4. Quitar permiso 'postulaciones' del Rol USUARIO (La madre del cordero)
$permPost = Permission::where('name', 'postulaciones')->first();
if ($permPost) {
    // Usamos DB directa porque Eloquent usa default connection a veces
    $deleted = DB::connection('core')->table('role_has_permissions')
        ->where('role_id', $rolUsuario->id)
        ->where('permission_id', $permPost->id)
        ->delete();

    if ($deleted) {
        echo "Permiso 'postulaciones' ELIMINADO del Rol USUARIO.\n";
    } else {
        echo "Permiso 'postulaciones' NO encontrado en Rol USUARIO (o ya eliminado).\n";
    }
}

// 5. Quitar permisos directos (si tuviera)
try {
    // Si la tabla model_has_permissions no existe, try-catch lo salvará
    // Pero si usamos Spatie, debería ser esa tabla (o model_has_roles/permissions)
    // Probamos borrar de model_has_permissions
    DB::connection('core')->table('model_has_permissions')
        ->where('model_id', $u->id)
        ->where('permission_id', $permPost->id)
        ->delete();
    echo "Permisos directos 'postulaciones' ELIMINADOS (si existían).\n";
} catch (\Exception $e) {
    echo "Error intentando borrar permisos directos: " . $e->getMessage() . "\n";
}

echo "DONE.\n";
