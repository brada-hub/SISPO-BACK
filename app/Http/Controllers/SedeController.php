<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Http\Request;

class SedeController extends Controller
{
    public function index()
    {
        return Sede::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        return Sede::create($validated);
    }

    public function show(Sede $sede)
    {
        return $sede;
    }

    public function update(Request $request, Sede $sede)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $sede->update($validated);
        return $sede;
    }

    public function destroy(Sede $sede)
    {
        $sede->delete();
        return response()->noContent();
    }
}
