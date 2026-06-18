<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('peoplecount_sensors', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('organization_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peoplecount_sensors', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
