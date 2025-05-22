<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveIsbnFromBooksTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('books', 'isbn')) {
            Schema::table('books', function (Blueprint $table) {
                $table->dropColumn('isbn');
            });
        }
    }

    public function down()
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('isbn')->nullable();
        });
    }
}

