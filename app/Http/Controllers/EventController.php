<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Evento::orderBy('fecha_inicio', 'asc')->get();
        return response()->json($events);
    }

    public function store(Request $request)
    {
        $this->authorizeSuperUser($request);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo' => 'required|string|in:feriado,reunion,examen,actividad,otro',
        ]);

        $event = Evento::create($validated);

        return response()->json([
            'message' => 'Evento creado correctamente.',
            'event' => $event
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeSuperUser($request);

        $event = Evento::findOrFail($id);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo' => 'required|string|in:feriado,reunion,examen,actividad,otro',
        ]);

        $event->update($validated);

        return response()->json([
            'message' => 'Evento actualizado correctamente.',
            'event' => $event
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeSuperUser($request);

        $event = Evento::findOrFail($id);
        $event->delete();

        return response()->json([
            'message' => 'Evento eliminado correctamente.'
        ]);
    }

    private function authorizeSuperUser(Request $request): void
    {
        if ($request->user()->rol !== 'superusuario') {
            abort(response()->json(['message' => 'No tiene permisos para realizar esta acción.'], 403));
        }
    }
}
