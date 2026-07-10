<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RegistroAsistenciaOficial extends Model
{
    use HasFactory;

    protected $table = 'registro_asistencia_oficial';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'seccion_id',
        'fecha',
        'oficializado_por',
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

    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    public function oficializadoBy()
    {
        return $this->belongsTo(User::class, 'oficializado_por');
    }
}
