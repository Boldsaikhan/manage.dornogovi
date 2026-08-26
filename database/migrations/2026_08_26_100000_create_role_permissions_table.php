<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ролийн загвар — тухайн албан тушаалын түвшинд ямар модульд ямар эрхтэй байхыг тодорхойлно.
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 32);        // super_admin | department_head | specialist
            $table->string('module_key', 64);
            $table->string('level', 16);       // view | manage
            $table->timestamps();

            $table->unique(['role', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
