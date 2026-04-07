<x-backoffice.layout pageTitle="Overview" pageSubtitle="Ringkasan performa bisnis secara visual">
	<section id="overview-panel" class="space-y-5">
		<article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
			<div class="flex items-center justify-between gap-3 mb-4">
				<h2 class="text-lg md:text-xl font-extrabold text-[var(--rich-black)]">Overview Bisnis (MVP)</h2>
				<span class="text-xs font-semibold text-slate-500">Update otomatis</span>
			</div>

			<div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
				<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
					<p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Menu</p>
					<p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) data_get($overview, 'kpi.menus', 0) }}</p>
				</div>
				<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
					<p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Order</p>
					<p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) data_get($overview, 'kpi.orders', 0) }}</p>
				</div>
				<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
					<p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total User</p>
					<p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) data_get($overview, 'kpi.users', 0) }}</p>
				</div>
				<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
					<p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Revenue Paid</p>
					<p class="mt-1 text-xl font-extrabold text-emerald-800">Rp {{ number_format((float) data_get($overview, 'kpi.revenue', 0), 0, ',', '.') }}</p>
				</div>
				<div class="rounded-xl border border-blue-200 bg-blue-50 p-3">
					<p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Avg Order Value</p>
					<p class="mt-1 text-xl font-extrabold text-blue-800">Rp {{ number_format((float) data_get($overview, 'kpi.averageOrderValue', 0), 0, ',', '.') }}</p>
				</div>
				<div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
					<p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Payment Success</p>
					<p class="mt-1 text-xl font-extrabold text-amber-800">{{ number_format((float) data_get($overview, 'kpi.paymentSuccessRate', 0), 1, ',', '.') }}%</p>
				</div>
			</div>
		</article>

		<section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
			<article class="xl:col-span-7 rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
				<h3 class="text-base font-extrabold text-[var(--rich-black)]">Tren 7 Hari</h3>
				<div class="mt-4 grid grid-cols-1 gap-4">
					<div class="rounded-xl border border-slate-200 p-3">
						<p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Jumlah Order</p>
						<div class="h-48"><canvas id="overview-order-trend"></canvas></div>
					</div>
					<div class="rounded-xl border border-slate-200 p-3">
						<p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Revenue Paid</p>
						<div class="h-48"><canvas id="overview-revenue-trend"></canvas></div>
					</div>
				</div>
			</article>

			<div class="xl:col-span-5 space-y-5">
				<article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
					<h3 class="text-base font-extrabold text-[var(--rich-black)]">Distribusi Status</h3>
					<div class="mt-4 h-64"><canvas id="overview-status-distribution"></canvas></div>
				</article>

				<article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
					<h3 class="text-base font-extrabold text-[var(--rich-black)]">Top Menu 30 Hari</h3>
					<div class="mt-3 space-y-2">
						@forelse (data_get($overview, 'topMenus30Days', []) as $menu)
							<div class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
								<p class="text-sm font-semibold text-slate-700 truncate">{{ $menu['name'] ?? '-' }}</p>
								<span class="text-xs font-bold rounded-full px-2 py-1 bg-slate-200 text-slate-700">{{ (int) ($menu['count'] ?? 0) }}x</span>
							</div>
						@empty
							<p class="text-sm text-slate-500">Belum ada data menu terjual.</p>
						@endforelse
					</div>
				</article>

				<article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
					<h3 class="text-base font-extrabold text-[var(--rich-black)]">Occupancy Meja</h3>
					<div class="mt-3 grid grid-cols-3 gap-2 text-center">
						<div class="rounded-lg bg-slate-50 p-2">
							<p class="text-[11px] uppercase font-bold text-slate-500">Total</p>
							<p class="text-lg font-extrabold text-[var(--rich-black)]">{{ (int) data_get($overview, 'tableOccupancy.totalTables', 0) }}</p>
						</div>
						<div class="rounded-lg bg-red-50 p-2">
							<p class="text-[11px] uppercase font-bold text-red-600">Terisi</p>
							<p class="text-lg font-extrabold text-red-700">{{ (int) data_get($overview, 'tableOccupancy.occupiedTables', 0) }}</p>
						</div>
						<div class="rounded-lg bg-emerald-50 p-2">
							<p class="text-[11px] uppercase font-bold text-emerald-600">Tersedia</p>
							<p class="text-lg font-extrabold text-emerald-700">{{ (int) data_get($overview, 'tableOccupancy.availableTables', 0) }}</p>
						</div>
					</div>
				</article>
			</div>
		</section>
	</section>

	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script>
		(function () {
			if (typeof Chart === 'undefined') {
				return;
			}

			const overview = JSON.parse('{!! json_encode($overview ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}');

			const orderCtx = document.getElementById('overview-order-trend');
			if (orderCtx) {
				new Chart(orderCtx, {
					type: 'line',
					data: {
						labels: overview.charts?.orderTrend7Days?.labels || [],
						datasets: [
							{
								label: 'Order',
								data: overview.charts?.orderTrend7Days?.values || [],
								borderColor: '#2563EB',
								backgroundColor: 'rgba(37,99,235,0.12)',
								fill: true,
								tension: 0.35,
								pointRadius: 3,
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: { legend: { display: false } },
					}
				});
			}

			const revenueCtx = document.getElementById('overview-revenue-trend');
			if (revenueCtx) {
				new Chart(revenueCtx, {
					type: 'bar',
					data: {
						labels: overview.charts?.revenueTrend7Days?.labels || [],
						datasets: [
							{
								label: 'Revenue',
								data: overview.charts?.revenueTrend7Days?.values || [],
								backgroundColor: 'rgba(16,185,129,0.7)',
								borderRadius: 6,
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: { legend: { display: false } },
					}
				});
			}

			const statusCtx = document.getElementById('overview-status-distribution');
			if (statusCtx) {
				new Chart(statusCtx, {
					type: 'doughnut',
					data: {
						labels: overview.charts?.statusDistribution?.labels || [],
						datasets: [
							{
								data: overview.charts?.statusDistribution?.values || [],
								backgroundColor: ['#F59E0B', '#F97316', '#3B82F6', '#10B981'],
								borderWidth: 0,
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10 } }
						},
					}
				});
			}
		})();
	</script>
</x-backoffice.layout>
