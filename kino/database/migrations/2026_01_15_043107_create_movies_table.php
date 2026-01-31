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
            $table->string('code')->nullable()->unique();
            $table->bigInteger('message_id')->nullable(); // kanal message_id
            $table->enum('status', ['waiting_video', 'waiting_name', 'ready'])
                ->default('waiting_video');
            $table->timestamps();

            $table->index('code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('movies');
    }
};
