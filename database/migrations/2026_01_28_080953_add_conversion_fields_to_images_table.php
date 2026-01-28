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
        Schema::table('images', function (Blueprint $table) {
            // Поля для трейсинга конвертации
            $table->string('conversion_status', 20)
                ->nullable()
                ->default(null)
                ->comment('pending, processing, completed, failed, skipped');

            $table->timestamp('converted_at')->nullable();
            $table->timestamp('conversion_failed_at')->nullable();
            $table->timestamp('conversion_skipped_at')->nullable();
            $table->timestamp('conversion_checked_at')->nullable();

            $table->text('conversion_error')->nullable();
            $table->string('conversion_skip_reason', 100)->nullable();

            $table->integer('conversion_attempts')->default(0);
            $table->integer('conversion_quality')->nullable();

            // Для аналитики
            $table->bigInteger('original_size')->nullable()->comment('Size before conversion');
            $table->decimal('compression_ratio', 5, 2)->nullable()->comment('Percentage saved');

            // Индексы для часто используемых запросов
            $table->index(['user_id', 'conversion_status']);
            $table->index(['conversion_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn([
                'conversion_status',
                'converted_at',
                'conversion_failed_at',
                'conversion_skipped_at',
                'conversion_error',
                'conversion_skip_reason',
                'conversion_attempts',
                'conversion_quality',
                'original_size',
                'compression_ratio',
            ]);

            $table->dropIndex(['user_id', 'conversion_status']);
            $table->dropIndex(['conversion_status', 'created_at']);
        });
    }
};
