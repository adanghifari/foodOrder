<x-backoffice.layout pageTitle="Chatbot Analytics">
    <section class="space-y-5">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-xl md:text-2xl font-extrabold text-[var(--rich-black)]">Chatbot Hari Ini</h2>
                    <p class="text-sm font-semibold text-slate-500">Ringkasan aktivitas chatbot untuk {{ $businessDateLabel ?? '-' }}.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-[#6A2B09]/20 bg-[#FCB861]/20 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-[#6A2B09]">
                    Monitoring Hybrid
                </span>
            </div>

            <div class="mt-4 grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Pesan</p>
                    <p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) ($summary['total'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Rule-Based</p>
                    <p class="mt-1 text-xl font-extrabold text-emerald-800">{{ (int) ($summary['rule_based'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-indigo-700">Gemini Fallback</p>
                    <p class="mt-1 text-xl font-extrabold text-indigo-800">{{ (int) ($summary['gemini_fallback'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Unknown Resolved</p>
                    <p class="mt-1 text-xl font-extrabold text-amber-800">{{ (int) ($summary['unknown_resolved'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-sky-700">Avg Latency</p>
                    <p class="mt-1 text-xl font-extrabold text-sky-800">{{ (int) ($summary['avg_latency_ms'] ?? 0) }} ms</p>
                </div>
            </div>
        </article>

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <article class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide text-slate-600">Sumber Keputusan</h3>
                </div>
                <div class="p-4 space-y-3">
                    @php
                        $total = max(1, (int) (($summary['total'] ?? 0)));
                        $ruleBasedCount = (int) ($sourceBreakdown['rule_based'] ?? 0);
                        $geminiCount = (int) ($sourceBreakdown['gemini_fallback'] ?? 0);
                        $rulePct = round(($ruleBasedCount / $total) * 100, 1);
                        $geminiPct = round(($geminiCount / $total) * 100, 1);
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs font-bold text-emerald-700">
                            <span>Rule-Based</span>
                            <span>{{ $ruleBasedCount }} ({{ $rulePct }}%)</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-emerald-100">
                            <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $rulePct }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-xs font-bold text-indigo-700">
                            <span>Gemini Fallback</span>
                            <span>{{ $geminiCount }} ({{ $geminiPct }}%)</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-indigo-100">
                            <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $geminiPct }}%"></div>
                        </div>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide text-slate-600">Top Intent Hari Ini</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-bold">Intent</th>
                                <th class="px-4 py-3 font-bold text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse(($intentBreakdown ?? []) as $intent)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $intent['intent'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm font-extrabold text-slate-900 text-right">{{ (int) ($intent['count'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-8 text-sm text-center font-semibold text-slate-500">Belum ada data intent hari ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                <h3 class="text-sm font-extrabold uppercase tracking-wide text-slate-600">Aktivitas Terbaru</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left">
                    <thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">Waktu</th>
                            <th class="px-4 py-3 font-bold">Source</th>
                            <th class="px-4 py-3 font-bold">Rule Intent</th>
                            <th class="px-4 py-3 font-bold">Resolved Intent</th>
                            <th class="px-4 py-3 font-bold text-right">Latency</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse(($recentMetrics ?? []) as $metric)
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-700">{{ $metric['time'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ ($metric['source'] ?? '') === 'gemini_fallback' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ $metric['source'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $metric['intentRuleBased'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $metric['intentResolved'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm font-extrabold text-slate-900 text-right">{{ (int) ($metric['latencyMs'] ?? 0) }} ms</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-sm text-center font-semibold text-slate-500">Belum ada aktivitas chatbot hari ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</x-backoffice.layout>
