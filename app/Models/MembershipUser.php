<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipUser extends Model
{
    protected $table = 'bl_member_users_t';

    protected $fillable = [
        'nama',
        'alias',
        'email',
        'alamat',
        'kota',
        'provinsi',
        'negara',
        'nomor_hp',
        'membership_id'
    ];

    public $timestamps = true;
}
