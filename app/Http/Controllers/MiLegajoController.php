<?php

namespace App\Http\Controllers;

use App\Models\Postulante;
use App\Models\PostulanteMerito;
use App\Models\MeritoArchivo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MiLegajoController extends Controller
{
    /**
     * Get the current user's postulante profile.
     * If it doesn't exist, create a draft based on user data.
     */
    public function show()
    {
        $user = Auth::user();

        // Check permission if user uses permission system
        if (method_exists($user, 'hasPermissionTo') && !$user->hasPermissionTo('ver_mi_legajo')) {
             return response()->json(['message' => 'No tiene autorización para acceder a este módulo.'], 403);
        }

        // 1. Try to find existing profile
        $postulante = Postulante::where('user_id', $user->id)
            ->with(['meritos.archivos'])
            ->first();

        // 2. If not found, create one automatically
        if (!$postulante) {
            // Check if a postulante with same CI exists to link it (maybe they applied before being hired?)
            // This is a safety check: if CI matches user CI, link them.
            if ($user->ci) {
                $postulante = Postulante::where('ci', $user->ci)->first();
                if ($postulante) {
                    $postulante->user_id = $user->id;
                    $postulante->save();
                }
            }

            // If still no postulante, create new one
            if (!$postulante) {
                $postulante = Postulante::create([
                    'user_id' => $user->id,
                    'nombres' => $user->nombres,
                    'apellidos' => trim($user->apellido_paterno . ' ' . $user->apellido_materno),
                    'ci' => $user->ci,
                    'email' => $user->email,
                    'nacionalidad' => 'Boliviana', // Default
                    // Other fields null by default
                ]);
            }

            // Reload with relations
            $postulante->load(['meritos.archivos']);
        }

        return response()->json([
            'success' => true,
            'data' => $postulante
        ]);
    }

    /**
     * Update the user's legajo (profile, cv, merits)
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        if (method_exists($user, 'hasPermissionTo') && !$user->hasPermissionTo('ver_mi_legajo')) {
             return response()->json(['message' => 'No tiene autorización para realizar cambios.'], 403);
        }

        $postulante = $user->postulante;

        if (!$postulante) {
            return response()->json(['message' => 'Perfil no encontrado.'], 404);
        }

        try {
            DB::beginTransaction();

            // 1. Update basic info
            $postulante->update([
                'nacionalidad' => $request->input('nacionalidad', $postulante->nacionalidad),
                'direccion_domicilio' => $request->input('direccion_domicilio', $postulante->direccion_domicilio),
                'celular' => $request->input('celular', $postulante->celular),
                'ref_personal_celular' => $request->input('ref_personal_celular', $postulante->ref_personal_celular),
                'ref_personal_parentesco' => $request->input('ref_personal_parentesco', $postulante->ref_personal_parentesco),
                'ref_laboral_celular' => $request->input('ref_laboral_celular', $postulante->ref_laboral_celular),
                'ref_laboral_detalle' => $request->input('ref_laboral_detalle', $postulante->ref_laboral_detalle),
            ]);


            // 2. Handle connection updates (CI, Name) if user wants to sync?
            // Usually we keep them sync, but let's assume user source is primary for login, postulante is for CV.

            // 3. Handle main files
            if ($request->hasFile('foto_perfil')) {
                // Delete old if exists? Maybe later
                $path = $request->file('foto_perfil')->store('postulantes/fotos', 'public');
                $postulante->foto_perfil_path = $path;
            }
            if ($request->hasFile('cv_pdf')) {
                $path = $request->file('cv_pdf')->store('postulantes/cv', 'public');
                $postulante->cv_pdf_path = $path;
            }
            if ($request->hasFile('ci_archivo')) {
                $path = $request->file('ci_archivo')->store('postulantes/ci', 'public');
                $postulante->ci_archivo_path = $path;
            }
            if ($request->hasFile('carta_postulacion')) { // Maybe not needed for internal staff?
                $path = $request->file('carta_postulacion')->store('postulantes/cartas', 'public');
                $postulante->carta_postulacion_path = $path;
            }
            $postulante->save();

            // ========================================================
            // NÚCLEO CENTRAL (SSO): Sincronizar datos con Personas
            // Incluyendo las rutas recién creadas de Foto y CV
            // ========================================================
            $apellidos_parts = explode(' ', $postulante->apellidos, 2);
            \App\Models\Persona::updateOrCreate(
                ['ci' => $postulante->ci],
                [
                    'nombres'          => $postulante->nombres,
                    'apellido_paterno' => $apellidos_parts[0] ?? '',
                    'apellido_materno' => $apellidos_parts[1] ?? '',
                    'correo_personal'  => $postulante->email,
                    'celular'          => $postulante->celular,
                    'direccion'        => $postulante->direccion_domicilio,
                    'foto'             => $postulante->foto_perfil_path,
                    'cv_path'          => $postulante->cv_pdf_path,
                ]
            );
            // ========================================================
            // 4. Handle Meritos (This is complex because it's a list)
            // We can add new merits. Updating existing ones might require ID.

            // 5. PROCESAR MÉRITOS (DINÁMICO)
            if ($request->has('meritos') && is_array($request->meritos)) {
                $meritosInput = $request->meritos;

                foreach ($meritosInput as $index => $meritoData) {

                    // Decode respuestas if JSON string
                    $respuestas = [];
                    if (isset($meritoData['respuestas'])) {
                         $respuestas = is_string($meritoData['respuestas'])
                            ? json_decode($meritoData['respuestas'], true)
                            : $meritoData['respuestas'];
                    }

                    // Find or Create Merito
                    $merito = PostulanteMerito::updateOrCreate(
                        [
                            'id' => $meritoData['id'] ?? null,
                            'postulante_id' => $postulante->id
                        ],
                        [
                            'tipo_documento_id' => $meritoData['tipo_documento_id'],
                            'descripcion' => $respuestas['descripcion'] ?? 'Documento cargado', // Fallback
                            'respuestas' => $respuestas, // JSON cast handled by model?
                            'institucion' => $respuestas['institucion'] ?? ($respuestas['universidad'] ?? null),
                            'fecha_emision' => $respuestas['fecha'] ?? null,
                        ]
                    );

                    // Handle FILES for this merit
                    // We look for files in the request with key: meritos.{index}.archivos
                    $filesData = $request->file("meritos.{$index}.archivos");

                    if ($filesData && is_array($filesData)) {
                        foreach ($filesData as $configKey => $file) {
                             if ($file instanceof \Illuminate\Http\UploadedFile) {
                                 $path = $file->store("postulantes/{$user->id}/meritos", 'public');

                                 // Guardar o actualizar archivo específico por config_key
                                 $merito->archivos()->updateOrCreate(
                                     ['config_archivo_id' => $configKey],
                                     ['archivo_path' => $path]
                                 );
                             }
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Legajo actualizado correctamente.',
                'data' => $postulante->fresh(['meritos.archivos'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando legajo: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }
}
