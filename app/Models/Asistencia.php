<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'asistencias';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'estudiante_id',
        'registrado_por',
        'seccion_id',
        'fecha',
        'estado',
        'hora_llegada',
        'observacion',
        'metodo_registro',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    public function justificacion()
    {
        return $this->hasOne(Justificacion::class, 'asistencia_id');
    }
}
