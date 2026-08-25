<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = [
        'title', 'body', 'file_path', 'for_new_hires', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'for_new_hires' => 'boolean',
        ];
    }
}
