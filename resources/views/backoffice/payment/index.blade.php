<x-backoffice.layout pageTitle="Pembayaran">
	<section class="space-y-5">
		<article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 md:p-6">
			<h2 class="text-xl md:text-2xl font-extrabold text-[var(--rich-black)]">Daftar Pembayaran</h2>

			<div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
				<div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
					<p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Transaksi</p>
					<p class="mt-1 text-xl font-extrabold text-[var(--rich-black)]">{{ (int) ($summary['total'] ?? 0) }}</p>
				</div>
				<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5">
					<p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Lunas</p>
					<p class="mt-1 text-xl font-extrabold text-emerald-800">{{ (int) ($summary['paid'] ?? 0) }}</p>
				</div>
				<div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5">
					<p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Menunggu</p>
					<p class="mt-1 text-xl font-extrabold text-amber-800">{{ (int) ($summary['pending'] ?? 0) }}</p>
				</div>
				<div class="rounded-xl border border-rose-200 bg-rose-50 p-3.5">
					<p class="text-[11px] font-bold uppercase tracking-wide text-rose-700">Gagal</p>
					<p class="mt-1 text-xl font-extrabold text-rose-800">{{ (int) ($summary['failed'] ?? 0) }}</p>
				</div>
			</div>

			<div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-3">
				<div class="lg:col-span-8 space-y-3">
					<input id="payment-search" type="text" placeholder="Cari order ID / nama / email..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">

					<div class="flex flex-wrap gap-2">
						<button type="button" class="payment-status-tab inline-flex items-center rounded-xl border border-[#6A2B09] bg-[#6A2B09] text-[#FCB861] text-xs font-bold px-3 py-2 transition" data-status="all">Semua</button>
						<button type="button" class="payment-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="paid">Lunas</button>
						<button type="button" class="payment-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="pending">Menunggu</button>
						<button type="button" class="payment-status-tab inline-flex items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold px-3 py-2 transition" data-status="failed">Gagal</button>
					</div>
				</div>

				<div class="lg:col-span-4">
					<select id="payment-sort" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--rajah)]/70 focus:border-[var(--rajah)]">
						<option value="default">Sort by: Terbaru</option>
						<option value="total-asc">Total Termurah</option>
						<option value="total-desc">Total Termahal</option>
						<option value="status-asc">Status A-Z</option>
						<option value="status-desc">Status Z-A</option>
					</select>
				</div>
			</div>
		</article>

		<section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
			<div class="overflow-x-auto">
				<table class="w-full min-w-[980px] text-left">
					<thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
						<tr>
							<th class="px-4 py-3 font-bold">Order ID</th>
							<th class="px-4 py-3 font-bold">Nama</th>
							<th class="px-4 py-3 font-bold">Email</th>
							<th class="px-4 py-3 font-bold">No Meja</th>
							<th class="px-4 py-3 font-bold">Status</th>
							<th class="px-4 py-3 font-bold">Total Bayar</th>
							<th class="px-4 py-3 font-bold">Aksi</th>
						</tr>
					</thead>
					<tbody id="payment-table-body" class="divide-y divide-slate-200">
						@forelse (($payments ?? []) as $payment)
							@php
								$paymentStatus = strtoupper((string) ($payment['paymentStatus'] ?? 'PENDING'));
								$isPaid = in_array($paymentStatus, ['PAID', 'SUCCESS'], true);
								$isFailed = in_array($paymentStatus, ['FAILED', 'CANCELED', 'EXPIRED'], true);

								$statusFamily = $isPaid ? 'paid' : ($isFailed ? 'failed' : 'pending');

								$paymentStatusLabel = $isPaid
									? 'Lunas'
									: ($isFailed ? 'Gagal' : 'Menunggu');

								$paymentBadgeClass = $isPaid
									? 'bg-emerald-100 text-emerald-700'
									: ($isFailed ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700');
							@endphp
							<tr
								class="payment-row"
								data-order-id="{{ strtolower((string) ($payment['displayId'] ?? '')) }}"
								data-customer="{{ strtolower((string) ($payment['customerName'] ?? '')) }}"
								data-email="{{ strtolower((string) ($payment['customerEmail'] ?? '')) }}"
								data-status-family="{{ $statusFamily }}"
								data-total="{{ (float) ($payment['totalPrice'] ?? 0) }}"
							>
								<td class="px-4 py-3 text-sm font-extrabold text-[var(--rich-black)]">{{ $payment['displayId'] ?? '-' }}</td>
								<td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $payment['customerName'] ?? '-' }}</td>
								<td class="px-4 py-3 text-sm text-slate-700">{{ $payment['customerEmail'] ?? '-' }}</td>
								<td class="px-4 py-3 text-sm text-slate-700">{{ (int) ($payment['tableNumber'] ?? 0) }}</td>
								<td class="px-4 py-3">
									<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $paymentBadgeClass }}">{{ $paymentStatusLabel }}</span>
								</td>
								<td class="px-4 py-3 text-sm font-extrabold text-[var(--philippine-bronze)]">Rp {{ number_format((float) ($payment['totalPrice'] ?? 0), 0, ',', '.') }}</td>
								<td class="px-4 py-3">
									<div class="flex items-center gap-2">
										<a href="/backoffice/pembayaran?detail={{ urlencode((string) ($payment['orderId'] ?? '')) }}" class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Lihat Detail</a>
										<form
											method="POST"
											action="/backoffice/pembayaran/{{ urlencode((string) ($payment['orderId'] ?? '')) }}"
											data-notify-confirm
											data-confirm-type="warning"
											data-confirm-badge="Hapus Data"
											data-confirm-title="Hapus pembayaran?"
											data-confirm-message="Data pembayaran {{ $payment['displayId'] ?? '-' }} akan dihapus permanen."
											data-confirm-button="Ya, hapus"
											data-cancel-button="Batal"
										>
											@csrf
											@method('DELETE')
											<button type="submit" aria-label="Hapus pembayaran" title="Hapus pembayaran" class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 p-2 transition">
												<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
													<path d="M3 6h18"/>
													<path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/>
													<path d="M19 6l-1 14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1L5 6"/>
													<path d="M10 11v6"/>
													<path d="M14 11v6"/>
												</svg>
											</button>
										</form>
									</div>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="7" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">Belum ada data pembayaran.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<div id="payment-filter-empty" class="hidden px-4 py-8 text-center border-t border-slate-200">
				<p class="text-sm font-semibold text-slate-500">Data pembayaran tidak ditemukan untuk filter ini.</p>
			</div>
		</section>
	</section>

	<script>
		(function () {
			const tableBody = document.getElementById('payment-table-body');
			const searchInput = document.getElementById('payment-search');
			const sortSelect = document.getElementById('payment-sort');
			const tabs = Array.from(document.querySelectorAll('.payment-status-tab'));
			const emptyState = document.getElementById('payment-filter-empty');

			if (!tableBody) {
				return;
			}

			const rows = Array.from(tableBody.querySelectorAll('.payment-row'));
			if (rows.length === 0) {
				return;
			}

			const baseOrder = new Map(rows.map((row, index) => [row, index]));
			let activeStatus = 'all';

			function normalize(text) {
				return String(text || '').toLowerCase().trim();
			}

			function sortRows(list) {
				const mode = sortSelect ? sortSelect.value : 'default';

				if (mode === 'default') {
					return list.sort((a, b) => baseOrder.get(a) - baseOrder.get(b));
				}

				if (mode === 'total-asc') {
					return list.sort((a, b) => Number(a.dataset.total || 0) - Number(b.dataset.total || 0));
				}

				if (mode === 'total-desc') {
					return list.sort((a, b) => Number(b.dataset.total || 0) - Number(a.dataset.total || 0));
				}

				if (mode === 'status-asc') {
					return list.sort((a, b) => normalize(a.dataset.statusFamily).localeCompare(normalize(b.dataset.statusFamily)));
				}

				if (mode === 'status-desc') {
					return list.sort((a, b) => normalize(b.dataset.statusFamily).localeCompare(normalize(a.dataset.statusFamily)));
				}

				return list;
			}

			function applyFilters() {
				const keyword = normalize(searchInput ? searchInput.value : '');

				let visible = rows.filter(function (row) {
					const orderId = normalize(row.dataset.orderId);
					const customer = normalize(row.dataset.customer);
					const email = normalize(row.dataset.email);
					const status = normalize(row.dataset.statusFamily);

					const bySearch = keyword === '' || orderId.includes(keyword) || customer.includes(keyword) || email.includes(keyword);
					const byStatus = activeStatus === 'all' || status === activeStatus;

					return bySearch && byStatus;
				});

				visible = sortRows(visible);

				rows.forEach(function (row) {
					row.classList.add('hidden');
				});

				visible.forEach(function (row) {
					row.classList.remove('hidden');
					tableBody.appendChild(row);
				});

				if (emptyState) {
					emptyState.classList.toggle('hidden', visible.length !== 0);
				}
			}

			if (searchInput) {
				searchInput.addEventListener('input', applyFilters);
			}

			if (sortSelect) {
				sortSelect.addEventListener('change', applyFilters);
			}

			tabs.forEach(function (tab) {
				tab.addEventListener('click', function () {
					activeStatus = normalize(tab.dataset.status || 'all') || 'all';

					tabs.forEach(function (btn) {
						const selected = btn === tab;
						btn.classList.toggle('border-[#6A2B09]', selected);
						btn.classList.toggle('bg-[#6A2B09]', selected);
						btn.classList.toggle('text-[#FCB861]', selected);
						btn.classList.toggle('border-slate-300', !selected);
						btn.classList.toggle('bg-white', !selected);
						btn.classList.toggle('text-slate-700', !selected);
					});

					applyFilters();
				});
			});

			applyFilters();
		})();
	</script>

	@include('backoffice.payment.detail.detail', ['selectedPayment' => $selectedPayment])
</x-backoffice.layout>
