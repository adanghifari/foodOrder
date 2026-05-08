<x-backoffice.layout pageTitle="Overview" pageSubtitle="Ringkasan performa bisnis secara visual">
	<section id="overview-panel" class="space-y-5">
		<article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
			<div class="flex items-center justify-between gap-3 mb-4">
				<h2 class="text-lg md:text-xl font-extrabold text-[var(--rich-black)]">Overview Bisnis (MVP)</h2>
				<span class="text-xs font-semibold text-slate-500">Update otomatis</span>
			</div>

			<form id="overview-filter-form" method="GET" action="/backoffice/overview" class="mb-4 grid grid-cols-1 md:grid-cols-12 gap-3">
				@if ($errors->any())
					<div class="md:col-span-12 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
						{{ $errors->first() }}
					</div>
				@endif
				<div class="md:col-span-3">
					<label for="overview-mode" class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Mode Filter</label>
					<select id="overview-mode" name="mode" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-[#6A2B09]/25">
						<option value="none" {{ data_get($filters ?? [], 'mode', 'none') === 'none' ? 'selected' : '' }}>None</option>
						<option value="day" {{ data_get($filters ?? [], 'mode', 'none') === 'day' ? 'selected' : '' }}>Spesifik Hari</option>
						<option value="month" {{ data_get($filters ?? [], 'mode', 'none') === 'month' ? 'selected' : '' }}>Spesifik Bulan</option>
						<option value="year" {{ data_get($filters ?? [], 'mode', 'none') === 'year' ? 'selected' : '' }}>Spesifik Tahun</option>
					</select>
				</div>
				<div id="overview-day-col" class="md:col-span-3">
					<label for="overview-tanggal" class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Tanggal</label>
					<select id="overview-tanggal" name="tanggal" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-[#6A2B09]/25">
						<option value="">Pilih Tanggal</option>
						@for ($day = 1; $day <= 31; $day++)
							<option value="{{ $day }}" {{ (string) $day === (string) data_get($filters ?? [], 'tanggal', '') ? 'selected' : '' }}>{{ $day }}</option>
						@endfor
					</select>
				</div>
				<div id="overview-month-col" class="md:col-span-3">
					<label for="overview-bulan" class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Bulan</label>
					<select id="overview-bulan" name="bulan" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-[#6A2B09]/25">
						<option value="">Pilih Bulan</option>
						@foreach ([1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'] as $monthNumber => $monthName)
							<option value="{{ $monthNumber }}" {{ (string) $monthNumber === (string) data_get($filters ?? [], 'bulan', '') ? 'selected' : '' }}>{{ $monthName }}</option>
						@endforeach
					</select>
				</div>
				<div id="overview-year-col" class="md:col-span-3">
					<label for="overview-tahun" class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Tahun</label>
					<select id="overview-tahun" name="tahun" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-[#6A2B09]/25">
						<option value="">Pilih Tahun</option>
						@for ($year = (int) now()->year; $year >= ((int) now()->year - 6); $year--)
							<option value="{{ $year }}" {{ (string) $year === (string) data_get($filters ?? [], 'tahun', '') ? 'selected' : '' }}>{{ $year }}</option>
						@endfor
					</select>
				</div>
				<div class="md:col-span-12 flex items-end gap-2">
					<button id="overview-apply-filter" type="submit" class="inline-flex items-center justify-center rounded-xl bg-[var(--alloy-orange)] hover:bg-[var(--philippine-bronze)] text-white text-sm font-bold px-4 py-2.5 transition">
						Terapkan Filter
					</button>
					<a href="/backoffice/overview/export-pdf?{{ http_build_query(request()->query()) }}" class="inline-flex items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-sm font-bold px-4 py-2.5 transition">
						Export PDF
					</a>
					<a href="/backoffice/overview" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold px-4 py-2.5 transition">
						Reset
					</a>
				</div>
				<p class="md:col-span-12 text-xs text-slate-500">
					Pilih mode filter yang dibutuhkan.
				</p>
			</form>

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
				<h3 class="text-base font-extrabold text-[var(--rich-black)]">{{ data_get($overview, 'meta.trendLabel', 'Tren 7 Hari') }}</h3>
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
					<h3 class="text-base font-extrabold text-[var(--rich-black)]">Top Menu (Sesuai Filter)</h3>
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
			const modeField = document.getElementById('overview-mode');
			const filterForm = document.getElementById('overview-filter-form');
			const dayCol = document.getElementById('overview-day-col');
			const monthCol = document.getElementById('overview-month-col');
			const yearCol = document.getElementById('overview-year-col');
			const dayInput = document.getElementById('overview-tanggal');
			const monthInput = document.getElementById('overview-bulan');
			const yearInput = document.getElementById('overview-tahun');

			function syncFilterMode() {
				if (!modeField) {
					return;
				}

				const mode = modeField.value || 'none';
				const isNone = mode === 'none';
				const isDay = mode === 'day';
				const isMonth = mode === 'month';
				const isYear = mode === 'year';

				if (dayCol) dayCol.classList.toggle('hidden', !isDay || isNone);
				if (monthCol) monthCol.classList.toggle('hidden', isYear || isNone);
				if (yearCol) yearCol.classList.toggle('hidden', isNone);

				if (dayInput) dayInput.disabled = !isDay || isNone;
				if (monthInput) monthInput.disabled = isYear || isNone;
				if (yearInput) yearInput.disabled = isNone;
			}

			if (modeField) {
				modeField.addEventListener('change', syncFilterMode);
				syncFilterMode();
			}

			if (filterForm) {
				filterForm.addEventListener('submit', function (event) {
					const mode = modeField ? (modeField.value || 'none') : 'none';
					let errorMessage = '';

					if (mode === 'day') {
						if (!dayInput || !dayInput.value) {
							errorMessage = 'Tanggal wajib dipilih untuk mode Spesifik Hari.';
						} else if (!monthInput || !monthInput.value) {
							errorMessage = 'Bulan wajib dipilih untuk mode Spesifik Hari.';
						} else if (!yearInput || !yearInput.value) {
							errorMessage = 'Tahun wajib dipilih untuk mode Spesifik Hari.';
						}
					} else if (mode === 'month') {
						if (!monthInput || !monthInput.value) {
							errorMessage = 'Bulan wajib dipilih untuk mode Spesifik Bulan.';
						} else if (!yearInput || !yearInput.value) {
							errorMessage = 'Tahun wajib dipilih untuk mode Spesifik Bulan.';
						}
					} else if (mode === 'year') {
						if (!yearInput || !yearInput.value) {
							errorMessage = 'Tahun wajib dipilih untuk mode Spesifik Tahun.';
						}
					}

					if (errorMessage !== '') {
						event.preventDefault();
						if (window.KedaiKlikNotify && typeof window.KedaiKlikNotify.show === 'function') {
							window.KedaiKlikNotify.show({
								type: 'warning',
								title: 'Filter belum lengkap',
								message: errorMessage,
								duration: 3600,
							});
						}
					}
				});
			}

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
