<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personal') && !Schema::hasColumn('personal', 'firma')) {
            Schema::table('personal', function (Blueprint $table) {
                $table->string('firma', 255)->nullable()->after('cargo_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('personal') && Schema::hasColumn('personal', 'firma')) {
            Schema::table('personal', function (Blueprint $table) {
                $table->dropColumn('firma');
            });
        }
    }
};
