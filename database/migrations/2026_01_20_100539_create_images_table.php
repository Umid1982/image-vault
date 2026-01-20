<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path')->comment('Путь к файлу в системе хранения');
            $table->string('original_name')->comment('Оригинальное имя файла');
            $table->string('mime', 50)->comment('MIME-тип (image/jpeg, image/png)');
            $table->unsignedBigInteger('size')->comment('Размер файла в байтах');
            $table->string('hash')->nullable()->unique()->comment('Хэш содержимого файла для предотвращения дубликатов');
            $table->timestamps();
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
