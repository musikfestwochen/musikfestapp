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
        Schema::table('peoplecount_assignments', function (Blueprint $table) {
            $table->foreignId('sensor_share_id')
                ->nullable()
                ->after('sensor_id')
                ->constrained('peoplecount_sensor_shares')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peoplecount_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sensor_share_id');
        });
    }
};
