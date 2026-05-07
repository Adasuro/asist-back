<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Justificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JustificationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'asistencia_id' => 'required|exists:asistencias,id',
            'motivo' => 'required|string',
            'documento_url' => 'nullable|string',
        ]);

        $justification = Justificacion::updateOrCreate(
            ['asistencia_id' => $validated['asistencia_id']],
            [
                'registrado_por' => Auth::id(),
                'motivo' => $validated['motivo'],
                'documento_url' => $validated['documento_url'] ?? null,
                'fecha_presentacion' => now()->toDateString(),
            ]
        );

        return response()->json([
            'message' => 'Justificación registrada correctamente.',
            'justification' => $justification
        ]);
    }

    public function show($asistenciaId)
    {
        $justification = Justificacion::where('asistencia_id', $asistenciaId)->first();
        return response()->json($justification);
    }
}
