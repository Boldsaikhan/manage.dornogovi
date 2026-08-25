<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentStandard extends Model
{
    protected $fillable = [
        'title', 'body', 'file_path', 'sort_order',
    ];
}
