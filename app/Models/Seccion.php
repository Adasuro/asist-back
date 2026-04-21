<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Seccion extends Model
{
    use HasFactory;

    protected $table = 'secciones';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'grado_id',
        'nombre',
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

    public function grado()
    {
        return $this->belongsTo(Grado::class, 'grado_id');
    }

    public function auxiliares()
    {
        return $this->belongsToMany(User::class, 'auxiliar_secciones', 'seccion_id', 'usuario_id');
    }
}
