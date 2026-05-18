<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Domains\Booking\Services\BookingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function availability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bookingStartAt' => 'required|string',
            'durationHours' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $result = $this->bookingService->getAvailability(
            (string) $request->input('bookingStartAt'),
            (int) $request->input('durationHours')
        );

        if (! $result['ok']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'] ?? 'Gagal memeriksa ketersediaan meja.',
            ], (int) ($result['status'] ?? 422));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking availability retrieved',
            'data' => $result['data'],
        ]);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tableNumber' => 'required|integer|min:1',
            'bookingStartAt' => 'required|string',
            'durationHours' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $result = $this->bookingService->createBooking(
            (string) $request->user()->_id,
            (int) $request->input('tableNumber'),
            (string) $request->input('bookingStartAt'),
            (int) $request->input('durationHours'),
            $request->input('notes')
        );

        if (! $result['ok']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'] ?? 'Booking gagal dibuat.',
            ], (int) ($result['status'] ?? 422));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking created',
            'data' => $this->bookingService->buildBookingResponse($result['booking']),
        ], 201);
    }

    public function myBookings(Request $request)
    {
        $data = $this->bookingService->myBookings((string) $request->user()->_id);

        return response()->json([
            'status' => 'success',
            'message' => 'Bookings retrieved',
            'data' => $data,
        ]);
    }
}
