<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Category;
use App\Models\Post;
use App\Models\Preference;

final class PublicController
{
    public function home(): void
    {
        $pagination = Post::recentPublished($this->page(), $this->perPage());
        View::render('public/home', [
            'title' => $this->pageTitle('Home'),
            'posts' => $pagination['items'],
            'pagination' => $pagination,
            'categories' => Category::tree(),
            'seo' => $this->seoData(
                title: $this->pageTitle('Home'),
                description: (string) Preference::get('seo_default_description', ''),
                canonicalUrl: current_url()
            ),
        ]);
    }

    public function post(string $slug): void
    {
        $post = Post::findPublishedBySlug($slug);
        if (!$post) {
            \App\Core\Response::abort(404, 'Post not found');
        }

        View::render('public/post', [
            'title' => $this->pageTitle((string) $post['title']),
            'post' => $post,
            'categories' => Category::tree(),
            'shareServices' => $this->shareServices(),
            'shareUrl' => app_url('/post/' . urlencode((string) $post['slug'])),
            'seo' => $this->seoData(
                title: $this->pageTitle((string) $post['title']),
                description: seo_excerpt((string) ($post['excerpt'] ?: $post['body_html'])),
                canonicalUrl: app_url('/post/' . urlencode($post['slug'])),
                imageUrl: !empty($post['featured_image']) ? app_url((string) $post['featured_image']) : null,
                type: 'article'
            ),
        ]);
    }

    public function category(string $slug): void
    {
        $category = Category::findBySlug($slug);
        if (!$category) {
            \App\Core\Response::abort(404, 'Category not found');
        }

        $pagination = Post::forCategory((int) $category['id'], $this->page(), $this->perPage());

        View::render('public/category', [
            'title' => $this->pageTitle((string) $category['name']),
            'category' => $category,
            'posts' => $pagination['items'],
            'pagination' => $pagination,
            'categories' => Category::tree(),
            'seo' => $this->seoData(
                title: $this->pageTitle((string) $category['name']),
                description: seo_excerpt((string) ($category['description'] ?: Preference::get('seo_default_description', ''))),
                canonicalUrl: current_url()
            ),
        ]);
    }

    public function robots(): void
    {
        $allowIndexing = Preference::get('seo_allow_indexing', '1') === '1';
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo $allowIndexing ? "Allow: /\n" : "Disallow: /\n";
        echo "Sitemap: " . app_url('/sitemap.xml') . "\n";
        exit;
    }

    public function sitemap(): void
    {
        $urls = [[
            'loc' => app_url('/'),
            'lastmod' => now(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ]];

        foreach (Category::all() as $category) {
            $urls[] = [
                'loc' => app_url('/category/' . urlencode((string) $category['slug'])),
                'lastmod' => (string) ($category['updated_at'] ?? $category['created_at'] ?? now()),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        foreach (Post::publishedForSitemap() as $post) {
            $urls[] = [
                'loc' => app_url('/post/' . urlencode((string) $post['slug'])),
                'lastmod' => (string) ($post['updated_at'] ?? $post['published_at'] ?? $post['created_at'] ?? now()),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        http_response_code(200);
        header('Content-Type: application/xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $url) {
            echo "  <url>\n";
            echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
            echo '    <lastmod>' . gmdate('c', strtotime((string) $url['lastmod'])) . "</lastmod>\n";
            echo '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            echo '    <priority>' . $url['priority'] . "</priority>\n";
            echo "  </url>\n";
        }
        echo "</urlset>";
        exit;
    }

    private function page(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    private function perPage(): int
    {
        return max(1, min(100, (int) Preference::get('articles_per_page', '10')));
    }

    private function pageTitle(string $pageTitle): string
    {
        $siteName = trim((string) Preference::get('seo_site_name', 'CyberBlog'));
        if ($pageTitle === '' || $pageTitle === $siteName) {
            return $siteName;
        }

        return $pageTitle . ' | ' . $siteName;
    }

    private function seoData(string $title, string $description = '', ?string $canonicalUrl = null, ?string $imageUrl = null, string $type = 'website'): array
    {
        $allowIndexing = Preference::get('seo_allow_indexing', '1') === '1';
        $description = $description !== '' ? $description : (string) Preference::get('seo_default_description', '');

        return [
            'title' => $title,
            'description' => seo_excerpt($description),
            'canonical_url' => $canonicalUrl ?? current_url(),
            'robots' => $allowIndexing ? 'index, follow' : 'noindex, nofollow',
            'google_site_verification' => trim((string) Preference::get('seo_google_site_verification', '')),
            'bing_site_verification' => trim((string) Preference::get('seo_bing_site_verification', '')),
            'image_url' => $imageUrl,
            'type' => $type,
        ];
    }

    private function shareServices(): array
    {
        return [
            'facebook' => Preference::get('sharing_facebook_enabled', '1') === '1',
            'linkedin' => Preference::get('sharing_linkedin_enabled', '1') === '1',
            'x' => Preference::get('sharing_x_enabled', '1') === '1',
        ];
    }
}
