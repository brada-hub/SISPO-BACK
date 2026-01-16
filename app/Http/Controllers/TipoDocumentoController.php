<?php

namespace App\Http\Controllers;

use App\Models\TipoDocumento;
use Illuminate\Http\Request;

class TipoDocumentoController extends Controller
{
    public function index()
    {
        return TipoDocumento::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria' => 'nullable|string',
            'campos' => 'nullable|array',
            'config_archivos' => 'nullable|array',
            'permite_multiples' => 'boolean',
        ]);

        return TipoDocumento::create($validated);
    }

    public function show(TipoDocumento $tipoDocumento)
    {
        return $tipoDocumento;
    }

    public function update(Request $request, TipoDocumento $tipoDocumento)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria' => 'nullable|string',
            'campos' => 'nullable|array',
            'config_archivos' => 'nullable|array',
            'permite_multiples' => 'boolean',
        ]);

        $tipoDocumento->update($validated);
        return $tipoDocumento;
    }

    public function destroy(TipoDocumento $tipoDocumento)
    {
        $tipoDocumento->delete();
        return response()->noContent();
    }
}
