<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotMetric;
use Illuminate\Support\Carbon;

class ChatbotAnalyticsController extends Controller
{
    public function indexPage()
    {
        $businessTimezone = 'Asia/Jakarta';
        $todayStart = Carbon::now($businessTimezone)->startOfDay();
        $todayEnd = Carbon::now($businessTimezone)->endOfDay();

        $todayMetrics = ChatbotMetric::whereBetween('created_at', [
            $todayStart->copy()->utc(),
            $todayEnd->copy()->utc(),
        ])->orderBy('_id', 'desc')->get();

        $total = $todayMetrics->count();
        $ruleBased = $todayMetrics->where('source', 'rule_based')->count();
        $geminiFallback = $todayMetrics->where('source', 'gemini_fallback')->count();
        $unknownResolved = $todayMetrics->where('intent_resolved', 'unknown_or_ambiguous')->count();
        $avgLatency = $total > 0
            ? (int) round($todayMetrics->avg(fn ($row) => (int) ($row->latency_ms ?? 0)))
            : 0;

        $sourceBreakdown = [
            'rule_based' => $ruleBased,
            'gemini_fallback' => $geminiFallback,
        ];

        $intentBreakdown = $todayMetrics
            ->groupBy(fn ($row) => (string) ($row->intent_resolved ?? 'unknown_or_ambiguous'))
            ->map(fn ($rows, $intent) => [
                'intent' => (string) $intent,
                'count' => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values();

        $recent = $todayMetrics->take(25)->map(function (ChatbotMetric $metric) use ($businessTimezone) {
            $createdAt = $metric->created_at
                ? Carbon::parse($metric->created_at)->setTimezone($businessTimezone)->format('H:i:s')
                : '-';

            return [
                'time' => $createdAt,
                'source' => (string) ($metric->source ?? '-'),
                'intentRuleBased' => (string) ($metric->intent_rule_based ?? '-'),
                'intentResolved' => (string) ($metric->intent_resolved ?? '-'),
                'latencyMs' => (int) ($metric->latency_ms ?? 0),
            ];
        });

        return view('backoffice.chatbot.index', [
            'summary' => [
                'total' => $total,
                'rule_based' => $ruleBased,
                'gemini_fallback' => $geminiFallback,
                'unknown_resolved' => $unknownResolved,
                'avg_latency_ms' => $avgLatency,
            ],
            'sourceBreakdown' => $sourceBreakdown,
            'intentBreakdown' => $intentBreakdown,
            'recentMetrics' => $recent,
            'businessDateLabel' => $todayStart->translatedFormat('d M Y'),
        ]);
    }
}
