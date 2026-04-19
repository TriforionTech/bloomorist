<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $table = 'bl_memberships_t';

    protected $fillable = [
        'nama',
        'besaran_diskon_persen',
    ];

    public $timestamps = true;
}
