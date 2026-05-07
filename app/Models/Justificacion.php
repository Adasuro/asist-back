<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Justificacion extends Model
{
    use HasFactory;

    protected $table = 'justificaciones';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'asistencia_id',
        'registrado_por',
        'motivo',
        'documento_url',
        'fecha_presentacion',
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

    public function asistencia()
    {
        return $this->belongsTo(Asistencia::class, 'asistencia_id');
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
