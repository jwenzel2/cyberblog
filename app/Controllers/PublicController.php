<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Analytics;
use App\Models\Category;
use App\Models\Post;
use App\Models\Preference;
use App\Models\User;

final class PublicController
{
    public function home(): void
    {
        Analytics::recordSiteVisit();
        $page = $this->page();
        $perPage = $this->perPage();
        $pagination = Post::recentPublished($page, $perPage);
        $siteName = trim((string) Preference::get('seo_site_name', 'CyberBlog'));

        $seo = $this->seoData(
            title: $this->pageTitle('Home'),
            description: (string) Preference::get('seo_default_description', ''),
            canonicalUrl: current_url()
        );
        $seo['json_ld'] = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => app_url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => app_url('/') . '?s={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
        $seo = $this->addPaginationLinks($seo, $pagination, app_url('/'));

        View::render('public/home', [
            'title' => $this->pageTitle('Home'),
            'posts' => $pagination['items'],
            'pagination' => $pagination,
            'categories' => Category::tree(),
            'seo' => $seo,
        ]);
    }

    public function post(string $slug): void
    {
        $post = Post::findPublishedBySlug($slug);
        if (!$post) {
            \App\Core\Response::abort(404, 'Post not found');
        }
        Analytics::recordSiteVisit();
        Analytics::recordPostView((int) $post['id']);

        $siteName = trim((string) Preference::get('seo_site_name', 'CyberBlog'));
        $canonicalUrl = app_url('/post/' . urlencode($post['slug']));
        $imageUrl = !empty($post['featured_image']) ? app_url((string) $post['featured_image']) : null;
        $description = seo_excerpt((string) ($post['excerpt'] ?: $post['body_html']));
        $author = !empty($post['author_id']) ? User::find((int) $post['author_id']) : null;
        $authorName = $author['display_name'] ?? 'Unknown';
        $categories = $post['categories'] ?? [];
        $firstCategory = $categories[0] ?? null;

        $breadcrumbs = [['name' => 'Home', 'url' => app_url('/')]];
        if ($firstCategory) {
            $breadcrumbs[] = ['name' => $firstCategory['name'], 'url' => app_url('/category/' . urlencode($firstCategory['slug']))];
        }
        $breadcrumbs[] = ['name' => $post['title']];

        $seo = $this->seoData(
            title: $this->pageTitle((string) $post['title']),
            description: $description,
            canonicalUrl: $canonicalUrl,
            imageUrl: $imageUrl,
            type: 'article'
        );

        $seo['article_published_time'] = $post['published_at'] ? gmdate('c', strtotime($post['published_at'])) : null;
        $seo['article_modified_time'] = $post['updated_at'] ? gmdate('c', strtotime($post['updated_at'])) : null;
        $seo['article_author'] = $authorName;
        $seo['article_section'] = $firstCategory['name'] ?? null;

        $blogPosting = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post['title'],
            'description' => $description,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl],
            'author' => ['@type' => 'Person', 'name' => $authorName],
            'publisher' => ['@type' => 'Organization', 'name' => $siteName, 'url' => app_url('/')],
        ];
        if ($post['published_at']) {
            $blogPosting['datePublished'] = gmdate('c', strtotime($post['published_at']));
        }
        if ($post['updated_at']) {
            $blogPosting['dateModified'] = gmdate('c', strtotime($post['updated_at']));
        }
        if ($imageUrl) {
            $blogPosting['image'] = $imageUrl;
        }
        if ($firstCategory) {
            $blogPosting['articleSection'] = $firstCategory['name'];
        }

        $seo['json_ld'] = [$blogPosting, $this->breadcrumbListJsonLd($breadcrumbs)];

        View::render('public/post', [
            'title' => $this->pageTitle((string) $post['title']),
            'post' => $post,
            'categories' => Category::tree(),
            'shareServices' => $this->shareServices(),
            'shareUrl' => app_url('/post/' . urlencode((string) $post['slug'])),
            'breadcrumbs' => $breadcrumbs,
            'seo' => $seo,
        ]);
    }

    public function category(string $slug): void
    {
        $category = Category::findBySlug($slug);
        if (!$category) {
            \App\Core\Response::abort(404, 'Category not found');
        }
        Analytics::recordSiteVisit();

        $page = $this->page();
        $perPage = $this->perPage();
        $pagination = Post::forCategory((int) $category['id'], $page, $perPage);
        $categoryUrl = app_url('/category/' . urlencode((string) $category['slug']));

        $breadcrumbs = [['name' => 'Home', 'url' => app_url('/')]];
        if (!empty($category['parent_id'])) {
            $parent = Category::find((int) $category['parent_id']);
            if ($parent) {
                $breadcrumbs[] = ['name' => $parent['name'], 'url' => app_url('/category/' . urlencode($parent['slug']))];
            }
        }
        $breadcrumbs[] = ['name' => $category['name']];

        $seo = $this->seoData(
            title: $this->pageTitle((string) $category['name']),
            description: seo_excerpt((string) ($category['description'] ?: Preference::get('seo_default_description', ''))),
            canonicalUrl: current_url()
        );
        $seo['json_ld'] = [$this->breadcrumbListJsonLd($breadcrumbs)];
        $seo = $this->addPaginationLinks($seo, $pagination, $categoryUrl);

        View::render('public/category', [
            'title' => $this->pageTitle((string) $category['name']),
            'category' => $category,
            'posts' => $pagination['items'],
            'pagination' => $pagination,
            'categories' => Category::tree(),
            'breadcrumbs' => $breadcrumbs,
            'seo' => $seo,
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
        $posts = Post::publishedForSitemap();
        $latestPostDate = $posts[0]['updated_at'] ?? $posts[0]['published_at'] ?? $posts[0]['created_at'] ?? now();

        $urls = [[
            'loc' => app_url('/'),
            'lastmod' => (string) $latestPostDate,
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

        foreach ($posts as $post) {
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

    public function feed(): void
    {
        $siteName = trim((string) Preference::get('seo_site_name', 'CyberBlog'));
        $siteDescription = trim((string) Preference::get('seo_default_description', ''));
        $pagination = Post::recentPublished(1, 20);

        http_response_code(200);
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
        echo "  <channel>\n";
        echo '    <title>' . htmlspecialchars($siteName, ENT_XML1) . "</title>\n";
        echo '    <link>' . htmlspecialchars(app_url('/'), ENT_XML1) . "</link>\n";
        echo '    <description>' . htmlspecialchars($siteDescription, ENT_XML1) . "</description>\n";
        echo '    <atom:link href="' . htmlspecialchars(app_url('/feed'), ENT_XML1) . '" rel="self" type="application/rss+xml"/>' . "\n";
        echo '    <lastBuildDate>' . gmdate('r') . "</lastBuildDate>\n";

        foreach ($pagination['items'] as $post) {
            $postUrl = app_url('/post/' . urlencode((string) $post['slug']));
            $pubDate = gmdate('r', strtotime((string) ($post['published_at'] ?: $post['created_at'])));
            $excerpt = seo_excerpt((string) ($post['excerpt'] ?: $post['body_html']), 300);

            echo "    <item>\n";
            echo '      <title>' . htmlspecialchars((string) $post['title'], ENT_XML1) . "</title>\n";
            echo '      <link>' . htmlspecialchars($postUrl, ENT_XML1) . "</link>\n";
            echo '      <description>' . htmlspecialchars($excerpt, ENT_XML1) . "</description>\n";
            echo '      <pubDate>' . $pubDate . "</pubDate>\n";
            echo '      <guid isPermaLink="true">' . htmlspecialchars($postUrl, ENT_XML1) . "</guid>\n";
            echo "    </item>\n";
        }

        echo "  </channel>\n";
        echo "</rss>";
        exit;
    }

    public function indexNowKey(): void
    {
        $apiKey = trim((string) Preference::get('indexnow_api_key', ''));
        http_response_code($apiKey !== '' ? 200 : 404);
        header('Content-Type: text/plain; charset=utf-8');
        echo $apiKey;
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

    private function breadcrumbListJsonLd(array $breadcrumbs): array
    {
        $items = [];
        foreach ($breadcrumbs as $i => $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
            ];
            if (isset($crumb['url'])) {
                $item['item'] = $crumb['url'];
            }
            $items[] = $item;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    private function addPaginationLinks(array $seo, array $pagination, string $baseUrl): array
    {
        $page = (int) ($pagination['page'] ?? 1);
        $totalPages = (int) ($pagination['total_pages'] ?? 1);
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        if ($page > 1) {
            $seo['pagination_prev'] = $page === 2 ? $baseUrl : $baseUrl . $separator . 'page=' . ($page - 1);
        }
        if ($page < $totalPages) {
            $seo['pagination_next'] = $baseUrl . $separator . 'page=' . ($page + 1);
        }

        return $seo;
    }
}
