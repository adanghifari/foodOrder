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
					</select>
				</div>
			</div>
		</article>

		<section id="payment-grid" class="grid grid-cols-1 xl:grid-cols-2 gap-4">
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

				<article
					class="payment-card rounded-2xl border border-slate-200 bg-white shadow-sm p-4 md:p-5"
					data-order-id="{{ strtolower((string) ($payment['displayId'] ?? '')) }}"
					data-customer="{{ strtolower((string) ($payment['customerName'] ?? '')) }}"
					data-email="{{ strtolower((string) ($payment['customerEmail'] ?? '')) }}"
					data-status-family="{{ $statusFamily }}"
					data-total="{{ (float) ($payment['totalPrice'] ?? 0) }}"
				>
					<div class="flex items-start justify-between gap-3">
						<div>
							<h3 class="text-base font-extrabold text-[var(--rich-black)]">{{ $payment['displayId'] ?? '-' }}</h3>
							<p class="text-xs text-slate-500">Nama: <span class="font-semibold text-slate-700">{{ $payment['customerName'] ?? '-' }}</span></p>
							<p class="text-xs text-slate-500 mt-0.5">Email: <span class="font-semibold text-slate-700">{{ $payment['customerEmail'] ?? '-' }}</span></p>
						</div>
						<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $paymentBadgeClass }}">{{ $paymentStatusLabel }}</span>
					</div>

					<div class="mt-3 grid grid-cols-1 gap-2">
						<div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
							<p class="text-[11px] text-slate-500 font-bold uppercase">No Meja</p>
							<p class="text-sm font-extrabold text-slate-700">{{ (int) ($payment['tableNumber'] ?? 0) }}</p>
						</div>
					</div>

					<div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
						<div>
							<p class="text-xs text-slate-500">Total Bayar</p>
							<p class="text-base font-extrabold text-[var(--philippine-bronze)]">Rp {{ number_format((float) ($payment['totalPrice'] ?? 0), 0, ',', '.') }}</p>
						</div>
						<a href="/backoffice/pembayaran?detail={{ urlencode((string) ($payment['orderId'] ?? '')) }}" class="inline-flex items-center rounded-lg border border-[#2563EB] bg-white hover:bg-blue-50 text-[#2563EB] text-xs font-extrabold px-3 py-2 transition">Lihat Detail</a>
					</div>
				</article>
			@empty
				<article class="xl:col-span-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
					<p class="text-sm font-semibold text-slate-500">Belum ada data pembayaran.</p>
				</article>
			@endforelse

			<article id="payment-filter-empty" class="hidden xl:col-span-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
				<p class="text-sm font-semibold text-slate-500">Data pembayaran tidak ditemukan untuk filter ini.</p>
			</article>
		</section>
	</section>

	<script>
		(function () {
			const grid = document.getElementById('payment-grid');
			const searchInput = document.getElementById('payment-search');
			const sortSelect = document.getElementById('payment-sort');
			const tabs = Array.from(document.querySelectorAll('.payment-status-tab'));
			const emptyState = document.getElementById('payment-filter-empty');

			if (!grid) {
				return;
			}

			const cards = Array.from(grid.querySelectorAll('.payment-card'));
			if (cards.length === 0) {
				return;
			}

			const baseOrder = new Map(cards.map((card, index) => [card, index]));
			let activeStatus = 'all';

			function normalize(text) {
				return String(text || '').toLowerCase().trim();
			}

			function sortCards(list) {
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

				return list;
			}

			function applyFilters() {
				const keyword = normalize(searchInput ? searchInput.value : '');

				let visible = cards.filter(function (card) {
					const orderId = normalize(card.dataset.orderId);
					const customer = normalize(card.dataset.customer);
					const email = normalize(card.dataset.email);
					const status = normalize(card.dataset.statusFamily);

					const bySearch = keyword === '' || orderId.includes(keyword) || customer.includes(keyword) || email.includes(keyword);
					const byStatus = activeStatus === 'all' || status === activeStatus;

					return bySearch && byStatus;
				});

				visible = sortCards(visible);

				cards.forEach(function (card) {
					card.classList.add('hidden');
				});

				visible.forEach(function (card) {
					card.classList.remove('hidden');
					grid.appendChild(card);
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

	@if (!empty($selectedPayment))
		@php
			$modalStatus = strtoupper((string) ($selectedPayment['paymentStatus'] ?? 'PENDING'));
			$modalStatusLabel = in_array($modalStatus, ['PAID', 'SUCCESS'], true)
				? 'Lunas'
				: (in_array($modalStatus, ['FAILED', 'CANCELED', 'EXPIRED'], true) ? 'Gagal' : 'Menunggu');
		@endphp

		<div class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm"></div>
		<div class="fixed inset-0 z-50 flex items-center justify-center p-4">
			<div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
				<div class="px-5 py-4 border-b border-slate-200 flex items-start justify-between gap-3">
					<div>
						<h3 class="text-lg font-extrabold text-[var(--rich-black)]">Detail Pembayaran</h3>
						<p class="text-sm font-semibold text-slate-500">{{ $selectedPayment['displayId'] ?? '-' }}</p>
					</div>
					<a href="/backoffice/pembayaran" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold px-3 py-1.5 transition">Tutup</a>
				</div>

				<div class="p-5 space-y-4 max-h-[72vh] overflow-y-auto">
					<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
						<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
							<p class="text-xs font-bold uppercase tracking-wide text-slate-500">Nama Pemesan</p>
							<p class="mt-1 text-sm font-semibold text-slate-700">{{ $selectedPayment['customerName'] ?? '-' }}</p>
						</div>
						<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
							<p class="text-xs font-bold uppercase tracking-wide text-slate-500">Email</p>
							<p class="mt-1 text-sm font-semibold text-slate-700">{{ $selectedPayment['customerEmail'] ?? '-' }}</p>
						</div>
						<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
							<p class="text-xs font-bold uppercase tracking-wide text-slate-500">Status Pembayaran</p>
							<p class="mt-1 text-sm font-semibold text-slate-700">{{ $modalStatusLabel }}</p>
						</div>
						<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
							<p class="text-xs font-bold uppercase tracking-wide text-slate-500">Status Pesanan</p>
							<p class="mt-1 text-sm font-semibold text-slate-700">{{ str_replace('_', ' ', (string) ($selectedPayment['orderStatus'] ?? '-')) }}</p>
						</div>
					</div>

					<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
						<p class="text-xs font-bold uppercase tracking-wide text-slate-500">Rincian Item</p>
						<ul class="mt-2 space-y-2">
							@forelse (($selectedPayment['items'] ?? []) as $item)
								@php
									$itemName = is_array($item) ? ($item['name'] ?? '-') : (is_object($item) ? ($item->name ?? '-') : '-');
									$itemPrice = is_array($item) ? ($item['price'] ?? 0) : (is_object($item) ? ($item->price ?? 0) : 0);
								@endphp
								<li class="flex items-center justify-between text-sm text-slate-700 gap-3">
									<span class="font-semibold">{{ $itemName }}</span>
									<span>Rp {{ number_format((float) $itemPrice, 0, ',', '.') }}</span>
								</li>
							@empty
								<li class="text-sm text-slate-500">Tidak ada item.</li>
							@endforelse
						</ul>
					</div>

					<div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3">
						<p class="text-sm text-slate-500">Total Pembayaran</p>
						<p class="text-base font-extrabold text-[var(--philippine-bronze)]">Rp {{ number_format((float) ($selectedPayment['totalPrice'] ?? 0), 0, ',', '.') }}</p>
					</div>
				</div>
			</div>
		</div>
	@endif
</x-backoffice.layout>
