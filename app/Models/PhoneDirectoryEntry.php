<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneDirectoryEntry extends Model
{
    protected $fillable = [
        'org_name', 'org_order', 'sort_order', 'person_name', 'position',
        'office_phone', 'mobile_phone',
    ];
}
