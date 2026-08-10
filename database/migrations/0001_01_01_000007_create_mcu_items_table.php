<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->string('code', 50)->unique()->index();
            $table->string('name', 255);
            $table->string('category', 100)->nullable()->index();
            $table->string('unit', 30)->nullable();
            $table->string('normal_reference_male', 255)->nullable();
            $table->string('normal_reference_female', 255)->nullable();
            $table->decimal('price', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcu_items');
    }
};
