<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->date('borrowed_date');
            $table->date('returned_date')->nullable();
            $table->date('due_date');
            $table->enum('status', ['borrowed', 'returned', 'overdue'])->default('borrowed');
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
