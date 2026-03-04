<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Postulante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpedienteController extends Controller
{
    /**
     * Display a listing of all administrative staff with their expediente status.
     * Permission: 'ver_todo_personal'
     */
    public function index(Request $request)
    {
        // 1. Get all Staff (Users)
        $usersQuery = User::with(['postulante', 'rol', 'sede'])->whereNotNull('rol_id');

        // Security: Filter by Jurisdiction
        $currentUser = auth()->user();
        $isGlobalAdmin = in_array(strtoupper($currentUser->rol->name ?? ''), ['SUPERADMIN', 'SUPER ADMIN', 'ADMINISTRADOR']);
        $jurisdiccion = $currentUser->jurisdiccion ?? [];
        $allowedSedes = [];

        if (!$isGlobalAdmin) {
            $allowedSedes = !empty($jurisdiccion) ? $jurisdiccion : ($currentUser->sede_id ? [$currentUser->sede_id] : []);
        }

        if ($request->filled('sede_id')) {
            $searchSede = $request->sede_id;
            // If restricted, ensure requested sede is allowed
            if (!empty($allowedSedes)) {
                $usersQuery->where('sede_id', in_array($searchSede, $allowedSedes) ? $searchSede : -1);
            } else {
                $usersQuery->where('sede_id', $searchSede);
            }
        } else if (!empty($allowedSedes)) {
            $usersQuery->whereIn('sede_id', $allowedSedes);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $usersQuery->where(function($q) use ($search) {
                $q->where('nombres', 'like', "%$search%")
                  ->orWhere('apellidos', 'like', "%$search%")
                  ->orWhere('ci', 'like', "%$search%");
            });
        }

        $users = $usersQuery->get()->map(function($user) {
            $status = 'pendiente';
            $docsCount = 0;
            if ($user->postulante) {
                if ($user->postulante->cv_pdf_path) $docsCount++;
                if ($user->postulante->ci_archivo_path) $docsCount++;
                if ($docsCount >= 2) $status = 'completo';
                else if ($docsCount > 0) $status = 'parcial';
            }

            return [
                'id' => 'u' . $user->id,
                'type' => 'staff',
                'real_id' => $user->id,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'ci' => $user->ci,
                'email' => $user->email,
                'rol' => $user->rol ? $user->rol->nombre : 'Staff',
                'sede' => $user->sede ? $user->sede->nombre : 'Sin Sede',
                'tiene_legajo' => !!$user->postulante,
                'estado_legajo' => $status,
                'postulante_id' => $user->postulante ? $user->postulante->id : null
            ];
        });

        // 2. Get Personnel from Direct Registration (Postulantes marked as Administrative but not linked to user yet)
        $directQuery = Postulante::whereNull('user_id')
            ->whereNotNull('clasificacion')
            ->with('sede');

        if ($request->filled('sede_id')) {
            $searchSede = $request->sede_id;
            if (!empty($allowedSedes)) {
                $directQuery->where('sede_id', in_array($searchSede, $allowedSedes) ? $searchSede : -1);
            } else {
                $directQuery->where('sede_id', $searchSede);
            }
        } else if (!empty($allowedSedes)) {
            $directQuery->whereIn('sede_id', $allowedSedes);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $directQuery->where(function($q) use ($search) {
                $q->where('nombres', 'like', "%$search%")
                  ->orWhere('apellidos', 'like', "%$search%")
                  ->orWhere('ci', 'like', "%$search%");
            });
        }

        $directs = $directQuery->get()->map(function($p) {
            $status = 'pendiente';
            $docsCount = 0;
            if ($p->cv_pdf_path) $docsCount++;
            if ($p->ci_archivo_path) $docsCount++;
            if ($docsCount >= 2) $status = 'completo';
            else if ($docsCount > 0) $status = 'parcial';

            return [
                'id' => 'p' . $p->id,
                'type' => 'staff_portal',
                'real_id' => $p->id,
                'nombres' => $p->nombres,
                'apellidos' => $p->apellidos,
                'ci' => $p->ci,
                'email' => $p->email,
                'rol' => $p->clasificacion ?: 'Personal',
                'sede' => $p->sede ? $p->sede->nombre : 'Sin Sede',
                'tiene_legajo' => true,
                'estado_legajo' => $status,
                'postulante_id' => $p->id
            ];
        });

        // Merge
        $all = $users->concat($directs)->sortBy('nombres')->values();

        // Filter by state if needed
        if ($request->filled('estado_legajo') && $request->estado_legajo !== 'todos') {
            $targetStatus = $request->estado_legajo;
            $all = $all->filter(function($item) use ($targetStatus) {
                return $item['estado_legajo'] === $targetStatus;
            });
        }

        // Manual Pagination
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 30);
        $total = $all->count();
        $items = $all->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $items,
                'current_page' => (int)$page,
                'total' => $total,
                'per_page' => (int)$perPage,
            ]
        ]);
    }

    /**
     * Show detailed expediente for a specific user
     */
    public function show($id)
    {
        $type = substr($id, 0, 1);
        $realId = substr($id, 1);

        if ($type === 'u') {
            // Staff member
            $user = User::with(['postulante.meritos.tipoDocumento', 'postulante.meritos.archivos', 'rol', 'sede'])->findOrFail($realId);
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } else {
            // Personnel from Portal or External applicant
            $postulante = Postulante::with(['meritos.tipoDocumento', 'meritos.archivos', 'sede'])->findOrFail($realId);

            // Map to a common structure similar to User for the frontend
            $data = [
                'nombres' => $postulante->nombres,
                'apellidos' => $postulante->apellidos,
                'ci' => $postulante->ci,
                'email' => $postulante->email,
                'rol' => ['nombre' => $postulante->clasificacion ?: 'POSTULANTE (EXT.)'],
                'sede' => $postulante->sede,
                'postulante' => $postulante
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        }
    }
}
