<?php

namespace Tests\Feature\Seo;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\SeoPage;
use App\Models\SiteSettings;
use App\Models\User;
use Database\Seeders\SeoPageSeeder;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['app.frontend_url' => 'https://news.example']);

        SiteSettings::query()->create([
            'site_name' => 'ZBC News',
            'site_tag' => 'Tagline',
            'language' => 'en',
            'timezone' => 'UTC',
        ]);

        $this->author = User::factory()->create(['name' => 'Reporter', 'slug' => 'reporter']);
        $this->category = ArticleCategory::query()->create([
            'title' => 'Business',
            'slug' => 'business',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        // Recent (within 48h) published article.
        $this->recent = Article::query()->create([
            'title' => 'Recent Headline',
            'slug' => 'recent-headline',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $this->author->id,
            'article_category_id' => $this->category->id,
            'published_at' => now()->subHours(2),
        ]);

        // Old (>48h) published article — should be in general but NOT news sitemap.
        $this->old = Article::query()->create([
            'title' => 'Old Headline',
            'slug' => 'old-headline',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $this->author->id,
            'article_category_id' => $this->category->id,
            'published_at' => now()->subDays(10),
        ]);

        $this->seed(SeoPageSeeder::class);
        Cache::flush();
    }

    private User $author;

    private ArticleCategory $category;

    private Article $recent;

    private Article $old;

    private function generalXml(): string
    {
        return $this->get('/sitemap.xml')->assertOk()->getContent();
    }

    private function newsXml(): string
    {
        return $this->get('/news-sitemap.xml')->assertOk()->getContent();
    }

    public function test_general_sitemap_includes_public_article_category_author_tag_and_static(): void
    {
        $xml = $this->generalXml();

        $this->assertStringContainsString('<loc>https://news.example/recent-headline</loc>', $xml);
        $this->assertStringContainsString('<loc>https://news.example/old-headline</loc>', $xml);
        $this->assertStringContainsString('<loc>https://news.example/business</loc>', $xml);
        $this->assertStringContainsString('<loc>https://news.example/author/reporter</loc>', $xml);
        $this->assertStringContainsString('<loc>https://news.example/about</loc>', $xml);
        $this->assertStringContainsString('<loc>https://news.example/</loc>', $xml);
    }

    public function test_general_sitemap_excludes_noindex_page(): void
    {
        SeoPage::query()->where('page_key', 'careers')->update(['noindex' => true]);
        Cache::flush();

        $xml = $this->generalXml();

        $this->assertStringNotContainsString('https://news.example/careers', $xml);
        $this->assertStringContainsString('https://news.example/about', $xml); // sibling still present
    }

    public function test_noindex_exclusion_is_consistent_with_resolver(): void
    {
        // A page excluded from the sitemap must also resolve to noindex — proving
        // the sitemap's exclusion rule matches SeoResolverService, not a second list.
        SeoPage::query()->where('page_key', 'careers')->update(['noindex' => true]);
        Cache::flush();

        $this->assertStringNotContainsString('https://news.example/careers', $this->generalXml());

        $this->getJson('/api/v1/seo-pages/resolve?path=/careers')
            ->assertOk()
            ->assertJsonPath('data.robots', 'noindex,nofollow');
    }

    public function test_lastmod_uses_entity_updated_at(): void
    {
        $this->recent->forceFill(['updated_at' => '2026-01-02 03:04:05'])->saveQuietly();
        Cache::flush();

        $this->assertStringContainsString('2026-01-02T03:04:05+00:00', $this->generalXml());
    }

    public function test_general_sitemap_is_well_formed_and_valid_against_sitemaps_schema(): void
    {
        $xml = $this->generalXml();

        libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $this->assertTrue($doc->loadXML($xml), 'general sitemap is not well-formed XML');

        $xsd = @file_get_contents('https://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');
        if ($xsd === false) {
            $this->markTestSkipped('Could not fetch sitemap.org XSD (no network).');
        }

        $this->assertTrue($doc->schemaValidateSource($xsd), 'general sitemap fails sitemap.org XSD');
    }

    public function test_news_sitemap_only_contains_articles_within_48h(): void
    {
        $xml = $this->newsXml();

        $this->assertStringContainsString('https://news.example/recent-headline', $xml);
        $this->assertStringNotContainsString('old-headline', $xml);
        $this->assertStringContainsString('<news:name>ZBC News</news:name>', $xml);
        $this->assertStringContainsString('<news:language>en</news:language>', $xml);
    }

    public function test_news_sitemap_validates_against_google_news_schema(): void
    {
        $xml = $this->newsXml();

        libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $this->assertTrue($doc->loadXML($xml), 'news sitemap is not well-formed XML');

        // Fetch Google's official news XSD; skip only if the network is unavailable.
        $probe = @file_get_contents('https://www.google.com/schemas/sitemap-news/0.9/sitemap-news.xsd');
        if ($probe === false) {
            $this->markTestSkipped('Could not fetch Google News XSD (no network).');
        }

        // urlset root lives in the sitemaps namespace and imports the official
        // Google News schema for the news:news element.
        $combined = <<<'XSD'
<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
    targetNamespace="http://www.sitemaps.org/schemas/sitemap/0.9"
    elementFormDefault="qualified">
  <xsd:import namespace="http://www.google.com/schemas/sitemap-news/0.9"
      schemaLocation="https://www.google.com/schemas/sitemap-news/0.9/sitemap-news.xsd"/>
  <xsd:element name="urlset">
    <xsd:complexType>
      <xsd:sequence>
        <xsd:element name="url" maxOccurs="unbounded">
          <xsd:complexType>
            <xsd:sequence>
              <xsd:element name="loc" type="xsd:anyURI"/>
              <xsd:element ref="news:news" minOccurs="0"/>
              <xsd:element name="lastmod" type="xsd:string" minOccurs="0"/>
            </xsd:sequence>
          </xsd:complexType>
        </xsd:element>
      </xsd:sequence>
    </xsd:complexType>
  </xsd:element>
</xsd:schema>
XSD;

        $this->assertTrue($doc->schemaValidateSource($combined), 'news sitemap fails Google News XSD');
    }

    public function test_news_publication_date_equals_resolve_jsonld_date_published(): void
    {
        // The <news:publication_date> for an article must equal, byte-for-byte,
        // the NewsArticle JSON-LD datePublished from the resolve endpoint.
        $newsXml = $this->newsXml();

        $doc = new DOMDocument;
        $doc->loadXML($newsXml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('news', 'http://www.google.com/schemas/sitemap-news/0.9');

        $node = $xpath->query('//sm:url[sm:loc="https://news.example/recent-headline"]/news:news/news:publication_date')->item(0);
        $this->assertNotNull($node, 'recent article missing from news sitemap');
        $sitemapDate = $node->textContent;

        $jsonLdDate = $this->getJson('/api/v1/seo-pages/resolve?path=/recent-headline')
            ->assertOk()
            ->json('data.json_ld.0.datePublished');

        $this->assertSame($jsonLdDate, $sitemapDate);
    }
}
