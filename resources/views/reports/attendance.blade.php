<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Asistencia</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #2563eb; }
        .info { margin-bottom: 20px; }
        .info table { width: 100%; }
        .stats { margin-bottom: 30px; background: #f8fafc; padding: 15px; border-radius: 8px; }
        .stats table { width: 100%; text-align: center; }
        .stats-val { font-size: 18px; font-bold; color: #1e40af; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f1f5f9; color: #475569; text-align: left; padding: 8px; border: 1px solid #e2e8f0; }
        td { padding: 8px; border: 1px solid #e2e8f0; }
        .estado-presente { color: #16a34a; font-weight: bold; }
        .estado-tardanza { color: #d97706; font-weight: bold; }
        .estado-falta { color: #dc2626; font-weight: bold; }
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #94a3b8; }
        .signature { margin-top: 80px; width: 100%; }
        .signature td { border: none; text-align: center; }
        .signature-line { border-top: 1px solid #333; width: 200px; margin: 0 auto; margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE ASISTENCIA</h1>
        <p>Sistema de Gestión Escolar - ASIST</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td><strong>Rango de Fechas:</strong> {{ $fechaInicio }} al {{ $fechaFin }}</td>
                <td style="text-align: right;"><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td><strong>Sección:</strong> {{ $seccionNombre ?? 'Todas mis secciones' }}</td>
                <td style="text-align: right;"><strong>Usuario:</strong> {{ $userName }}</td>
            </tr>
        </table>
    </div>

    <div class="stats">
        <table>
            <tr>
                <td>
                    <div class="stats-val">{{ $stats['presente'] }}</div>
                    <div>Presentes</div>
                </td>
                <td>
                    <div class="stats-val">{{ $stats['tardanza_justificada'] + $stats['tardanza_injustificada'] }}</div>
                    <div>Tardanzas</div>
                </td>
                <td>
                    <div class="stats-val">{{ $stats['falta_justificada'] + $stats['falta_injustificada'] }}</div>
                    <div>Faltas</div>
                </td>
                <td>
                    <div class="stats-val">{{ $stats['total'] }}</div>
                    <div>Total</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Estudiante</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Just.</th>
                <th>Sección / Grado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($asistencias as $asistencia)
            <tr>
                <td>{{ $asistencia->nombre_completo }}</td>
                <td>{{ $asistencia->fecha }}</td>
                <td class="estado-{{ $asistencia->estado }}">{{ ucfirst($asistencia->estado) }}</td>
                <td>{{ $asistencia->justificacion_id ? 'Sí' : 'No' }}</td>
                <td>{{ $asistencia->seccion_nombre }} ({{ $asistencia->grado_nombre }})</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signature">
        <tr>
            <td>
                <div class="signature-line"></div>
                Firma del Auxiliar
            </td>
            <td>
                <div class="signature-line"></div>
                Sello de Dirección
            </td>
        </tr>
    </table>

    <div class="footer">
        Este documento es un reporte oficial generado por el sistema ASIST.
    </div>
</body>
</html>
