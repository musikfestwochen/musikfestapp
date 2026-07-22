<?php

use App\Models\Organization;
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
        Schema::create('stage_safety_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Organization::class)->constrained()->cascadeOnDelete();
            $table->string('manufacturer');
            $table->string('model');
            $table->string('serial');
            $table->string('name')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('stale_after_seconds')->default(300);
            $table->timestamp('archived_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'manufacturer', 'serial'],
                'stage_safety_sensor_identity_unique',
            );
            $table->index(
                ['organization_id', 'archived_at'],
                'stage_safety_sensor_archive_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_safety_sensors');
    }
};
