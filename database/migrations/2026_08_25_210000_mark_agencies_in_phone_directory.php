<?php

use App\Models\PhoneDirectoryEntry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Аймгийн агентлагуудыг (config/agencies.php) утасны жагсаалтад тэмдэглэнэ.
        PhoneDirectoryEntry::query()
            ->select('org_name')
            ->distinct()
            ->pluck('org_name')
            ->each(function (?string $orgName) {
                if (! PhoneDirectoryEntry::isKnownAgency($orgName)) {
                    return;
                }

                PhoneDirectoryEntry::query()
                    ->where('org_name', $orgName)
                    ->update(['category' => 'agentlag']);
            });
    }

    public function down(): void
    {
        // Ангиллыг буцаах шаардлагагүй — гараар засаж болно.
    }
};
