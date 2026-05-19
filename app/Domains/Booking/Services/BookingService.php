<?php

namespace App\Domains\Booking\Services;

use App\Models\Booking;
use App\Models\Order;
use App\Domains\Table\Services\TableService;
use App\Support\TableGuard;
use Illuminate\Support\Carbon;

class BookingService
{
    private const ACTIVE_BOOKING_STATUSES = ['PENDING', 'CONFIRMED', 'SEATED'];
    private const ACTIVE_BOOKING_ORDER_STATUSES = ['PENDING_PAYMENT', 'CONFIRMED', 'IN_QUEUE', 'IN_PROGRESS'];

    public function __construct(
        private readonly TableService $tableService
    ) {
    }

    public function getAvailability(string $startAtRaw, int $durationHours): array
    {
        $timeWindow = $this->buildTimeWindow($startAtRaw, $durationHours);
        if (! $timeWindow['ok']) {
            return $timeWindow;
        }

        /** @var Carbon $startAt */
        $startAt = $timeWindow['start_at'];
        /** @var Carbon $endAt */
        $endAt = $timeWindow['end_at'];
        $preBlockHours = max(0, (int) config('booking.pre_block_hours', 2));
        $expandedEndAt = $endAt->copy()->addHours($preBlockHours);

        $knownTables = collect(config('tables.known_table_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($knownTables->isEmpty()) {
            $knownTables = collect(range(
                (int) config('tables.min_table_id', 1),
                (int) config('tables.max_table_id', 100)
            ));
        }

        $startWithCooldownBoundary = $startAt->copy()->subHours($preBlockHours);

        $conflictingBookingTableIds = Booking::whereIn('table_number', $knownTables->toArray())
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->where('booking_start_at', '<', $expandedEndAt)
            ->where('booking_end_at', '>', $startWithCooldownBoundary)
            ->pluck('table_number')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $conflictingBookingOrderTableIds = $this->getConflictingBookingOrderTableIds(
            $startAt,
            $expandedEndAt,
            $knownTables->toArray()
        );

        $onSpotBlockingTableIds = collect(
            $this->tableService->blockingTableIdsForBookingSlot(
                $startAt,
                $endAt,
                $knownTables->toArray()
            )
        )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $unavailableTableIds = $conflictingBookingTableIds
            ->merge($conflictingBookingOrderTableIds)
            ->merge($onSpotBlockingTableIds)
            ->unique()
            ->values();

        $availableTableIds = $knownTables
            ->reject(fn ($tableId) => $unavailableTableIds->contains((int) $tableId))
            ->values();

        return [
            'ok' => true,
            'data' => [
                'bookingStartAt' => $startAt->toIso8601String(),
                'bookingEndAt' => $endAt->toIso8601String(),
                'durationHours' => $durationHours,
                'extraCharge' => $this->calculateExtraCharge($durationHours),
                'availableTables' => $availableTableIds->toArray(),
                'unavailableTables' => $unavailableTableIds->toArray(),
            ],
        ];
    }

    public function createBooking(string $customerId, int $tableNumber, string $startAtRaw, int $durationHours, ?string $notes = null): array
    {
        if (! TableGuard::isKnownTable($tableNumber)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Nomor meja tidak terdaftar.',
            ];
        }

        $timeWindow = $this->buildTimeWindow($startAtRaw, $durationHours);
        if (! $timeWindow['ok']) {
            return $timeWindow;
        }

        /** @var Carbon $startAt */
        $startAt = $timeWindow['start_at'];
        /** @var Carbon $endAt */
        $endAt = $timeWindow['end_at'];
        $preBlockHours = max(0, (int) config('booking.pre_block_hours', 2));
        $expandedEndAt = $endAt->copy()->addHours($preBlockHours);

        $startWithCooldownBoundary = $startAt->copy()->subHours($preBlockHours);

        $hasBookingConflict = Booking::where('table_number', $tableNumber)
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->where('booking_start_at', '<', $expandedEndAt)
            ->where('booking_end_at', '>', $startWithCooldownBoundary)
            ->exists();

        $hasBookingOrderConflict = $this->getConflictingBookingOrderTableIds(
            $startAt,
            $expandedEndAt,
            [$tableNumber]
        )->isNotEmpty();

        if ($hasBookingConflict || $hasBookingOrderConflict) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Meja tidak tersedia pada jam yang dipilih.',
            ];
        }

        $extraCharge = $this->calculateExtraCharge($durationHours);

        $booking = Booking::create([
            'customer_id' => $customerId,
            'table_number' => $tableNumber,
            'booking_start_at' => $startAt,
            'booking_end_at' => $endAt,
            'duration_hours' => $durationHours,
            'extra_charge' => $extraCharge,
            'total_booking_charge' => $extraCharge,
            'status' => 'CONFIRMED',
            'notes' => trim((string) ($notes ?? '')),
        ]);

        return [
            'ok' => true,
            'booking' => $booking,
        ];
    }

    public function myBookings(string $customerId)
    {
        return Booking::with('customer')
            ->where('customer_id', $customerId)
            ->orderBy('booking_start_at', 'asc')
            ->orderBy('_id', 'desc')
            ->get()
            ->map(function (Booking $booking) {
                return $this->buildBookingResponse($booking);
            });
    }

    public function buildBookingResponse(Booking $booking): array
    {
        return [
            'bookingId' => (string) $booking->_id,
            'tableNumber' => (int) ($booking->table_number ?? 0),
            'status' => (string) ($booking->status ?? 'UNKNOWN'),
            'bookingStartAt' => optional($booking->booking_start_at)?->toIso8601String(),
            'bookingEndAt' => optional($booking->booking_end_at)?->toIso8601String(),
            'durationHours' => (int) ($booking->duration_hours ?? 0),
            'extraCharge' => (int) ($booking->extra_charge ?? 0),
            'totalBookingCharge' => (int) ($booking->total_booking_charge ?? 0),
            'notes' => (string) ($booking->notes ?? ''),
            'createdAt' => optional($booking->created_at)?->toIso8601String(),
        ];
    }

    private function buildTimeWindow(string $startAtRaw, int $durationHours): array
    {
        $minDuration = max(1, (int) config('booking.min_duration_hours', 2));
        if ($durationHours < $minDuration) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Durasi booking minimal ' . $minDuration . ' jam.',
            ];
        }

        try {
            $startAt = Carbon::parse($startAtRaw);
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Format waktu booking tidak valid.',
            ];
        }

        $startAtMinute = (int) $startAt->copy()->timezone('Asia/Jakarta')->format('i');
        if (! in_array($startAtMinute, [0], true)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Jam booking harus tepat per jam (contoh: 10:00, 12:00).',
            ];
        }

        $hour = (int) $startAt->copy()->timezone('Asia/Jakarta')->format('G');
        if ($hour % 2 !== 0) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Jam booking tersedia setiap 2 jam (contoh: 08:00, 10:00, 12:00).',
            ];
        }

        $openHour = (int) config('booking.open_hour', 8);
        $closeHour = (int) config('booking.close_hour', 20);
        $localStart = $startAt->copy()->timezone('Asia/Jakarta');
        if ($hour < $openHour || $hour >= $closeHour) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Jam booking hanya tersedia antara '
                    . str_pad((string) $openHour, 2, '0', STR_PAD_LEFT)
                    . ':00 sampai '
                    . str_pad((string) $closeHour, 2, '0', STR_PAD_LEFT)
                    . ':00.',
            ];
        }

        $endAt = $startAt->copy()->addHours($durationHours);
        $localEnd = $endAt->copy()->timezone('Asia/Jakarta');
        $closingAt = $localStart->copy()
            ->startOfDay()
            ->setHour($closeHour)
            ->setMinute(0)
            ->setSecond(0);
        if ($localEnd->gt($closingAt)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Booking melewati jam tutup. Pilih jam mulai lebih awal atau durasi lebih pendek.',
            ];
        }

        return [
            'ok' => true,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    private function calculateExtraCharge(int $durationHours): int
    {
        $threshold = max(1, (int) config('booking.extra_charge_threshold_hours', 4));
        $amount = max(0, (int) config('booking.extra_charge_amount', 40000));

        return $durationHours >= $threshold ? $amount : 0;
    }

    private function getConflictingBookingOrderTableIds(
        Carbon $startAt,
        Carbon $expandedEndAt,
        array $tableNumbers
    ) {
        $preBlockHours = max(0, (int) config('booking.pre_block_hours', 2));
        $startWithCooldownBoundary = $startAt->copy()->subHours($preBlockHours);

        $candidateOrders = Order::whereIn('table_number', $tableNumbers)
            ->where('order_type', 'booking_dine_in')
            ->whereNull('order_deleted_at')
            ->whereIn('status', self::ACTIVE_BOOKING_ORDER_STATUSES)
            ->whereNotNull('booking_start_at')
            ->get([
                'table_number',
                'booking_start_at',
                'booking_end_at',
                'duration_hours',
            ]);

        return $candidateOrders
            ->filter(function (Order $order) use ($startWithCooldownBoundary, $expandedEndAt) {
                $orderStartAt = $this->resolveBookingOrderStartAt($order);
                if (! $orderStartAt) {
                    return false;
                }

                $endAt = $this->resolveBookingOrderEndAt($order);
                if (! $endAt) {
                    return false;
                }

                // Overlap + cooldown rule:
                // existing.start < requested.end && existing.end > (requested.start - cooldown)
                return $orderStartAt->lt($expandedEndAt) && $endAt->gt($startWithCooldownBoundary);
            })
            ->pluck('table_number')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function resolveBookingOrderStartAt(Order $order): ?Carbon
    {
        if (! $order->booking_start_at) {
            return null;
        }

        try {
            return Carbon::parse($order->booking_start_at);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function resolveBookingOrderEndAt(Order $order): ?Carbon
    {
        if ($order->booking_end_at) {
            try {
                return Carbon::parse($order->booking_end_at);
            } catch (\Throwable $exception) {
                return null;
            }
        }

        if (! $order->booking_start_at) {
            return null;
        }

        $durationHours = (int) ($order->duration_hours ?? 0);
        if ($durationHours <= 0) {
            return null;
        }

        try {
            return Carbon::parse($order->booking_start_at)->addHours($durationHours);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
