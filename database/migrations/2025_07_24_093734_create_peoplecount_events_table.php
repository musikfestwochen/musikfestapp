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
        Schema::create('peoplecount_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignIdFor(Organization::class)->constrained()->cascadeOnDelete();
            // All datetime fields are stored in UTC timezone
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peoplecount_events');
    }
};
