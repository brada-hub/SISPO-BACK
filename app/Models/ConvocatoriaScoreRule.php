<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConvocatoriaScoreRule extends Model
{
    protected $connection = 'mysql';
    protected $table = 'convocatoria_score_rules';

    protected $fillable = [
        'convocatoria_id',
        'criterion_code',
        'criterion_name',
        'weight',
        'max_points',
        'is_required',
        'is_enabled',
        'config_json',
    ];

    protected $casts = [
        'weight'      => 'decimal:2',
        'max_points'  => 'decimal:2',
        'is_required' => 'boolean',
        'is_enabled'  => 'boolean',
        'config_json' => 'array',
    ];

    public function convocatoria()
    {
        return $this->belongsTo(Convocatoria::class);
    }
}
