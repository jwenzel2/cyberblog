<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Session;

final class Analytics
{
    public static function recordSiteVisit(): void
    {
        $today = app_date('Y-m-d');
        if (Session::get('analytics.site_visit_date') === $today) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO analytics_site_daily (metric_date, visit_count)
             VALUES (:metric_date, 1)
             ON DUPLICATE KEY UPDATE visit_count = visit_count + 1'
        );
        $stmt->execute(['metric_date' => $today]);
        Session::put('analytics.site_visit_date', $today);
    }

    public static function recordPostView(int $postId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO analytics_post_daily (post_id, metric_date, view_count)
             VALUES (:post_id, :metric_date, 1)
             ON DUPLICATE KEY UPDATE view_count = view_count + 1'
        );
        $stmt->execute([
            'post_id' => $postId,
            'metric_date' => app_date('Y-m-d'),
        ]);
    }

    public static function siteVisitSummary(): array
    {
        return self::totals('analytics_site_daily', 'visit_count');
    }

    public static function postViewSummary(): array
    {
        return self::totals('analytics_post_daily', 'view_count');
    }

    public static function topPosts(int $days): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.title, p.slug, SUM(apd.view_count) AS views
             FROM analytics_post_daily apd
             INNER JOIN posts p ON p.id = apd.post_id
             WHERE apd.metric_date >= :start_date
             GROUP BY p.id, p.title, p.slug
             ORDER BY views DESC, p.title ASC
             LIMIT 10'
        );
        $stmt->execute(['start_date' => app_date('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'))]);

        return $stmt->fetchAll();
    }

    public static function recentSiteVisits(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT metric_date, visit_count
             FROM analytics_site_daily
             WHERE metric_date >= :start_date
             ORDER BY metric_date DESC'
        );
        $stmt->execute(['start_date' => app_date('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'))]);

        return $stmt->fetchAll();
    }

    private static function totals(string $table, string $column): array
    {
        $pdo = Database::connection();
        $ranges = [
            'today' => app_date('Y-m-d'),
            'week' => app_date('Y-m-d', strtotime('-6 days')),
            'month' => app_date('Y-m-d', strtotime('-29 days')),
        ];

        $result = [];
        foreach ($ranges as $label => $startDate) {
            $stmt = $pdo->prepare(sprintf(
                'SELECT COALESCE(SUM(%s), 0) FROM %s WHERE metric_date >= :start_date',
                $column,
                $table
            ));
            $stmt->execute(['start_date' => $startDate]);
            $result[$label] = (int) $stmt->fetchColumn();
        }

        return $result;
    }
}
