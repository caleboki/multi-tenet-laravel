<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class RosterPerformanceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Total memberships across all organizations (SC-008).
     */
    private const MEMBERSHIPS = 50_000;

    /**
     * Number of organizations (SC-008).
     */
    private const ORGANIZATIONS = 500;

    /**
     * Members of the largest organization, whose roster SC-007 times.
     */
    private const LARGEST_ROSTER = 1_000;

    /**
     * People shared across the organizations. Each organization draws its members from this
     * pool, so one person belongs to many organizations, as in real use.
     */
    private const PEOPLE = 2_000;

    /**
     * Seed the volume the performance criteria are measured at (SC-007, SC-008).
     *
     * Local only: it is not called from DatabaseSeeder. Run it with
     * `./vendor/bin/sail artisan db:seed --class=RosterPerformanceSeeder`, then sign in as
     * perf-admin@example.test (password "password") and open Performance Org 1, which has
     * 1,000 members. Rows are written with bulk inserts, so seeding takes seconds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('RosterPerformanceSeeder only runs outside production.');

            return;
        }

        $administrator = User::factory()->create(['name' => 'Performance Admin', 'email' => 'perf-admin@example.test']);
        $people = $this->createPeople();

        $organizations = Organization::factory()
            ->count(self::ORGANIZATIONS)
            ->active()
            ->for($administrator, 'requester')
            ->sequence(fn (Sequence $sequence): array => [
                'name' => 'Performance Org '.($sequence->index + 1),
                'slug' => 'performance-org-'.($sequence->index + 1),
            ])
            ->create();

        foreach ($organizations->values() as $index => $organization) {
            $this->insertMemberships($organization, $index === 0 ? $administrator->id : null, $people, $this->rosterSize($index), $index * 37);
        }
    }

    /**
     * Create the shared pool of people with one bulk insert per thousand rows.
     *
     * @return list<int>
     */
    private function createPeople(): array
    {
        $rows = User::factory()
            ->count(self::PEOPLE)
            ->sequence(fn (Sequence $sequence): array => ['email' => 'perf-person-'.($sequence->index + 1).'@example.test'])
            ->make()
            ->map(fn (User $user): array => [...$user->getAttributes(), 'created_at' => now(), 'updated_at' => now()]);

        foreach ($rows->chunk(1_000) as $chunk) {
            User::query()->insert($chunk->values()->all());
        }

        return User::query()->where('email', 'like', 'perf-person-%')->pluck('id')->all();
    }

    /**
     * Give the first organization 1,000 members and spread the rest of the 50,000 evenly.
     */
    private function rosterSize(int $index): int
    {
        if ($index === 0) {
            return self::LARGEST_ROSTER;
        }

        $remaining = self::MEMBERSHIPS - self::LARGEST_ROSTER;
        $others = self::ORGANIZATIONS - 1;

        return intdiv($remaining, $others) + ($index <= $remaining % $others ? 1 : 0);
    }

    /**
     * Insert one organization's memberships: an active administrator first, then volunteers,
     * one in twenty of them inactive. Members are distinct people drawn from the pool.
     *
     * @param  list<int>  $people
     */
    private function insertMemberships(Organization $organization, ?int $administratorId, array $people, int $size, int $offset): void
    {
        $rows = [];

        for ($position = 0; $position < $size; $position++) {
            $userId = $position === 0 && $administratorId !== null ? $administratorId : $people[($offset + $position) % count($people)];
            $factory = Membership::factory();
            $factory = $position === 0 ? $factory->administrator()->active() : $factory->volunteer();
            $factory = $position > 0 && $position % 20 === 0 ? $factory->inactive() : $factory->active();

            $rows[] = [
                ...$factory->raw(['organization_id' => $organization->id, 'user_id' => $userId]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 1_000) as $chunk) {
            Membership::query()->insert($chunk);
        }
    }
}
