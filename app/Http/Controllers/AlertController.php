<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Alerta::with(['estudiante.seccion.grado'])
            ->where('resuelta', false)
            ->orderBy('created_at', 'desc');

        if ($user->rol === 'auxiliar') {
            $assignedSections = $user->secciones()->pluck('secciones.id')->toArray();
            $query->whereHas('estudiante', function ($q) use ($assignedSections) {
                $q->whereIn('seccion_id', $assignedSections);
            });
        }

        $alerts = $query->get();

        return response()->json($alerts);
    }

    public function resolve(Request $request, $id)
    {
        $user = $request->user();
        $alert = Alerta::with('estudiante')->findOrFail($id);

        if ($user->rol === 'auxiliar') {
            $assignedSections = $user->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($alert->estudiante->seccion_id, $assignedSections)) {
                return response()->json(['message' => 'No tiene permisos para modificar esta alerta.'], 403);
            }
        }

        $alert->update(['resuelta' => true]);

        return response()->json([
            'message' => 'Alerta marcada como resuelta.',
            'alert' => $alert
        ]);
    }

    public function resolveAll(Request $request)
    {
        $user = $request->user();

        $query = Alerta::where('resuelta', false);

        if ($user->rol === 'auxiliar') {
            $assignedSections = $user->secciones()->pluck('secciones.id')->toArray();
            $query->whereHas('estudiante', function ($q) use ($assignedSections) {
                $q->whereIn('seccion_id', $assignedSections);
            });
        }

        $query->update(['resuelta' => true]);

        return response()->json([
            'message' => 'Todas las alertas aplicables han sido marcadas como resueltas.'
        ]);
    }
}
