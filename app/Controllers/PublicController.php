<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Category;
use App\Models\Post;

final class PublicController
{
    public function home(): void
    {
        View::render('public/home', [
            'title' => 'CyberBlog',
            'posts' => Post::recentPublished(),
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

        View::render('public/category', [
            'title' => $category['name'],
            'category' => $category,
            'posts' => Post::forCategory((int) $category['id']),
            'categories' => Category::tree(),
        ]);
    }
}
