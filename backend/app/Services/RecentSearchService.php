<?php

namespace App\Services;

use App\Models\UserRecentSearch;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecentSearchService
{
    public const DEMO_CUSTOMER_EMAIL = 'customer@meramot.demo';
    public const COOKIE_NAME = 'meramot_recent_searches';
    public const SESSION_KEY = 'meramot_recent_searches';
    public const CLEARED_SESSION_KEY = 'meramot_recent_searches_cleared';
    public const MAX_ITEMS = 8;
    public const COOKIE_TTL_MINUTES = 43200; // 30 days

    /**
     * Pre-populated demo showcase searches reserved strictly for customer@meramot.demo.
     *
     * @var array<int, array{query: string, target_section: string, offset_minutes: int}>
     */
    public const DEMO_DEFAULTS = [
        ['query' => 'ThinkPad keyboard repair', 'target_section' => 'spare-parts', 'offset_minutes' => 2],
        ['query' => 'iPhone 13 Pro OLED display', 'target_section' => 'spare-parts', 'offset_minutes' => 9],
        ['query' => 'MacBook Air M2 battery replacement', 'target_section' => 'spare-parts', 'offset_minutes' => 16],
        ['query' => 'Samsung S22 Ultra display', 'target_section' => 'spare-parts', 'offset_minutes' => 23],
        ['query' => 'Thermal repasting', 'target_section' => 'packages', 'offset_minutes' => 30],
    ];

    public static function sanitize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $stripped = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $raw);
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', (string) $stripped));
        if ($collapsed === '') {
            return '';
        }

        $truncated = trim(mb_substr($collapsed, 0, 120, 'UTF-8'));

        return mb_strlen($truncated, 'UTF-8') >= 2 ? $truncated : '';
    }

    public static function normalize(string $clean): string
    {
        return mb_strtolower(
            trim((string) preg_replace('/\s+/u', ' ', $clean)),
            'UTF-8'
        );
    }

    public static function shouldRecord(Request $request, bool $allowJson = false): bool
    {
        if ($request->header('X-Meramot-Prefetch') === '1') {
            return false;
        }

        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?: $request->header('Purpose')));
        if ($purpose !== '' && str_contains($purpose, 'prefetch')) {
            return false;
        }

        if (!$allowJson && $request->expectsJson()) {
            return false;
        }

        if ((int) $request->query('page', 1) > 1) {
            return false;
        }

        return true;
    }

    public static function record(Request $request, ?string $rawQuery, ?string $section = null, bool $allowJson = false): void
    {
        if (!self::shouldRecord($request, $allowJson)) {
            return;
        }

        $clean = self::sanitize($rawQuery);
        if ($clean === '') {
            return;
        }

        $normalized = self::normalize($clean);
        $safeSection = self::sanitizeSection($section);

        if (Auth::check()) {
            self::recordForUser((int) Auth::id(), $clean, $normalized, $safeSection);
            return;
        }

        self::recordForGuest($request, $clean, $normalized, $safeSection);
    }

    /**
     * @return array<int, array{label: string, query: string, normalized: string, href: string, section: string, searched_at: ?string}>
     */
    public static function getRecent(Request $request, int $limit = self::MAX_ITEMS): array
    {
        $limit = max(1, min(self::MAX_ITEMS, $limit));

        if (Auth::check()) {
            if (!Schema::hasTable('user_recent_searches')) {
                return [];
            }

            return UserRecentSearch::query()
                ->where('user_id', (int) Auth::id())
                ->orderByDesc('last_searched_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (UserRecentSearch $row) => self::formatItem(
                    $row->query,
                    $row->target_section,
                    $row->last_searched_at?->toIso8601String()
                ))
                ->values()
                ->all();
        }

        $items = self::readGuestItems($request);

        return array_values(array_map(
            fn (array $item) => self::formatItem(
                (string) $item['query'],
                $item['section'] ?? 'all',
                $item['searched_at'] ?? null
            ),
            array_slice($items, 0, $limit)
        ));
    }

    /**
     * @return array<int, array{label: string, query: string, normalized: string, href: string, section: string, searched_at: ?string}>
     */
    public static function removeOne(Request $request, string $rawQuery): array
    {
        $clean = self::sanitize($rawQuery);
        $normalized = $clean !== '' ? self::normalize($clean) : self::normalize(trim($rawQuery));

        if ($normalized === '') {
            return self::getRecent($request);
        }

        if (Auth::check()) {
            if (Schema::hasTable('user_recent_searches')) {
                UserRecentSearch::query()
                    ->where('user_id', (int) Auth::id())
                    ->where('normalized_query', $normalized)
                    ->delete();
            }

            return self::getRecent($request);
        }

        $items = self::readGuestItems($request);
        $filtered = array_values(array_filter(
            $items,
            fn (array $item) => self::normalize((string) ($item['query'] ?? '')) !== $normalized
        ));

        self::writeGuestItems($request, $filtered);

        return self::getRecent($request);
    }

    public static function clearAll(Request $request): void
    {
        if (Auth::check()) {
            if (Schema::hasTable('user_recent_searches')) {
                UserRecentSearch::query()
                    ->where('user_id', (int) Auth::id())
                    ->delete();
            }

            return;
        }

        self::writeGuestItems($request, []);
    }

    public static function seedDemoCustomerDefaults(int $userId, bool $onlyIfEmpty = true): void
    {
        if (!Schema::hasTable('user_recent_searches')) {
            return;
        }

        if ($onlyIfEmpty && UserRecentSearch::query()->where('user_id', $userId)->exists()) {
            return;
        }

        $now = now();
        foreach (self::DEMO_DEFAULTS as $item) {
            $clean = self::sanitize($item['query']);
            if ($clean === '') {
                continue;
            }

            $normalized = self::normalize($clean);
            $timestamp = $now->copy()->subMinutes($item['offset_minutes']);

            DB::table('user_recent_searches')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'normalized_query' => $normalized,
                ],
                [
                    'query' => $clean,
                    'target_section' => $item['target_section'],
                    'search_count' => 1,
                    'last_searched_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );
        }
    }

    /**
     * @return array{label: string, query: string, normalized: string, href: string, section: string, searched_at: ?string}
     */
    private static function formatItem(string $query, ?string $section = null, ?string $searchedAt = null): array
    {
        return [
            'label' => $query,
            'query' => $query,
            'normalized' => self::normalize($query),
            'href' => '/search?q=' . rawurlencode($query),
            'section' => self::sanitizeSection($section) ?? 'all',
            'searched_at' => $searchedAt,
        ];
    }

    private static function sanitizeSection(?string $section): ?string
    {
        $clean = strtolower(trim((string) $section));
        return in_array($clean, ['spare-parts', 'shops', 'packages', 'all'], true) ? $clean : null;
    }

    private static function recordForUser(int $userId, string $clean, string $normalized, ?string $section): void
    {
        if (!Schema::hasTable('user_recent_searches')) {
            return;
        }

        DB::transaction(function () use ($userId, $clean, $normalized, $section) {
            $now = now();
            $existing = UserRecentSearch::query()
                ->where('user_id', $userId)
                ->where('normalized_query', $normalized)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->query = $clean;
                if ($section !== null) {
                    $existing->target_section = $section;
                }
                $existing->search_count = (int) $existing->search_count + 1;
                $existing->last_searched_at = $now;
                $existing->save();
            } else {
                try {
                    UserRecentSearch::create([
                        'user_id' => $userId,
                        'query' => $clean,
                        'normalized_query' => $normalized,
                        'target_section' => $section ?? 'all',
                        'search_count' => 1,
                        'last_searched_at' => $now,
                    ]);
                } catch (QueryException $e) {
                    if (($e->errorInfo[0] ?? null) === '23505') {
                        UserRecentSearch::query()
                            ->where('user_id', $userId)
                            ->where('normalized_query', $normalized)
                            ->update([
                                'query' => $clean,
                                'target_section' => $section ?? 'all',
                                'search_count' => DB::raw('search_count + 1'),
                                'last_searched_at' => $now,
                                'updated_at' => $now,
                            ]);
                    } else {
                        throw $e;
                    }
                }
            }

            $keepIds = UserRecentSearch::query()
                ->where('user_id', $userId)
                ->orderByDesc('last_searched_at')
                ->orderByDesc('id')
                ->limit(self::MAX_ITEMS)
                ->pluck('id');

            if ($keepIds->count() >= self::MAX_ITEMS) {
                UserRecentSearch::query()
                    ->where('user_id', $userId)
                    ->whereNotIn('id', $keepIds)
                    ->delete();
            }
        });
    }

    private static function recordForGuest(Request $request, string $clean, string $normalized, ?string $section): void
    {
        $items = self::readGuestItems($request);

        $items = array_values(array_filter(
            $items,
            fn (array $item) => self::normalize((string) ($item['query'] ?? '')) !== $normalized
        ));

        array_unshift($items, [
            'query' => $clean,
            'section' => $section ?? 'all',
            'searched_at' => now()->toIso8601String(),
        ]);

        self::writeGuestItems($request, array_slice($items, 0, self::MAX_ITEMS));
    }

    /**
     * @return array<int, array{query: string, section: string, searched_at: ?string}>
     */
    private static function readGuestItems(Request $request): array
    {
        if ($request->hasSession()) {
            if ($request->session()->get(self::CLEARED_SESSION_KEY) === true && !$request->session()->has(self::SESSION_KEY)) {
                return [];
            }

            if ($request->session()->has(self::SESSION_KEY)) {
                $sessionRaw = $request->session()->get(self::SESSION_KEY);
                return self::normalizeGuestPayload(is_array($sessionRaw) ? $sessionRaw : []);
            }
        }

        $cookieRaw = $request->cookie(self::COOKIE_NAME);
        if (!is_string($cookieRaw) || trim($cookieRaw) === '') {
            return [];
        }

        $decoded = json_decode($cookieRaw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = self::normalizeGuestPayload($decoded);

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $items);
        }

        return $items;
    }

    /**
     * @param array<int, mixed> $items
     */
    private static function writeGuestItems(Request $request, array $items): void
    {
        $normalized = self::normalizeGuestPayload($items);

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $normalized);
            if (empty($normalized)) {
                $request->session()->put(self::CLEARED_SESSION_KEY, true);
            } else {
                $request->session()->forget(self::CLEARED_SESSION_KEY);
            }
        }

        if (empty($normalized)) {
            Cookie::queue(Cookie::forget(self::COOKIE_NAME, '/'));
            return;
        }

        $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        Cookie::queue(Cookie::make(
            self::COOKIE_NAME,
            $payload ?: '[]',
            self::COOKIE_TTL_MINUTES,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'Lax'
        ));
    }

    /**
     * @param array<int, mixed> $rawItems
     * @return array<int, array{query: string, section: string, searched_at: ?string}>
     */
    private static function normalizeGuestPayload(array $rawItems): array
    {
        $result = [];
        $seen = [];

        foreach ($rawItems as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $clean = self::sanitize(isset($entry['query']) ? (string) $entry['query'] : null);
            if ($clean === '') {
                continue;
            }

            $norm = self::normalize($clean);
            if (isset($seen[$norm])) {
                continue;
            }

            $seen[$norm] = true;
            $result[] = [
                'query' => $clean,
                'section' => self::sanitizeSection(isset($entry['section']) ? (string) $entry['section'] : null) ?? 'all',
                'searched_at' => isset($entry['searched_at']) && is_string($entry['searched_at'])
                    ? $entry['searched_at']
                    : null,
            ];

            if (count($result) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $result;
    }
}
