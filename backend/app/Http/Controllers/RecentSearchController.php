<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceSearchEngine;
use App\Services\RecentSearchService;
use Illuminate\Http\Request;

class RecentSearchController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'recent_searches' => RecentSearchService::getRecent($request),
        ]);
    }

    public function store(Request $request)
    {
        $rawQuery = (string) $request->input('q', '');
        $clean = RecentSearchService::sanitize($rawQuery);

        if ($clean !== '') {
            $section = $request->input('section');
            if (!$section) {
                $resolved = MarketplaceSearchEngine::resolveGlobalSearchDestination($clean);
                $section = $resolved['section'] ?? 'all';
            }

            RecentSearchService::record($request, $clean, (string) $section, allowJson: true);
        }

        $recent = RecentSearchService::getRecent($request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'recent_searches' => $recent,
            ]);
        }

        return back();
    }

    public function destroyItem(Request $request)
    {
        $query = (string) $request->input('q', '');
        $recent = RecentSearchService::removeOne($request, $query);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'recent_searches' => $recent,
            ]);
        }

        return back();
    }

    public function clear(Request $request)
    {
        RecentSearchService::clearAll($request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'recent_searches' => [],
            ]);
        }

        return back();
    }
}
