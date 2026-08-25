<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgEmployeePhone extends Model
{
    protected $fillable = [
        'organization',
        'unit',
        'position',
        'last_name',
        'first_name',
        'room',
        'work_phone',
        'mobile_phone',
        'email',
        'sort_order',
    ];
}
