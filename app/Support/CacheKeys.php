<?php

namespace App\Support;

/**
 * Cache keys in one place.
 *
 * Curriculum keys carry a version number that increments on any edit, so
 * clearing the cache is a single operation rather than a sweep over a thousand
 * rows, and nothing stale can be read afterwards. Old entries simply expire.
 */
class CacheKeys
{
    public const CURRICULUM_VERSION = 'curriculum.version';
    public const PLATFORM_SETTINGS = 'platform.settings';

    public const TTL_CURRICULUM = 3600;   // an hour, and versioned anyway
    public const TTL_LANDING = 900;       // fifteen minutes
    public const TTL_DASHBOARD = 300;     // five minutes

    public static function curriculumVersion(): int
    {
        return (int) cache()->get(self::CURRICULUM_VERSION, 1);
    }

    public static function curriculumSubjects(): string
    {
        return 'curriculum.v'.self::curriculumVersion().'.subjects';
    }

    public static function curriculumChildren(?int $parentId): string
    {
        return 'curriculum.v'.self::curriculumVersion().'.children.'.($parentId ?? 'root');
    }

    public static function landingStats(string $portal): string
    {
        return "landing.stats.{$portal}";
    }

    public static function adminDashboard(int $days): string
    {
        return "admin.dashboard.{$days}";
    }

    /** One increment retires every curriculum key at once. */
    public static function forgetCurriculum(): void
    {
        $store = cache();

        $current = (int) $store->get(self::CURRICULUM_VERSION, 1);

        $store->forever(self::CURRICULUM_VERSION, $current + 1);
    }
}
