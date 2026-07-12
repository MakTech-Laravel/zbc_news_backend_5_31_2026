<?php

namespace Tests\Feature\Categories;

use App\Enums\ArticleCategoryStatus;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['categories.list', 'categories.create', 'categories.update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Categories'],
            );
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'editor', 'guard_name' => 'api'],
        );
        $role->givePermissionTo(['categories.list', 'categories.create', 'categories.update']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('editor');
        $this->admin->givePermissionTo(['categories.list', 'categories.create', 'categories.update']);

        Passport::actingAs($this->admin);
    }

    public function test_public_and_admin_lists_are_ordered_by_sort_order(): void
    {
        $second = ArticleCategory::query()->create([
            'title' => 'Second',
            'slug' => 'second',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 2,
        ]);
        $first = ArticleCategory::query()->create([
            'title' => 'First',
            'slug' => 'first',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 1,
        ]);
        $third = ArticleCategory::query()->create([
            'title' => 'Third',
            'slug' => 'third',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 3,
        ]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.2.id', $third->id)
            ->assertJsonPath('data.0.sort_order', 1);

        $this->getJson('/api/v1/admin/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.2.id', $third->id);
    }

    public function test_reorder_endpoint_renumbers_categories(): void
    {
        $a = ArticleCategory::query()->create([
            'title' => 'A',
            'slug' => 'a',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 1,
        ]);
        $b = ArticleCategory::query()->create([
            'title' => 'B',
            'slug' => 'b',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 2,
        ]);
        $c = ArticleCategory::query()->create([
            'title' => 'C',
            'slug' => 'c',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 3,
        ]);

        $response = $this->postJson('/api/v1/admin/categories/reorder', [
            'ids' => [$c->id, $a->id, $b->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $c->id)
            ->assertJsonPath('data.0.sort_order', 1)
            ->assertJsonPath('data.1.id', $a->id)
            ->assertJsonPath('data.1.sort_order', 2)
            ->assertJsonPath('data.2.id', $b->id)
            ->assertJsonPath('data.2.sort_order', 3);

        $this->assertDatabaseHas('article_categories', ['id' => $c->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('article_categories', ['id' => $a->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('article_categories', ['id' => $b->id, 'sort_order' => 3]);
    }

    public function test_reorder_rejects_incomplete_id_list(): void
    {
        $a = ArticleCategory::query()->create([
            'title' => 'A',
            'slug' => 'a',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 1,
        ]);
        ArticleCategory::query()->create([
            'title' => 'B',
            'slug' => 'b',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 2,
        ]);

        $this->postJson('/api/v1/admin/categories/reorder', [
            'ids' => [$a->id],
        ])->assertUnprocessable();
    }

    public function test_new_category_gets_next_sort_order(): void
    {
        ArticleCategory::query()->create([
            'title' => 'Existing',
            'slug' => 'existing',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 3,
        ]);

        $response = $this->postJson('/api/v1/admin/categories/store', [
            'title' => 'New Category',
            'slug' => 'new-category',
            'status' => ArticleCategoryStatus::ACTIVE->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 4);

        $this->assertDatabaseHas('article_categories', [
            'slug' => 'new-category',
            'sort_order' => 4,
        ]);
    }

    public function test_move_to_position_shifts_neighbors(): void
    {
        $a = ArticleCategory::query()->create([
            'title' => 'A',
            'slug' => 'a',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 1,
        ]);
        $b = ArticleCategory::query()->create([
            'title' => 'B',
            'slug' => 'b',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 2,
        ]);
        $c = ArticleCategory::query()->create([
            'title' => 'C',
            'slug' => 'c',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 3,
        ]);

        $response = $this->postJson('/api/v1/admin/categories/move/'.$c->slug, [
            'position' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $c->id)
            ->assertJsonPath('data.0.sort_order', 1)
            ->assertJsonPath('data.1.id', $a->id)
            ->assertJsonPath('data.1.sort_order', 2)
            ->assertJsonPath('data.2.id', $b->id)
            ->assertJsonPath('data.2.sort_order', 3);
    }

    public function test_create_and_list_expose_is_featured(): void
    {
        $response = $this->postJson('/api/v1/admin/categories/store', [
            'title' => 'Featured Cat',
            'slug' => 'featured-cat',
            'status' => ArticleCategoryStatus::ACTIVE->value,
            'is_featured' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.is_featured', true);

        $this->assertDatabaseHas('article_categories', [
            'slug' => 'featured-cat',
            'is_featured' => true,
        ]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.is_featured', true);
    }
}
