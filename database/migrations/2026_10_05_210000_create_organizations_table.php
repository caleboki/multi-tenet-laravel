<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Organization names must be unique without regard to case among organizations
     * that have not been rejected (FR-007). The schema builder has no API for a
     * partial expression index, so that index is created with raw SQL. Rejected
     * organizations are excluded so their names become available again.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('contact_email');
            $table->string('status')->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('requested_by_id')->constrained('users');
            $table->string('signup_token', 64)->nullable()->unique();
            $table->boolean('self_signup_enabled')->default(true);
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX organizations_name_lower_unique ON organizations (lower(name)) WHERE status <> 'rejected'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
