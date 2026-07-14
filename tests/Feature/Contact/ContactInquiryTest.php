<?php

namespace Tests\Feature\Contact;

use App\Enums\ContactInquiryStatus;
use App\Models\ContactInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'api']);
    }

    public function test_public_contact_form_stores_inquiry(): void
    {
        $response = $this->postJson('/api/v1/contact', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'General Inquiry',
            'message' => 'I would like more information about advertising.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('contact_inquiries', [
            'email' => 'jane@example.com',
            'status' => ContactInquiryStatus::NEW->value,
        ]);
    }

    public function test_admin_can_list_contact_inquiries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        ContactInquiry::create([
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'subject' => 'Support',
            'message' => 'Need help with my account.',
            'status' => ContactInquiryStatus::NEW,
        ]);

        Passport::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/contact-inquiries');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_opening_new_message_marks_it_as_read(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $inquiry = ContactInquiry::create([
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'subject' => 'Press',
            'message' => 'Media request.',
            'status' => ContactInquiryStatus::NEW,
        ]);

        Passport::actingAs($admin);

        $this->getJson('/api/v1/admin/contact-inquiries/show/'.$inquiry->id)
            ->assertOk()
            ->assertJsonPath('data.status', ContactInquiryStatus::READ->value);

        $this->assertDatabaseHas('contact_inquiries', [
            'id' => $inquiry->id,
            'status' => ContactInquiryStatus::READ->value,
        ]);
    }
}
