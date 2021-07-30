<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModelHasRoles extends Model
{
    use HasFactory, SoftDeletes;

    public function role() {
        return $this->belongsTo('App\Models\Roles', 'role_id', 'id');
    }
}
