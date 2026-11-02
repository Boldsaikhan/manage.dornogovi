<?php

use App\Models\DocumentStandard;
use App\Support\DocumentStandardCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_standard_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('document_standard_categories')->nullOnDelete();
            $table->string('key', 64)->unique();
            $table->string('label');
            $table->string('summary', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('document_standards', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained('document_standard_categories')->nullOnDelete();
            $table->string('slug', 80)->nullable()->unique()->after('category_id');
            $table->string('code', 40)->nullable()->after('title');
            $table->string('summary', 500)->nullable()->after('code');
            $table->string('source_title')->nullable()->after('body');
            $table->string('source_url', 500)->nullable()->after('source_title');
            $table->string('source_number', 120)->nullable()->after('source_url');
            $table->string('status', 24)->default('in_force')->after('source_number');
            $table->date('effective_on')->nullable()->after('status');
            $table->date('amended_on')->nullable()->after('effective_on');
            $table->date('repealed_on')->nullable()->after('amended_on');
            $table->json('visual')->nullable()->after('repealed_on');
            $table->json('examples')->nullable()->after('visual');
            $table->json('checks')->nullable()->after('examples');
            $table->boolean('is_official')->default(false)->after('checks');
            $table->timestamp('archived_at')->nullable()->after('is_official');
        });

        DocumentStandardCatalog::sync();

        DocumentStandard::query()->whereNull('source_number')->update([
            'status' => DocumentStandard::STATUS_REFERENCED,
            'summary' => 'Өмнө гараар оруулсан тэмдэглэл. Албан эх биш.',
        ]);

        DocumentStandard::query()->whereNull('slug')->orderBy('id')->each(function (DocumentStandard $row) {
            $row->update(['slug' => 'legacy-'.$row->id]);
        });
    }

    public function down(): void
    {
        Schema::table('document_standards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn([
                'slug', 'code', 'summary', 'source_title', 'source_url', 'source_number',
                'status', 'effective_on', 'amended_on', 'repealed_on',
                'visual', 'examples', 'checks', 'is_official', 'archived_at',
            ]);
        });

        Schema::dropIfExists('document_standard_categories');
    }
};
