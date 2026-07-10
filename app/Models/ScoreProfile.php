<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreProfile extends Model
{
    protected $connection = 'mysql';
    protected $table = 'score_profiles';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rules()
    {
        return $this->hasMany(ScoreProfileRule::class, 'score_profile_id');
    }

    public function convocatorias()
    {
        return $this->hasMany(Convocatoria::class, 'score_profile_id');
    }
}
