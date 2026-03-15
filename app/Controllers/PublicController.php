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
            'title' => 'CyberBlog',
            'posts' => $pagination['items'],
            'pagination' => $pagination,
            'categories' => Category::tree(),
        ]);
    }

    public function post(string $slug): void
    {
        $post = Post::findPublishedBySlug($slug);
        if (!$post) {
            \App\Core\Response::abort(404, 'Post not found');
        }

        View::render('public/post', [
            'title' => $post['title'],
            'post' => $post,
            'categories' => Category::tree(),
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
            'title' => $category['name'],
            'category' => $category,
            'posts' => $pagination['items'],
            'pagination' => $pagination,
            'categories' => Category::tree(),
        ]);
    }

    private function page(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    private function perPage(): int
    {
        return max(1, min(100, (int) Preference::get('articles_per_page', '10')));
    }
}
