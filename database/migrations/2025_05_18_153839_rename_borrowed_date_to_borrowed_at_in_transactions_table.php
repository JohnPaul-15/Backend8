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
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'borrowed_date')) {
                $table->renameColumn('borrowed_date', 'borrowed_at');
            }

            if (Schema::hasColumn('transactions', 'returned_date')) {
                $table->renameColumn('returned_date', 'returned_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'borrowed_at')) {
                $table->renameColumn('borrowed_at', 'borrowed_date');
            }

            if (Schema::hasColumn('transactions', 'returned_at')) {
                $table->renameColumn('returned_at', 'returned_date');
            }
        });
    }
};
