<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_safety_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('stage_safety_sensors')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->double('value');
            $table->string('unit', 16);
            $table->timestamp('observed_at');
            $table->timestamp('received_at');
            $table->unsignedInteger('window_seconds')->nullable();
            $table->boolean('battery_low')->nullable();
            $table->smallInteger('rssi_dbm')->nullable();
            $table->unsignedSmallInteger('cv')->nullable();

            $table->unique(
                ['sensor_id', 'kind', 'observed_at'],
                'stage_safety_reading_identity_unique',
            );
            $table->index(
                ['sensor_id', 'observed_at'],
                'stage_safety_reading_observed_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_safety_readings');
    }
};
