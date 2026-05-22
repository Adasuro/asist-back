<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Alerta extends Model
{
    use HasFactory;

    protected $table = 'alertas';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'estudiante_id',
        'tipo',
        'mensaje',
        'resuelta',
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
}
