<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registro_asistencia_oficial', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seccion_id')->constrained('secciones')->onDelete('cascade');
            $table->date('fecha');
            $table->foreignUuid('oficializado_por')->constrained('usuarios');
            $table->unique(['seccion_id', 'fecha']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registro_asistencia_oficial');
    }
};
