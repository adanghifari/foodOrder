<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    private array $allowedStatuses = ['CONFIRMED', 'SEATED', 'COMPLETED', 'NO_SHOW'];
    private const PAID_STATUSES = ['PAID', 'SUCCESS', 'SETTLEMENT'];

    public function indexPage()
    {
        $timezone = 'Asia/Jakarta';
        $todayStart = Carbon::now($timezone)->startOfDay();
        $todayEnd = Carbon::now($timezone)->endOfDay();

        $bookings = Booking::with('customer')
            ->orderBy('booking_start_at', 'asc')
            ->orderBy('_id', 'desc')
            ->get()
            ->map(function (Booking $booking) {
                return $this->buildBookingPayload($booking);
            })
            ->values();

        $bookingDineInOrders = Order::with('customer')
            ->where('order_type', 'booking_dine_in')
            ->whereNull('order_deleted_at')
            ->whereIn('payment_status', self::PAID_STATUSES)
            ->orderBy('booking_start_at', 'asc')
            ->orderBy('_id', 'desc')
            ->get()
            ->map(function (Order $order) {
                return $this->buildBookingPayloadFromOrder($order);
            })
            ->values();

        $bookings = $bookings->concat($bookingDineInOrders)
            ->sortBy('bookingStartAt')
            ->values();

        $todayBookings = $bookings->filter(function (array $booking) use ($todayStart, $todayEnd, $timezone) {
            $startAt = (string) ($booking['bookingStartAt'] ?? '');
            if ($startAt === '') {
                return false;
            }

            try {
                $bookingStart = Carbon::parse($startAt)->setTimezone($timezone);
            } catch (\Throwable $exception) {
                return false;
            }

            return $bookingStart->between($todayStart, $todayEnd);
        })->values();

        $upcomingBookings = $bookings->filter(function (array $booking) use ($todayEnd, $timezone) {
            $startAt = (string) ($booking['bookingStartAt'] ?? '');
            if ($startAt === '') {
                return false;
            }

            try {
                $bookingStart = Carbon::parse($startAt)->setTimezone($timezone);
            } catch (\Throwable $exception) {
                return false;
            }

            return $bookingStart->gt($todayEnd);
        })->values();

        $previousBookings = $bookings->filter(function (array $booking) use ($todayStart, $timezone) {
            $startAt = (string) ($booking['bookingStartAt'] ?? '');
            if ($startAt === '') {
                return false;
            }

            try {
                $bookingStart = Carbon::parse($startAt)->setTimezone($timezone);
            } catch (\Throwable $exception) {
                return false;
            }

            return $bookingStart->lt($todayStart);
        })->values();

        return view('backoffice.booking.index', [
            'todayBookings' => $todayBookings,
            'upcomingBookings' => $upcomingBookings,
            'previousBookings' => $previousBookings,
            'statusOptions' => $this->allowedStatuses,
            'bookingDateLabel' => $todayStart->translatedFormat('d M Y'),
            'summary' => [
                'today' => $todayBookings->count(),
                'upcoming' => $upcomingBookings->count(),
                'seated' => $todayBookings->where('status', 'SEATED')->count(),
                'completed' => $todayBookings->where('status', 'COMPLETED')->count(),
            ],
        ]);
    }

    public function updateStatusPage(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:' . implode(',', $this->allowedStatuses),
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $booking = Booking::find($id);
        if (! $booking) {
            return redirect()->back()->with('error', 'Booking tidak ditemukan.');
        }

        $status = strtoupper((string) $request->input('status'));
        $payload = ['status' => $status];

        if ($status === 'SEATED') {
            $payload['checked_in_at'] = now();
        }

        if (in_array($status, ['COMPLETED', 'NO_SHOW'], true)) {
            $payload['completed_at'] = now();
        }

        $booking->update($payload);

        return redirect()->back()->with('success', 'Status booking berhasil diperbarui.');
    }

    private function buildBookingPayload(Booking $booking): array
    {
        $customer = $booking->customer;
        $customerName = (string) ($customer?->name ?? $customer?->username ?? '-');
        $customerEmail = (string) ($customer?->email ?? $customer?->username ?? '-');

        return [
            'bookingId' => (string) $booking->_id,
            'displayId' => 'BKG-' . strtoupper(substr((string) $booking->_id, -6)),
            'sourceType' => 'BOOKING',
            'tableNumber' => (int) ($booking->table_number ?? 0),
            'status' => strtoupper((string) ($booking->status ?? 'UNKNOWN')),
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'durationHours' => (int) ($booking->duration_hours ?? 0),
            'extraCharge' => (int) ($booking->extra_charge ?? 0),
            'totalBookingCharge' => (int) ($booking->total_booking_charge ?? 0),
            'bookingStartAt' => optional($booking->booking_start_at)?->toIso8601String(),
            'bookingEndAt' => optional($booking->booking_end_at)?->toIso8601String(),
        ];
    }

    private function buildBookingPayloadFromOrder(Order $order): array
    {
        $customer = $order->customer;
        $customerName = (string) ($customer?->name ?? $customer?->username ?? '-');
        $customerEmail = (string) ($customer?->email ?? $customer?->username ?? '-');

        $mappedStatus = match (strtoupper((string) ($order->status ?? ''))) {
            'PENDING_PAYMENT' => 'CONFIRMED',
            'CONFIRMED' => 'CONFIRMED',
            'IN_QUEUE', 'IN_PROGRESS' => 'SEATED',
            'DELIVERED' => 'COMPLETED',
            default => strtoupper((string) ($order->status ?? 'UNKNOWN')),
        };

        $bookingStartAt = $order->booking_start_at;
        $durationHours = (int) ($order->duration_hours ?? 0);
        $bookingEndAt = null;

        if ($bookingStartAt && $durationHours > 0) {
            try {
                $bookingEndAt = \Illuminate\Support\Carbon::parse($bookingStartAt)
                    ->addHours($durationHours)
                    ->toIso8601String();
            } catch (\Throwable $e) {
                $bookingEndAt = null;
            }
        }

        return [
            'bookingId' => (string) $order->_id,
            'displayId' => 'ORD-' . strtoupper(substr((string) $order->_id, -6)),
            'sourceType' => 'BOOKING_DINE_IN',
            'tableNumber' => (int) ($order->table_number ?? 0),
            'status' => $mappedStatus,
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'durationHours' => $durationHours,
            'extraCharge' => (int) ($order->total_price ?? 0),
            'totalBookingCharge' => (int) ($order->total_price ?? 0),
            'bookingStartAt' => $bookingStartAt ? (is_string($bookingStartAt) ? $bookingStartAt : $bookingStartAt->toIso8601String()) : null,
            'bookingEndAt' => $bookingEndAt,
        ];
    }
}
