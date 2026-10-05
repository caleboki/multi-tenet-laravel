<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the local development data described in the feature quickstart.
     *
     * Every account uses the password "password".
     */
    public function run(): void
    {
        User::factory()->operator()->create([
            'name' => 'Platform Operator',
            'email' => 'operator@example.test',
        ]);

        $foodBankAdmin = User::factory()->create(['name' => 'Food Bank Admin', 'email' => 'admin@foodbank.test']);
        $foodBankVolunteer = User::factory()->create(['name' => 'Food Bank Volunteer', 'email' => 'volunteer@foodbank.test']);
        $riverCleanupAdmin = User::factory()->create(['name' => 'River Cleanup Admin', 'email' => 'admin@rivercleanup.test']);
        $memberOfBoth = User::factory()->create(['name' => 'Member Of Both', 'email' => 'both@example.test']);
        $requester = User::factory()->create(['name' => 'Kitchen Requester', 'email' => 'requester@example.test']);

        $foodBank = Organization::factory()->active()->for($foodBankAdmin, 'requester')->create([
            'name' => 'Food Bank North',
            'slug' => 'food-bank-north',
            'contact_email' => 'hello@foodbank.test',
        ]);

        $riverCleanup = Organization::factory()->active()->for($riverCleanupAdmin, 'requester')->create([
            'name' => 'River Cleanup',
            'slug' => 'river-cleanup',
            'contact_email' => 'hello@rivercleanup.test',
        ]);

        Organization::factory()->pending()->for($requester, 'requester')->create([
            'name' => 'Community Kitchen',
            'slug' => 'community-kitchen',
            'contact_email' => 'hello@communitykitchen.test',
        ]);

        Membership::factory()->for($foodBank)->for($foodBankAdmin)->administrator()->active()->create();
        Membership::factory()->for($foodBank)->for($foodBankVolunteer)->volunteer()->active()->create();
        Membership::factory()->for($foodBank)->for($memberOfBoth)->volunteer()->active()->create();

        Membership::factory()->for($riverCleanup)->for($riverCleanupAdmin)->administrator()->active()->create();
        Membership::factory()->for($riverCleanup)->for($memberOfBoth)->administrator()->active()->create();
    }
}
