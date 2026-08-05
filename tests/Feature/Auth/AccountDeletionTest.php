<?php

namespace Tests\Feature\Auth;

use App\Jobs\PurgeDeletedAccounts;
use App\Jobs\SendAccountDeletionAdminEmailJob;
use App\Jobs\SendAccountDeletionCancelAdminEmailJob;
use App\Jobs\SendAccountDeletionCancelRequestedEmailJob;
use App\Jobs\SendAccountDeletionRequestedEmailJob;
use App\Jobs\SendAccountRestoredAdminEmailJob;
use App\Jobs\SendAccountRestoredEmailJob;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AccountDeletionService;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.turnstile.secret' => null, 'services.turnstile.site_key' => null]);

        foreach (['admin', 'super_admin', 'user', 'editor', 'author'] as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        $this->reader = User::factory()->create([
            'email' => 'reader@example.com',
            'password' => Hash::make('Password1!'),
            'email_verified_at' => now(),
        ]);
        $this->reader->assignRole('user');
    }

    public function test_reader_can_request_deletion_with_password_and_confirm(): void
    {
        Bus::fake([
            SendAccountDeletionRequestedEmailJob::class,
            SendAccountDeletionAdminEmailJob::class,
            SendAccountDeletionCancelRequestedEmailJob::class,
            SendAccountDeletionCancelAdminEmailJob::class,
            SendAccountRestoredEmailJob::class,
            SendAccountRestoredAdminEmailJob::class,
        ]);
        Passport::actingAs($this->reader);

        $response = $this->postJson('/api/v1/auth/account/delete', [
            'password' => 'Password1!',
            'confirm' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->reader->refresh();

        $this->assertTrue($this->reader->isPendingDeletion());
        $this->assertNotNull($this->reader->scheduled_permanent_deletion_at);
        $this->assertNotNull($this->reader->deletion_cancel_token);
        $this->assertTrue(
            $this->reader->scheduled_permanent_deletion_at->greaterThan(now()->addDays(29))
        );

        Bus::assertDispatched(SendAccountDeletionRequestedEmailJob::class);
        Bus::assertDispatched(SendAccountDeletionAdminEmailJob::class);
    }

    public function test_deletion_requires_correct_password_and_confirm(): void
    {
        Passport::actingAs($this->reader);

        $this->postJson('/api/v1/auth/account/delete', [
            'password' => 'WrongPassword1!',
            'confirm' => true,
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/account/delete', [
            'password' => 'Password1!',
            'confirm' => false,
        ])->assertStatus(422);

        $this->assertFalse($this->reader->fresh()->isPendingDeletion());
    }

    public function test_staff_cannot_self_delete(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Password1!'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $this->postJson('/api/v1/auth/account/delete', [
            'password' => 'Password1!',
            'confirm' => true,
        ])->assertForbidden();
    }

    public function test_login_blocked_during_grace_period(): void
    {
        Bus::fake([
            SendAccountDeletionRequestedEmailJob::class,
            SendAccountDeletionAdminEmailJob::class,
            SendAccountDeletionCancelRequestedEmailJob::class,
            SendAccountDeletionCancelAdminEmailJob::class,
            SendAccountRestoredEmailJob::class,
            SendAccountRestoredAdminEmailJob::class,
        ]);

        app(AccountDeletionService::class)->requestDeletion($this->reader, 'Password1!');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reader@example.com',
            'password' => 'Password1!',
        ])->assertForbidden()
            ->assertJsonPath('data.account_pending_deletion', true);
    }

    public function test_cancel_request_goes_to_admin_and_does_not_restore_login(): void
    {
        Bus::fake([
            SendAccountDeletionRequestedEmailJob::class,
            SendAccountDeletionAdminEmailJob::class,
            SendAccountDeletionCancelRequestedEmailJob::class,
            SendAccountDeletionCancelAdminEmailJob::class,
            SendAccountRestoredEmailJob::class,
            SendAccountRestoredAdminEmailJob::class,
        ]);

        app(AccountDeletionService::class)->requestDeletion($this->reader, 'Password1!');

        $token = (string) $this->reader->fresh()->deletion_cancel_token;

        $this->postJson('/api/v1/auth/account/cancel-deletion', [
            'token' => $token,
        ])->assertOk();

        $this->reader->refresh();
        $this->assertTrue($this->reader->isPendingDeletion());
        $this->assertTrue($this->reader->hasDeletionCancelRequest());

        Bus::assertDispatched(SendAccountDeletionCancelRequestedEmailJob::class);
        Bus::assertDispatched(SendAccountDeletionCancelAdminEmailJob::class);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reader@example.com',
            'password' => 'Password1!',
        ])->assertForbidden();
    }

    public function test_admin_can_restore_after_cancel_request(): void
    {
        Bus::fake([
            SendAccountDeletionRequestedEmailJob::class,
            SendAccountDeletionAdminEmailJob::class,
            SendAccountDeletionCancelRequestedEmailJob::class,
            SendAccountDeletionCancelAdminEmailJob::class,
            SendAccountRestoredEmailJob::class,
            SendAccountRestoredAdminEmailJob::class,
        ]);

        app(AccountDeletionService::class)->requestDeletion($this->reader, 'Password1!');
        app(AccountDeletionService::class)->requestCancelDeletion(
            (string) $this->reader->fresh()->deletion_cancel_token,
        );

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('super_admin');
        Passport::actingAs($admin);

        $this->postJson('/api/v1/admin/users/restore-deletion/'.$this->reader->id)
            ->assertOk();

        $this->reader->refresh();
        $this->assertFalse($this->reader->isPendingDeletion());
        $this->assertFalse($this->reader->hasDeletionCancelRequest());

        Bus::assertDispatched(SendAccountRestoredEmailJob::class);
        Bus::assertDispatched(SendAccountRestoredAdminEmailJob::class);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->reader->id,
            'title' => 'Account restored',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Account restored',
        ]);

        $this->assertTrue(
            Auth::guard('web')->attempt([
                'email' => 'reader@example.com',
                'password' => 'Password1!',
            ]),
        );
        Auth::guard('web')->logout();
    }

    public function test_admin_can_restore_pending_deletion(): void
    {
        Bus::fake([
            SendAccountDeletionRequestedEmailJob::class,
            SendAccountDeletionAdminEmailJob::class,
            SendAccountDeletionCancelRequestedEmailJob::class,
            SendAccountDeletionCancelAdminEmailJob::class,
            SendAccountRestoredEmailJob::class,
            SendAccountRestoredAdminEmailJob::class,
        ]);

        app(AccountDeletionService::class)->requestDeletion($this->reader, 'Password1!');

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('super_admin');
        Passport::actingAs($admin);

        $this->postJson('/api/v1/admin/users/restore-deletion/'.$this->reader->id)
            ->assertOk();

        $this->assertFalse($this->reader->fresh()->isPendingDeletion());
        Bus::assertDispatched(SendAccountRestoredEmailJob::class);
        Bus::assertDispatched(SendAccountRestoredAdminEmailJob::class);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $this->reader->id)
                ->where('title', 'Account restored')
                ->exists()
        );
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $admin->id)
                ->where('title', 'Account restored')
                ->exists()
        );
    }

    public function test_newsletter_excludes_pending_deletion_users_and_unsubscribes(): void
    {
        Bus::fake([
            SendAccountDeletionRequestedEmailJob::class,
            SendAccountDeletionAdminEmailJob::class,
            SendAccountDeletionCancelRequestedEmailJob::class,
            SendAccountDeletionCancelAdminEmailJob::class,
            SendAccountRestoredEmailJob::class,
            SendAccountRestoredAdminEmailJob::class,
        ]);

        NewsletterSubscriber::query()->create([
            'email' => 'reader@example.com',
            'status' => 'verified',
            'verification_token' => 'verify-token',
            'unsubscribe_token' => 'unsub-token',
            'verified_at' => now(),
        ]);

        Passport::actingAs($this->reader);
        $this->postJson('/api/v1/auth/account/delete', [
            'password' => 'Password1!',
            'confirm' => true,
        ])->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'reader@example.com',
            'status' => 'unsubscribed',
        ]);

        $eligible = app(NewsletterService::class)->countEligibleRecipients(false);
        $this->assertSame(0, $eligible['breakdown']['users']);
    }

    public function test_purge_anonymizes_accounts_after_grace_period(): void
    {
        Bus::fake([
            SendAccountDeletionRequestedEmailJob::class,
            SendAccountDeletionAdminEmailJob::class,
            SendAccountDeletionCancelRequestedEmailJob::class,
            SendAccountDeletionCancelAdminEmailJob::class,
            SendAccountRestoredEmailJob::class,
            SendAccountRestoredAdminEmailJob::class,
        ]);
        Mail::fake();

        app(AccountDeletionService::class)->requestDeletion($this->reader, 'Password1!');

        $this->reader->forceFill([
            'scheduled_permanent_deletion_at' => now()->subMinute(),
        ])->save();

        (new PurgeDeletedAccounts)->handle(app(AccountDeletionService::class));

        $this->reader->refresh();
        $this->assertTrue($this->reader->isPermanentlyDeleted());
        $this->assertSame('Deleted User', $this->reader->name);
        $this->assertSame('deleted+'.$this->reader->id.'@deleted.local', $this->reader->email);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reader@example.com',
            'password' => 'Password1!',
        ])->assertUnauthorized();
    }

    public function test_purge_skips_accounts_with_cancel_request_even_after_grace_period(): void
    {
        Bus::fake([
            SendAccountDeletionRequestedEmailJob::class,
            SendAccountDeletionAdminEmailJob::class,
            SendAccountDeletionCancelRequestedEmailJob::class,
            SendAccountDeletionCancelAdminEmailJob::class,
            SendAccountRestoredEmailJob::class,
            SendAccountRestoredAdminEmailJob::class,
        ]);
        Mail::fake();

        app(AccountDeletionService::class)->requestDeletion($this->reader, 'Password1!');
        app(AccountDeletionService::class)->requestCancelDeletion(
            (string) $this->reader->fresh()->deletion_cancel_token,
        );

        $this->reader->forceFill([
            'scheduled_permanent_deletion_at' => now()->subDays(5),
        ])->save();

        (new PurgeDeletedAccounts)->handle(app(AccountDeletionService::class));

        $this->reader->refresh();
        $this->assertFalse($this->reader->isPermanentlyDeleted());
        $this->assertTrue($this->reader->isPendingDeletion());
        $this->assertTrue($this->reader->hasDeletionCancelRequest());
        $this->assertSame('reader@example.com', $this->reader->email);
    }

    public function test_admin_can_list_pending_account_deletions(): void
    {
        Bus::fake([
            SendAccountDeletionRequestedEmailJob::class,
            SendAccountDeletionAdminEmailJob::class,
            SendAccountDeletionCancelRequestedEmailJob::class,
            SendAccountDeletionCancelAdminEmailJob::class,
            SendAccountRestoredEmailJob::class,
            SendAccountRestoredAdminEmailJob::class,
        ]);

        app(AccountDeletionService::class)->requestDeletion($this->reader, 'Password1!');
        app(AccountDeletionService::class)->requestCancelDeletion(
            (string) $this->reader->fresh()->deletion_cancel_token,
        );

        $active = User::factory()->create([
            'email' => 'active@example.com',
            'email_verified_at' => now(),
        ]);
        $active->assignRole('user');

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('super_admin');
        Passport::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/users/pending-deletions')
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->reader->id, $ids);
        $this->assertNotContains($active->id, $ids);
        $this->assertTrue(
            collect($response->json('data'))
                ->firstWhere('id', $this->reader->id)['has_deletion_cancel_request'] ?? false
        );
    }
}
