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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->timestamp('adult_confirmed_at')->nullable()->after('email_verified_at');
            $table->boolean('is_platform_operator')->default(false);
            $table->foreignId('last_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['last_organization_id']);
            $table->dropColumn(['phone', 'adult_confirmed_at', 'is_platform_operator', 'last_organization_id']);
        });
    }
};
