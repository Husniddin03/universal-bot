<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable()->unique(); // Unikal kod
            $table->bigInteger('message_id')->nullable(); // Kanal message ID
            $table->string('file_id')->nullable(); // Kanal message ID
            $table->enum('status', ['waiting_video', 'waiting_name', 'waiting_code', 'ready'])
                ->default('waiting_video');

            $table->timestamps();

            // Index qo'shish tez qidiruv uchun
            $table->index('code');
        });

        Schema::create('status', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('movies');
        Schema::dropIfExists('status');
    }
};
