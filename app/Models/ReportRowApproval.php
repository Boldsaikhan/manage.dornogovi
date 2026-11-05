<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Тайлангийн мөрийг хэлтсийн дарга баталгаажуулсан бүртгэл.
 */
class ReportRowApproval extends Model
{
    protected $fillable = ['report_key', 'row_index', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return [
            'row_index' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
