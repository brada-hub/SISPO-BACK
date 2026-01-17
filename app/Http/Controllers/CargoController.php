<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function index()
    {
        return Cargo::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'sigla' => 'nullable|string|max:20',
        ]);

        return Cargo::create($validated);
    }

    public function show(Cargo $cargo)
    {
        return $cargo;
    }

    public function update(Request $request, Cargo $cargo)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'sigla' => 'nullable|string|max:20',
        ]);

        $cargo->update($validated);
        return $cargo;
    }

    public function destroy(Cargo $cargo)
    {
        $cargo->delete();
        return response()->noContent();
    }
}
