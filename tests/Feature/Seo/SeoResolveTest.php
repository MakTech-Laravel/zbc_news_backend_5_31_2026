<?php

namespace Tests\Feature\Seo;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\SeoPage;
use App\Models\SiteSettings;
use App\Models\User;
use App\Models\UserInformation;
use Database\Seeders\SeoPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeoResolveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['app.frontend_url' => 'https://news.example']);

        $this->seedTemplates();
    }

    private function seedTemplates(): void
    {
        $rows = [
            ['page_key' => 'home', 'name' => 'Home', 'url_path' => '/', 'is_template' => false,
                'meta_title' => 'ZBC News — Home', 'meta_description' => 'Latest headlines.', 'meta_keywords' => 'news, home'],
            ['page_key' => 'category', 'name' => 'Category (template)', 'url_path' => '/:categorySlug', 'is_template' => true,
                'meta_title' => '{category} News', 'meta_description' => 'Browse the latest {category} stories.', 'meta_keywords' => '{category}, news'],
            ['page_key' => 'article-detail', 'name' => 'Article detail (template)', 'url_path' => '/news-details/:articleSlug', 'is_template' => true,
                'meta_title' => '{title} — ZBC News', 'meta_description' => '{excerpt}', 'meta_keywords' => '{tags}, news'],
            ['page_key' => 'author-profile', 'name' => 'Author profile (template)', 'url_path' => '/author/:authorSlug', 'is_template' => true,
                'meta_title' => '{author} — ZBC News', 'meta_description' => '{bio}', 'meta_keywords' => '{author}, author'],
        ];

        foreach ($rows as $row) {
            SeoPage::query()->create($row);
        }
    }

    private function makeAuthor(string $name, string $slug, ?string $bio = null): User
    {
        $author = User::factory()->create(['name' => $name, 'slug' => $slug]);
        UserInformation::query()->create(['user_id' => $author->id, 'bio' => $bio]);

        return $author;
    }

    private function makeCategory(string $title, string $slug, array $overrides = []): ArticleCategory
    {
        return ArticleCategory::query()->create(array_merge([
            'title' => $title,
            'slug' => $slug,
            'status' => ArticleCategoryStatus::ACTIVE,
        ], $overrides));
    }

    private function makeArticle(array $attrs): Article
    {
        return Article::query()->create(array_merge([
            'article_description' => 'Body content.',
            'status' => ArticleStatus::PUBLISHED,
            'published_at' => '2026-03-15 10:00:00',
        ], $attrs));
    }

    private function resolve(string $path)
    {
        return $this->getJson('/api/v1/seo-pages/resolve?path='.urlencode($path));
    }

    public function test_home_resolves_to_home_page_with_website_and_organization_jsonld(): void
    {
        $response = $this->resolve('/');

        $response->assertOk()
            ->assertJsonPath('data.matched_entity', 'static')
            ->assertJsonPath('data.page_key', 'home')
            ->assertJsonPath('data.title', 'ZBC News — Home')
            ->assertJsonPath('data.canonical', 'https://news.example/')
            ->assertJsonPath('data.robots', 'index,follow');

        $jsonLd = $response->json('data.json_ld');
        $types = array_column($jsonLd, '@type');
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
    }

    public function test_article_slug_resolves_to_article_with_entity_meta_and_newsarticle_jsonld(): void
    {
        $author = $this->makeAuthor('Sarah Johnson', 'sarah-johnson', 'Award-winning journalist.');
        $category = $this->makeCategory('Business', 'business-category');

        $this->makeArticle([
            'title' => 'Markets Rally Today',
            'slug' => 'markets-rally-today',
            'meta_title' => 'Markets Rally — Custom Title',
            'meta_description' => 'Custom article description.',
            'meta_keywords' => 'markets, rally',
            'open_graph_image' => 'https://cdn.example/og.jpg',
            'user_id' => $author->id,
            'article_category_id' => $category->id,
        ]);

        $response = $this->resolve('/markets-rally-today');

        $response->assertOk()
            ->assertJsonPath('data.matched_entity', 'article')
            ->assertJsonPath('data.page_key', 'article-detail')
            ->assertJsonPath('data.title', 'Markets Rally — Custom Title')       // raw entity meta wins
            ->assertJsonPath('data.description', 'Custom article description.')
            ->assertJsonPath('data.canonical', 'https://news.example/markets-rally-today')
            ->assertJsonPath('data.og.type', 'article')
            ->assertJsonPath('data.og.image', 'https://cdn.example/og.jpg');

        $article = $response->json('data.json_ld.0');
        $this->assertSame('NewsArticle', $article['@type']);
        $this->assertSame('Markets Rally Today', $article['headline']);
        $this->assertSame('Business', $article['articleSection']);
        $this->assertSame('https://news.example/author/sarah-johnson', $article['author']['url']);
        $this->assertSame('Organization', $article['publisher']['@type']);
        $this->assertTrue($article['isAccessibleForFree']);
    }

    public function test_article_wins_over_category_when_slug_collides(): void
    {
        // A category and a published article share the slug "business".
        $this->makeCategory('Business', 'business');
        $author = $this->makeAuthor('Reporter One', 'reporter-one');
        $this->makeArticle([
            'title' => 'Business Slug Article',
            'slug' => 'business',
            'user_id' => $author->id,
            'article_category_id' => $this->makeCategory('Other', 'other')->id,
        ]);

        $this->resolve('/business')
            ->assertOk()
            ->assertJsonPath('data.matched_entity', 'article');
    }

    public function test_category_slug_interpolates_category_token(): void
    {
        $this->makeCategory('Technology', 'technology'); // no entity meta -> template applies

        $this->resolve('/technology')
            ->assertOk()
            ->assertJsonPath('data.matched_entity', 'category')
            ->assertJsonPath('data.title', 'Technology News')
            ->assertJsonPath('data.description', 'Browse the latest Technology stories.')
            ->assertJsonPath('data.keywords', 'Technology, news');
    }

    public function test_category_entity_meta_overrides_template(): void
    {
        $this->makeCategory('Sports', 'sports', [
            'meta_title' => 'Sports Hub',
            'meta_description' => 'All sports coverage.',
        ]);

        $this->resolve('/sports')
            ->assertOk()
            ->assertJsonPath('data.title', 'Sports Hub')
            ->assertJsonPath('data.description', 'All sports coverage.');
    }

    public function test_author_profile_interpolates_author_token(): void
    {
        $this->makeAuthor('Jane Doe', 'jane-doe', 'Covers politics.');

        $response = $this->resolve('/author/jane-doe');

        $response->assertOk()
            ->assertJsonPath('data.matched_entity', 'author')
            ->assertJsonPath('data.title', 'Jane Doe — ZBC News')
            ->assertJsonPath('data.description', 'Covers politics.')
            ->assertJsonPath('data.canonical', 'https://news.example/author/jane-doe');

        // No unresolved placeholders should ever leak to the client.
        $this->assertStringNotContainsString('{', (string) $response->json('data.title'));
    }

    public function test_headline_truncated_to_110_chars_with_ellipsis(): void
    {
        $author = $this->makeAuthor('Long Writer', 'long-writer');
        $longTitle = 'This is an extremely long news headline that goes well beyond the one hundred and ten character limit imposed by Google News for structured data headlines';
        $this->assertGreaterThan(110, mb_strlen($longTitle));

        $this->makeArticle([
            'title' => $longTitle,
            'slug' => 'long-headline-article',
            'user_id' => $author->id,
            'article_category_id' => $this->makeCategory('News', 'news-cat')->id,
        ]);

        $headline = $this->resolve('/long-headline-article')->json('data.json_ld.0.headline');

        $this->assertLessThanOrEqual(110, mb_strlen($headline));
        $this->assertStringEndsWith('…', $headline);
    }

    public function test_short_headline_is_not_truncated(): void
    {
        $author = $this->makeAuthor('Short Writer', 'short-writer');
        $this->makeArticle([
            'title' => 'Short Headline',
            'slug' => 'short-headline-article',
            'user_id' => $author->id,
            'article_category_id' => $this->makeCategory('News2', 'news-cat-2')->id,
        ]);

        $headline = $this->resolve('/short-headline-article')->json('data.json_ld.0.headline');

        $this->assertSame('Short Headline', $headline);
    }

    public function test_article_dates_are_iso8601_with_timezone_offset(): void
    {
        $author = $this->makeAuthor('Date Writer', 'date-writer');
        $this->makeArticle([
            'title' => 'Dated Article',
            'slug' => 'dated-article',
            'user_id' => $author->id,
            'article_category_id' => $this->makeCategory('News3', 'news-cat-3')->id,
            'published_at' => '2026-03-15 10:00:00',
        ]);

        $jsonLd = $this->resolve('/dated-article')->json('data.json_ld.0');

        $iso = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/';
        $this->assertMatchesRegularExpression($iso, $jsonLd['datePublished']);
        $this->assertMatchesRegularExpression($iso, $jsonLd['dateModified']);
    }

    public function test_article_without_published_date_omits_datepublished(): void
    {
        $author = $this->makeAuthor('Null Date Writer', 'null-date-writer');
        // status draft would 404; use published article but null published_at.
        Article::query()->create([
            'title' => 'No Date Article',
            'slug' => 'no-date-article',
            'article_description' => 'Body.',
            'status' => ArticleStatus::PUBLISHED,
            'published_at' => null,
            'user_id' => $author->id,
            'article_category_id' => $this->makeCategory('News4', 'news-cat-4')->id,
        ]);

        $jsonLd = $this->resolve('/no-date-article')->json('data.json_ld.0');

        $this->assertArrayNotHasKey('datePublished', $jsonLd);
    }

    public function test_noindex_template_sets_robots_noindex(): void
    {
        SeoPage::query()->where('page_key', 'article-detail')->update(['noindex' => true]);

        $author = $this->makeAuthor('Noindex Writer', 'noindex-writer');
        $this->makeArticle([
            'title' => 'Hidden Article',
            'slug' => 'hidden-article',
            'user_id' => $author->id,
            'article_category_id' => $this->makeCategory('NoIdx', 'no-idx')->id,
        ]);

        $this->resolve('/hidden-article')
            ->assertOk()
            ->assertJsonPath('data.robots', 'noindex,nofollow');
    }

    public function test_canonical_url_override_on_static_row_is_respected(): void
    {
        SeoPage::query()->create([
            'page_key' => 'about',
            'name' => 'About',
            'url_path' => '/about',
            'is_template' => false,
            'meta_title' => 'About — ZBC News',
            'canonical_url' => 'https://news.example/about-us',
            'noindex' => true,
        ]);

        $this->resolve('/about')
            ->assertOk()
            ->assertJsonPath('data.canonical', 'https://news.example/about-us')
            ->assertJsonPath('data.robots', 'noindex,nofollow');
    }

    public function test_omits_og_image_and_uses_summary_card_when_no_image_available(): void
    {
        // No site_settings row (default has null site_logo) and no configured default.
        config(['app.og_default_image' => null]);

        $this->resolve('/')
            ->assertOk()
            ->assertJsonMissingPath('data.og.image')
            ->assertJsonMissingPath('data.twitter.image')
            ->assertJsonPath('data.twitter.card', 'summary');
    }

    public function test_config_default_og_image_used_when_no_site_logo(): void
    {
        config(['app.og_default_image' => 'https://cdn.example/default-og.png']);

        $this->resolve('/')
            ->assertOk()
            ->assertJsonPath('data.og.image', 'https://cdn.example/default-og.png')
            ->assertJsonPath('data.twitter.card', 'summary_large_image');
    }

    public function test_og_image_override_on_static_row_drives_social_image(): void
    {
        SeoPage::query()->create([
            'page_key' => 'contact',
            'name' => 'Contact',
            'url_path' => '/contact',
            'is_template' => false,
            'meta_title' => 'Contact — ZBC News',
            'og_image' => 'https://cdn.example/contact-og.jpg',
        ]);

        $this->resolve('/contact')
            ->assertOk()
            ->assertJsonPath('data.og.image', 'https://cdn.example/contact-og.jpg')
            ->assertJsonPath('data.twitter.image', 'https://cdn.example/contact-og.jpg');
    }

    public function test_static_page_falls_back_to_site_defaults_when_template_and_settings_empty(): void
    {
        // No seeded row for /about, no site settings row -> default site name/tag.
        $this->resolve('/about')
            ->assertOk()
            ->assertJsonPath('data.matched_entity', 'static')
            ->assertJsonPath('data.title', 'ZBC News')
            ->assertJsonPath('data.description', 'Breaking news and analysis from around the world');
    }

    public function test_article_keywords_have_no_dangling_separator_when_tags_empty(): void
    {
        $author = $this->makeAuthor('Kw Writer', 'kw-writer');
        // No raw meta_keywords and no tags -> template "{tags}, news" must not yield ", news".
        $this->makeArticle([
            'title' => 'Keywordless Article',
            'slug' => 'keywordless-article',
            'user_id' => $author->id,
            'article_category_id' => $this->makeCategory('KwCat', 'kw-cat')->id,
        ]);

        $response = $this->resolve('/keywordless-article');

        $keywords = (string) $response->json('data.keywords');
        $this->assertSame('news', $keywords);
        $this->assertStringStartsNotWith(',', $keywords);
    }

    public function test_seeded_tag_template_interpolates_tag_token(): void
    {
        $this->seed(SeoPageSeeder::class);

        $this->resolve('/tag/climate-change')
            ->assertOk()
            ->assertJsonPath('data.matched_entity', 'tag')
            ->assertJsonPath('data.page_key', 'tag')
            ->assertJsonPath('data.title', 'Climate Change — ZBC News')
            ->assertJsonPath('data.canonical', 'https://news.example/tag/climate-change');
    }

    public function test_seeded_static_page_resolves_to_its_row(): void
    {
        $this->seed(SeoPageSeeder::class);

        $this->resolve('/about')
            ->assertOk()
            ->assertJsonPath('data.matched_entity', 'static')
            ->assertJsonPath('data.page_key', 'about')
            ->assertJsonPath('data.title', 'About Us — ZBC News');
    }

    public function test_site_settings_meta_used_as_fallback_before_site_name(): void
    {
        SiteSettings::query()->create([
            'site_name' => 'ZBC News',
            'site_tag' => 'Tagline',
            'timezone' => 'UTC',
            'meta_title' => 'Global Meta Title',
            'meta_description' => 'Global meta description.',
        ]);
        Cache::flush();

        // Clear the home template meta so it falls through to site_settings.meta_*.
        SeoPage::query()->where('page_key', 'home')->update([
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $this->resolve('/')
            ->assertOk()
            ->assertJsonPath('data.title', 'Global Meta Title')
            ->assertJsonPath('data.description', 'Global meta description.');
    }
}
