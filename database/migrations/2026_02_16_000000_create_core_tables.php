<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The connection name for the migration.
     *
     * @var string
     */
    /**
     * The connection name for the migration.
     *
     * @var string
     */
    // protected $connection = 'core'; // Let it use default during migration command if specifying path, or force it inside up/down

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = 'core';

        // 1. Systems (Sistemas)
        Schema::connection($connection)->create('systems', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->string('url')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // 2. Sedes (Compartidas)
        Schema::connection($connection)->create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('abreviacion', 10)->nullable();
            $table->string('departamento')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 3. Roles
        Schema::connection($connection)->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->foreignId('system_id')->nullable()->constrained('systems')->onDelete('cascade');
            $table->string('description')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['name', 'guard_name', 'system_id']);
        });

        // 4. Permissions
        Schema::connection($connection)->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->foreignId('system_id')->nullable()->constrained('systems')->onDelete('cascade');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name', 'system_id']);
        });

        // 5. Users
        Schema::connection($connection)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('name')->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('email')->unique();
            $table->string('ci')->nullable()->unique();
            $table->string('password');
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->onDelete('set null');
            $table->foreignId('rol_id')->nullable()->constrained('roles')->onDelete('set null');
            $table->boolean('activo')->default(true);
            $table->string('google_id')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        // 6. User Systems Assigment
        Schema::connection($connection)->create('user_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('system_id')->constrained('systems')->onDelete('cascade');
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'system_id']);
        });

        // 7. Model Has Roles
        Schema::connection($connection)->create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        // 8. Role Has Permissions
        Schema::connection($connection)->create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');

            $table->primary(['permission_id', 'role_id']);
        });

        // Insertar Sistemas por defecto
        DB::connection('core')->table('systems')->insert([
            ['name' => 'SISPO', 'display_name' => 'Sistema de Postulaciones', 'active' => true],
            ['name' => 'SIGVA', 'display_name' => 'Sistema de Gestión de Vacaciones', 'active' => true],
        ]);

        // Roles Básicos Globales
        DB::connection('core')->table('roles')->insert([
            ['name' => 'SuperAdmin', 'guard_name' => 'web', 'system_id' => null, 'description' => 'Acceso total a todo', 'activo' => true],
            ['name' => 'Admin', 'guard_name' => 'web', 'system_id' => null, 'description' => 'Administrador Global', 'activo' => true],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = 'core';
        Schema::connection($connection)->dropIfExists('role_has_permissions');
        Schema::connection($connection)->dropIfExists('model_has_roles');
        Schema::connection($connection)->dropIfExists('user_systems');
        Schema::connection($connection)->dropIfExists('users');
        Schema::connection($connection)->dropIfExists('permissions');
        Schema::connection($connection)->dropIfExists('roles');
        Schema::connection($connection)->dropIfExists('sedes');
        Schema::connection($connection)->dropIfExists('systems');
    }
};
