<?php

use App\Models\Peoplecount\Sensor;
use App\Models\User;
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
        Schema::create('peoplecount_sensor_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Sensor::class)->constrained('peoplecount_sensors')->cascadeOnDelete();
            $table->foreignId('owner_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('borrower_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();

            $table->index(['sensor_id', 'borrower_organization_id', 'starts_at', 'ends_at'], 'peoplecount_sensor_shares_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peoplecount_sensor_shares');
    }
};
