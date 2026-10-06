<?php

namespace Tests\Feature\Demo;

use App\Models\DemoEmail;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DemoInboxTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_inbox_does_not_exist_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);

        $response = $this->get(route('demo.inbox.index'));

        $response->assertNotFound();
    }

    public function test_emails_are_not_captured_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        User::factory()->create(['email' => 'ada@example.test']);

        $this->post(route('password.email'), ['email' => 'ada@example.test']);

        $this->assertDatabaseCount(DemoEmail::class, 0);
    }

    public function test_captures_every_email_the_app_sends_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        $this->actingAs($administrator)->post(route('orgs.invitations.store', $organization), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'role' => 'volunteer',
        ]);

        $email = DemoEmail::sole();
        $this->assertSame('grace@example.test', $email->recipients);
        $this->assertSame("You're invited to join Food Bank North", $email->subject);
        $this->assertStringContainsString('Set up your account: '.url('/invitations/'), $email->text_body);
    }

    public function test_inbox_lists_the_newest_emails_first_for_guests(): void
    {
        config(['demo.enabled' => true]);
        DemoEmail::factory()->create(['recipients' => 'first@example.test', 'subject' => 'Older email', 'created_at' => now()->subHour()]);
        DemoEmail::factory()->create(['recipients' => 'second@example.test', 'subject' => 'Newer email']);

        $response = $this->get(route('demo.inbox.index'));

        $response->assertSeeTextInOrder(['Demo inbox', 'second@example.test', 'Newer email', 'first@example.test', 'Older email']);
    }

    public function test_inbox_can_be_narrowed_to_one_recipient(): void
    {
        config(['demo.enabled' => true]);
        DemoEmail::factory()->create(['recipients' => 'grace@example.test', 'subject' => 'For Grace']);
        DemoEmail::factory()->create(['recipients' => 'ada@example.test', 'subject' => 'For Ada']);

        $response = $this->get(route('demo.inbox.index', ['to' => 'GRACE@example.test']));

        $response->assertSeeText('For Grace');
        $response->assertDontSeeText('For Ada');
    }

    public function test_email_page_shows_the_message_with_its_links_as_buttons(): void
    {
        config(['demo.enabled' => true]);
        $email = DemoEmail::factory()->create([
            'recipients' => 'grace@example.test',
            'subject' => "You're invited to join Food Bank North",
            'text_body' => "Grace Admin has invited you to join Food Bank North.\n\nSet up your account: http://localhost/invitations/abc123\n\nThis invitation expires on 12 Oct 2026.",
        ]);

        $response = $this->get(route('demo.inbox.show', $email));

        $response->assertSeeTextInOrder(['grace@example.test', "You're invited to join Food Bank North"]);
        $response->assertSee('href="http://localhost/invitations/abc123"', false);
        $response->assertSeeText('This invitation expires on 12 Oct 2026.');
    }

    public function test_email_page_escapes_the_message(): void
    {
        config(['demo.enabled' => true]);
        $email = DemoEmail::factory()->create([
            'subject' => "<script>alert('subject')</script>",
            'text_body' => "Hello <script>alert('body')</script>",
        ]);

        $response = $this->get(route('demo.inbox.show', $email));

        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('subject')</script>", false);
        $response->assertDontSee("<script>alert('body')</script>", false);
    }

    public function test_keeps_only_the_newest_emails(): void
    {
        config(['demo.enabled' => true, 'demo.inbox_size' => 2]);
        User::factory()->create(['email' => 'ada@example.test']);
        $oldest = DemoEmail::factory()->create(['subject' => 'Oldest']);
        DemoEmail::factory()->create(['subject' => 'Middle']);

        $this->post(route('password.email'), ['email' => 'ada@example.test']);

        $this->assertModelMissing($oldest);
        $this->assertSame(['Middle', 'Reset your password'], DemoEmail::orderBy('id')->pluck('subject')->all());
    }

    public function test_every_page_says_emails_are_not_really_sent_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->get(route('login'));

        $response->assertSeeText('Demo mode: emails are not really sent.');
        $response->assertSee('href="'.route('demo.inbox.index').'"', false);
    }

    public function test_no_demo_banner_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);

        $response = $this->get(route('login'));

        $response->assertDontSeeText('Demo mode');
    }
}
