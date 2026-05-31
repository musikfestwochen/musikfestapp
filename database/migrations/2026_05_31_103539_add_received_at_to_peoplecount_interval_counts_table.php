<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('peoplecount_interval_counts', function (Blueprint $table) {
            $table->dateTime('received_at')->nullable()->after('ts_to')->index();
        });

        DB::table('peoplecount_interval_counts')
            ->whereNull('received_at')
            ->update([
                'received_at' => DB::raw('ts_to'),
            ]);

        Schema::table('peoplecount_interval_counts', function (Blueprint $table) {
            $table->dateTime('received_at')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peoplecount_interval_counts', function (Blueprint $table) {
            $table->dropIndex(['received_at']);
            $table->dropColumn('received_at');
        });
    }
};
