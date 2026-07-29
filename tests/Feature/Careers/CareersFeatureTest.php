<?php

namespace Tests\Feature\Careers;

use App\Enums\CareerApplicationStatus;
use App\Enums\CareerEmploymentType;
use App\Enums\CareerJobDepartment;
use App\Enums\CareerJobStatus;
use App\Models\CareerApplication;
use App\Models\CareerJob;
use App\Models\CareersPageSettings;
use App\Models\User;
use Database\Seeders\CareersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CareersFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'careers-page.show',
            'careers-page.update',
            'career-jobs.list',
            'career-jobs.create',
            'career-jobs.show',
            'career-jobs.update',
            'career-jobs.delete',
            'career-jobs.restore',
            'career-jobs.force-delete',
            'career-applications.list',
            'career-applications.show',
            'career-applications.update',
            'career-applications.delete',
            'career-applications.export',
            'career-applications.bulk',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Careers'],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $role->givePermissionTo($permissions);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo($permissions);

        Passport::actingAs($this->admin);
    }

    public function test_public_careers_page_returns_seeded_defaults(): void
    {
        $this->seed(CareersSeeder::class);

        $this->getJson('/api/v1/careers/page')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hero.badge', 'Now Hiring');

        $this->assertDatabaseCount('careers_page_settings', 1);
    }

    public function test_public_lists_only_published_jobs(): void
    {
        CareerJob::query()->create([
            'title' => 'Published Reporter',
            'slug' => 'published-reporter',
            'department' => CareerJobDepartment::EDITORIAL->value,
            'employment_type' => CareerEmploymentType::FULL_TIME->value,
            'location' => 'Remote',
            'status' => CareerJobStatus::PUBLISHED->value,
            'published_at' => now(),
            'sort_order' => 1,
        ]);

        CareerJob::query()->create([
            'title' => 'Draft Engineer',
            'slug' => 'draft-engineer',
            'department' => CareerJobDepartment::ENGINEERING->value,
            'employment_type' => CareerEmploymentType::FULL_TIME->value,
            'location' => 'Remote',
            'status' => CareerJobStatus::DRAFT->value,
            'sort_order' => 2,
        ]);

        $this->getJson('/api/v1/careers/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Published Reporter');
    }

    public function test_admin_can_update_page_content(): void
    {
        $defaults = CareersSeeder::defaultPageContent();
        $defaults['hero']['badge'] = 'Hiring Now';

        $this->putJson('/api/v1/admin/careers/page', $defaults)
            ->assertOk()
            ->assertJsonPath('data.hero.badge', 'Hiring Now');

        $this->assertDatabaseHas('careers_page_settings', [
            'id' => CareersPageSettings::query()->value('id'),
        ]);
    }

    public function test_admin_can_create_and_soft_delete_job(): void
    {
        $create = $this->postJson('/api/v1/admin/careers/jobs/store', [
            'title' => 'Newsletter Editor',
            'department' => CareerJobDepartment::AUDIENCE->value,
            'employment_type' => CareerEmploymentType::FULL_TIME->value,
            'location' => 'Remote',
            'status' => CareerJobStatus::PUBLISHED->value,
            'sort_order' => 3,
        ]);

        $create->assertCreated()->assertJsonPath('data.slug', 'newsletter-editor');
        $jobId = $create->json('data.id');

        $this->deleteJson('/api/v1/admin/careers/jobs/delete/'.$jobId)
            ->assertOk();

        $this->assertSoftDeleted('career_jobs', ['id' => $jobId]);

        $this->postJson('/api/v1/admin/careers/jobs/restore/'.$jobId)
            ->assertOk()
            ->assertJsonPath('data.id', $jobId);
    }

    public function test_public_can_apply_with_resume_and_admin_can_manage(): void
    {
        Storage::fake('local');

        $job = CareerJob::query()->create([
            'title' => 'Video Journalist',
            'slug' => 'video-journalist',
            'department' => CareerJobDepartment::MULTIMEDIA->value,
            'employment_type' => CareerEmploymentType::FULL_TIME->value,
            'location' => 'Remote',
            'status' => CareerJobStatus::PUBLISHED->value,
            'published_at' => now(),
            'sort_order' => 1,
        ]);

        $resume = UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf');

        $this->post('/api/v1/careers/applications', [
            'career_job_id' => $job->id,
            'name' => 'Alex Candidate',
            'email' => 'alex@example.com',
            'phone' => '+1234567890',
            'cover_letter' => 'I would love to join the team.',
            'resume' => $resume,
        ], [
            'Accept' => 'application/json',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $application = CareerApplication::query()->first();
        $this->assertNotNull($application);
        $this->assertSame(CareerApplicationStatus::NEW->value, $application->status->value);
        Storage::disk('local')->assertExists($application->resume_path);

        $this->getJson('/api/v1/admin/careers/applications/show/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.status', CareerApplicationStatus::REVIEWED->value);

        $this->putJson('/api/v1/admin/careers/applications/'.$application->id.'/status', [
            'status' => CareerApplicationStatus::SHORTLISTED->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', CareerApplicationStatus::SHORTLISTED->value);

        $this->get('/api/v1/admin/careers/applications/'.$application->id.'/resume')
            ->assertOk();
    }

    public function test_cannot_apply_to_draft_job(): void
    {
        Storage::fake('local');

        $job = CareerJob::query()->create([
            'title' => 'Hidden Role',
            'slug' => 'hidden-role',
            'department' => CareerJobDepartment::EDITORIAL->value,
            'employment_type' => CareerEmploymentType::CONTRACT->value,
            'location' => 'NYC',
            'status' => CareerJobStatus::DRAFT->value,
            'sort_order' => 1,
        ]);

        $resume = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

        $this->post('/api/v1/careers/applications', [
            'career_job_id' => $job->id,
            'name' => 'Alex Candidate',
            'email' => 'alex@example.com',
            'resume' => $resume,
        ], [
            'Accept' => 'application/json',
        ])->assertStatus(422);
    }
}
