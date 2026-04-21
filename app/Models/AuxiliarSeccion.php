<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuxiliarSeccion extends Model
{
    use HasFactory;

    protected $table = 'auxiliar_secciones';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'usuario_id',
        'seccion_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
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

    public function auxiliar()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }
}
