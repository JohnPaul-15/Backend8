<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('genre');
            $table->text('description')->nullable();
            $table->integer('total_copies')->default(1);
            $table->integer('available_copies')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('books');
    }
};

