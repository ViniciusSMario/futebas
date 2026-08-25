<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('slug', 40)->nullable()->unique()->after('id');
            $table->text('description')->nullable()->after('positions');
            $table->boolean('requires_approval')->default(true)->after('status');
        });

        DB::table('games')->whereNull('slug')->select('id')->orderBy('id')->get()->each(function ($row) {
            do {
                $slug = Str::lower(Str::random(8));
            } while (DB::table('games')->where('slug', $slug)->exists());

            DB::table('games')->where('id', $row->id)->update(['slug' => $slug]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description', 'requires_approval']);
        });
    }
};
