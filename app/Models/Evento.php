<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Evento extends Model
{
    use HasFactory;

    protected $table = 'eventos';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'titulo',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'tipo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
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
}
