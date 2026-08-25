<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 14 сумын жагсаалтад албан тушаал / нэр солигдсоныг буцаана.
     * Зөвхөн илэрхий солигдсон мөрийг (нэр баганад тушаал, тушаал баганад овог нэр) засна.
     */
    public function up(): void
    {
        $rows = DB::table('phone_directory_entries')
            ->where(function ($q) {
                $q->where('category', 'sum')
                    ->orWhere('org_name', 'like', '%сум%')
                    ->orWhere('org_name', 'like', '%Сум%');
            })
            ->whereNotNull('position')
            ->where('position', '!=', '')
            ->get(['id', 'person_name', 'position']);

        foreach ($rows as $row) {
            if (! $this->looksSwapped((string) $row->person_name, (string) $row->position)) {
                continue;
            }

            DB::table('phone_directory_entries')
                ->where('id', $row->id)
                ->update([
                    'person_name' => $row->position,
                    'position' => $row->person_name,
                ]);
        }
    }

    public function down(): void
    {
        // Ижил нөхцөлөөр дахин солино.
        $this->up();
    }

    private function looksSwapped(string $personName, string $position): bool
    {
        return $this->looksLikePosition($personName) && $this->looksLikePersonName($position);
    }

    private function looksLikePersonName(string $value): bool
    {
        $value = trim($value);

        return (bool) preg_match('/^[\p{L}]{1,4}\.\s*[\p{L}\-үөёҮӨЁ]+$/u', $value);
    }

    private function looksLikePosition(string $value): bool
    {
        $v = mb_strtolower(trim($value));

        foreach (['дарга', 'нарийн бичиг', 'эрхлэгч', 'мэргэжилтэн', 'нягтлан', 'багийн', 'зовлон', 'итх', 'здтг'] as $needle) {
            if (str_contains($v, $needle)) {
                return true;
            }
        }

        return false;
    }
};
