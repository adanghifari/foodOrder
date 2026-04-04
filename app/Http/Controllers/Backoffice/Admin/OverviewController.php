<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;

class OverviewController extends Controller
{
    public function get()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Overview retrieved',
            'data' => [
                'menus' => MenuItem::count(),
                'orders' => Order::count(),
                'users' => User::count(),
            ],
        ]);
    }
}