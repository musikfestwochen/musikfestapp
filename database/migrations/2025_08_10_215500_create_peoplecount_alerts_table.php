<?php

use App\Enums\Peoplecount\AlertChannel;
use App\Enums\Peoplecount\AlertType;
use App\Models\Peoplecount\Area;
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
        Schema::create('peoplecount_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Area::class)->constrained('peoplecount_areas')->cascadeOnDelete();
            // Backed enums as strings
            $table->string('type'); // AlertType
            $table->string('channel'); // AlertChannel
            $table->unsignedInteger('cooldown_minutes');
            $table->unsignedInteger('occupancy_alert_threshold')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peoplecount_alerts');
    }
};
