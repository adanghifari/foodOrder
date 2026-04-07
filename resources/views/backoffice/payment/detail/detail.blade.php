@if (!empty($selectedPayment))
	@php
		$modalStatus = strtoupper((string) ($selectedPayment['paymentStatus'] ?? 'PENDING'));
		$modalStatusLabel = match ($modalStatus) {
			'PAID', 'SUCCESS', 'SETTLEMENT' => 'Lunas',
			'PENDING', 'UNPAID' => 'Menunggu Pembayaran',
			'FAILED', 'DENY' => 'Gagal',
			'CANCELED', 'CANCEL' => 'Dibatalkan',
			'EXPIRED', 'EXPIRE' => 'Kedaluwarsa',
			default => ucfirst(strtolower(str_replace('_', ' ', $modalStatus))),
		};

		$orderStatusRaw = strtoupper((string) ($selectedPayment['orderStatus'] ?? 'UNKNOWN'));
		$orderStatusLabel = match ($orderStatusRaw) {
			'CONFIRMED' => 'Terkonfirmasi',
			'IN_QUEUE' => 'Dalam Antrian',
			'IN_PROGRESS' => 'Sedang Diproses',
			'DELIVERED' => 'Sudah Diantar',
			'CANCELED', 'CANCEL' => 'Dibatalkan',
			'COMPLETED' => 'Selesai',
			default => ucfirst(strtolower(str_replace('_', ' ', $orderStatusRaw))),
		};

		$paymentTypeRaw = strtolower(trim((string) ($selectedPayment['paymentType'] ?? '')));
		$paymentPayload = is_array($selectedPayment['paymentPayload'] ?? null) ? $selectedPayment['paymentPayload'] : [];

		$paymentMethodLabel = match ($paymentTypeRaw) {
			'bank_transfer' => 'Bank Transfer',
			'qris' => 'QRIS',
			'echannel' => 'Mandiri Bill Payment',
			'cstore' => 'Convenience Store',
			'gopay' => 'GoPay',
			'shopeepay' => 'ShopeePay',
			'credit_card' => 'Kartu Kredit',
			'' => '-',
			default => strtoupper(str_replace('_', ' ', $paymentTypeRaw)),
		};

		$paymentMethodDetails = [];
		$vaNumbers = is_array($paymentPayload['va_numbers'] ?? null) ? $paymentPayload['va_numbers'] : [];

		foreach ($vaNumbers as $va) {
			$bank = strtoupper((string) ($va['bank'] ?? ''));
			$number = (string) ($va['va_number'] ?? '');

			if ($bank !== '' && $number !== '') {
				$paymentMethodDetails[] = $bank . ': ' . $number;
			}
		}

		$permataVa = (string) ($paymentPayload['permata_va_number'] ?? '');
		if ($permataVa !== '') {
			$paymentMethodDetails[] = 'PERMATA: ' . $permataVa;
		}

		$store = strtoupper((string) ($paymentPayload['store'] ?? ''));
		$paymentCode = (string) ($paymentPayload['payment_code'] ?? '');
		if ($store !== '' && $paymentCode !== '') {
			$paymentMethodDetails[] = $store . ' CODE: ' . $paymentCode;
		}

		$billerCode = (string) ($paymentPayload['biller_code'] ?? '');
		$billKey = (string) ($paymentPayload['bill_key'] ?? '');
		if ($billerCode !== '' && $billKey !== '') {
			$paymentMethodDetails[] = 'Biller Code: ' . $billerCode . ', Bill Key: ' . $billKey;
		}

		if (empty($paymentMethodDetails)) {
			$paymentMethodDetails[] = '-';
		}

		$orderCreatedAtRaw = (string) ($selectedPayment['createdAt'] ?? '');
		$orderCreatedAtLabel = '-';
		$paidAtRaw = (string) ($selectedPayment['paidAt'] ?? '');
		$paidAtLabel = '-';

		if ($orderCreatedAtRaw !== '') {
			try {
				$orderCreatedAtLabel = \Carbon\Carbon::parse($orderCreatedAtRaw)->format('d M Y, H:i:s');
			} catch (\Throwable $exception) {
				$orderCreatedAtLabel = $orderCreatedAtRaw;
			}
		}

		if ($paidAtRaw !== '') {
			try {
				$paidAtLabel = \Carbon\Carbon::parse($paidAtRaw)->format('d M Y, H:i:s');
			} catch (\Throwable $exception) {
				$paidAtLabel = $paidAtRaw;
			}
		}

		$modalBadgeClass = in_array($modalStatus, ['PAID', 'SUCCESS'], true)
			? 'bg-emerald-100 text-emerald-700'
			: (in_array($modalStatus, ['FAILED', 'CANCELED', 'EXPIRED'], true) ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700');
	@endphp

	<div class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm"></div>
	<div class="fixed inset-0 z-50 flex items-center justify-center p-4">
		<div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
			<div class="px-5 py-4 border-b border-slate-200 flex items-start justify-between gap-3">
				<div>
					<h3 class="text-lg font-extrabold text-[var(--rich-black)]">Detail Pembayaran</h3>
					<p class="text-sm font-semibold text-slate-500">{{ $selectedPayment['displayId'] ?? '-' }}</p>
				</div>
				<a href="/backoffice/pembayaran" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold px-3 py-1.5 transition">Tutup</a>
			</div>

			<div class="p-5 space-y-4 max-h-[72vh] overflow-y-auto">
				<section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
					<div class="overflow-x-auto">
						<table class="w-full min-w-[640px] text-left">
							<thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
								<tr>
									<th class="px-4 py-3 font-bold">Informasi</th>
									<th class="px-4 py-3 font-bold">Nilai</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-200 text-sm">
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Nama Pemesan</td>
									<td class="px-4 py-3 text-slate-800">{{ $selectedPayment['customerName'] ?? '-' }}</td>
								</tr>
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Email</td>
									<td class="px-4 py-3 text-slate-800">{{ $selectedPayment['customerEmail'] ?? '-' }}</td>
								</tr>
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Nomor Meja</td>
									<td class="px-4 py-3 text-slate-800">{{ (int) ($selectedPayment['tableNumber'] ?? 0) }}</td>
								</tr>
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Status Pembayaran</td>
									<td class="px-4 py-3">
										<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $modalBadgeClass }}">{{ $modalStatusLabel }}</span>
									</td>
								</tr>
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Metode Pembayaran</td>
									<td class="px-4 py-3 text-slate-800">{{ $paymentMethodLabel }}</td>
								</tr>
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Detail Metode</td>
									<td class="px-4 py-3 text-slate-800">
										<div class="space-y-1">
											@foreach ($paymentMethodDetails as $detail)
												<p>{{ $detail }}</p>
											@endforeach
										</div>
									</td>
								</tr>
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Status Pesanan</td>
									<td class="px-4 py-3 text-slate-800">{{ $orderStatusLabel }}</td>
								</tr>
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Waktu Buat Pesanan</td>
									<td class="px-4 py-3 text-slate-800">{{ $orderCreatedAtLabel }}</td>
								</tr>
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Waktu Bayar</td>
									<td class="px-4 py-3 text-slate-800">{{ $paidAtLabel }}</td>
								</tr>
								<tr>
									<td class="px-4 py-3 font-semibold text-slate-600">Total Pembayaran</td>
									<td class="px-4 py-3 text-[var(--philippine-bronze)] font-extrabold">Rp {{ number_format((float) ($selectedPayment['totalPrice'] ?? 0), 0, ',', '.') }}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</section>

				<section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
					<div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
						<h4 class="text-sm font-extrabold text-slate-700">Rincian Menu</h4>
					</div>
					<div class="overflow-x-auto">
						<table class="w-full min-w-[640px] text-left">
							<thead class="bg-white border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
								<tr>
									<th class="px-4 py-3 font-bold">No</th>
									<th class="px-4 py-3 font-bold">Nama Menu</th>
									<th class="px-4 py-3 font-bold">Harga</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-200 text-sm text-slate-700">
								@forelse (($selectedPayment['items'] ?? []) as $index => $item)
									@php
										$itemName = is_array($item) ? ($item['name'] ?? '-') : (is_object($item) ? ($item->name ?? '-') : '-');
										$itemPrice = is_array($item) ? ($item['price'] ?? 0) : (is_object($item) ? ($item->price ?? 0) : 0);
									@endphp
									<tr>
										<td class="px-4 py-3 font-semibold text-slate-500">{{ $index + 1 }}</td>
										<td class="px-4 py-3 font-semibold text-slate-800">{{ $itemName }}</td>
										<td class="px-4 py-3">Rp {{ number_format((float) $itemPrice, 0, ',', '.') }}</td>
									</tr>
								@empty
									<tr>
										<td colspan="3" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Tidak ada item.</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</section>
			</div>
		</div>
	</div>
@endif
