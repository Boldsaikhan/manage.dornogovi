<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Гэрээ (тайлан) ирүүлэх хугацаа.
 */
class ReportSchedule extends Model
{
    protected $fillable = ['report_key', 'due_on', 'note', 'updated_by'];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
