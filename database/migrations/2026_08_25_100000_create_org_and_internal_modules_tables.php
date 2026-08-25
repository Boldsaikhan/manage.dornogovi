<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->nullable()->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('is_admin')->constrained()->nullOnDelete();
            $table->string('position')->nullable()->after('department_id');
            $table->boolean('is_department_head')->default(false)->after('position');
        });

        Schema::create('user_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->string('level', 16)->default('view'); // view|manage
            $table->timestamps();
            $table->unique(['user_id', 'module_key']);
        });

        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->default('chuluu'); // chuluu|amralt|busad
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 24)->default('pending'); // pending|approved|rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('travel_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destination');
            $table->string('purpose')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('order_number')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_leader_records', function (Blueprint $table) {
            $table->id();
            $table->string('person_name');
            $table->string('agency')->nullable();
            $table->string('type', 32); // tomilolt|chuluu|amralt
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('destination')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('regulations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable();
            $table->longText('body')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('decrees', function (Blueprint $table) {
            $table->id();
            // zahiramj_a, zahiramj_b, tushaal_a, tushaal_b
            $table->string('kind', 32);
            $table->string('blank_number')->nullable();
            $table->string('number')->nullable();
            $table->string('title');
            $table->date('issued_on')->nullable();
            $table->longText('body')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('title');
            $table->string('counterparty')->nullable();
            $table->date('issued_on');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('archives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('file_path')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_standards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('period')->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('held_at')->nullable();
            $table->json('attendees')->nullable();
            $table->longText('minutes')->nullable();
            $table->longText('transcript')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });

        Schema::create('work_group_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('owner')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status', 24)->default('open');
            $table->date('due_on')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period')->nullable();
            $table->longText('body')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('file_path')->nullable();
            $table->boolean('for_new_hires')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16); // user|assistant|system
            $table->longText('content');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // Анхны хэлтсүүд
        $now = now();
        DB::table('departments')->insert([
            ['name' => 'Засаг даргын Тамгын газар', 'code' => 'ZDTG', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Хууль, эрх зүйн хэлтэс', 'code' => 'HEZ', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Санхүү, төсвийн хэлтэс', 'code' => 'ST', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Нийгмийн бодлогын хэлтэс', 'code' => 'NB', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Хөрөнгө оруулалт, хөгжлийн хэлтэс', 'code' => 'HOH', 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('trainings');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('work_group_tasks');
        Schema::dropIfExists('work_groups');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('document_standards');
        Schema::dropIfExists('archives');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('decrees');
        Schema::dropIfExists('regulations');
        Schema::dropIfExists('agency_leader_records');
        Schema::dropIfExists('travel_assignments');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('user_module_permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['position', 'is_department_head']);
        });

        Schema::dropIfExists('departments');
    }
};
