<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Asistencias
        Schema::create('asistencias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->foreignUuid('registrado_por')->constrained('usuarios');
            $table->foreignUuid('seccion_id')->constrained('secciones');
            $table->date('fecha')->default(now());
            $table->enum('estado', ['presente', 'tardanza', 'falta']);
            $table->time('hora_llegada')->nullable();
            $table->text('observacion')->nullable();
            $table->enum('metodo_registro', ['codigo', 'manual']);
            $table->unique(['estudiante_id', 'fecha']);
            $table->timestamps();
        });

        // 2. Justificaciones
        Schema::create('justificaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asistencia_id')->unique()->constrained('asistencias')->onDelete('cascade');
            $table->foreignUuid('registrado_por')->constrained('usuarios');
            $table->text('motivo');
            $table->string('documento_url')->nullable();
            $table->date('fecha_presentacion')->default(now());
            $table->timestamps();
        });

        // 3. Alertas
        Schema::create('alertas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->string('tipo'); // 'tardanzas_a_falta' | 'faltas_excesivas'
            $table->text('mensaje');
            $table->boolean('resuelta')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
        Schema::dropIfExists('justificaciones');
        Schema::dropIfExists('asistencias');
    }
};
