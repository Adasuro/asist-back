<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Grados
        Schema::create('grados', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->integer('nivel')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Secciones
        Schema::create('secciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('grado_id')->constrained('grados')->onDelete('cascade');
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->unique(['grado_id', 'nombre']);
            $table->timestamps();
        });

        // 3. Auxiliar - Secciones (Relación)
        Schema::create('auxiliar_secciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->foreignUuid('seccion_id')->constrained('secciones')->onDelete('cascade');
            $table->boolean('activo')->default(true);
            $table->unique(['usuario_id', 'seccion_id']);
            $table->timestamps();
        });

        // 4. Estudiantes
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre_completo');
            $table->string('dni')->unique();
            $table->string('codigo_sistema')->unique()->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->foreignUuid('seccion_id')->constrained('secciones');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiantes');
        Schema::dropIfExists('auxiliar_secciones');
        Schema::dropIfExists('secciones');
        Schema::dropIfExists('grados');
    }
};
