<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sorts *after* the things migration in the package directory (a digit beats a
 * letter) but *before* it alphabetically once the source timestamp is stripped —
 * so the published pair pins that publishing preserves the directory's order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toolbox_gizmos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thing_id')->constrained('toolbox_things');
        });
    }
};
