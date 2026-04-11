<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PlantillaMatrizController extends Controller
{
    public function index()
    {
        return \App\Models\PlantillaMatriz::orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'matriz' => 'nullable|array'
        ]);

        return \App\Models\PlantillaMatriz::create($validated);
    }

    public function show($id)
    {
        return \App\Models\PlantillaMatriz::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'matriz' => 'nullable|array'
        ]);

        $plantilla = \App\Models\PlantillaMatriz::findOrFail($id);
        $plantilla->update($validated);
        return $plantilla;
    }

    public function destroy($id)
    {
        \App\Models\PlantillaMatriz::findOrFail($id)->delete();
        return response()->noContent();
    }
}
