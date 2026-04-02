<?php

namespace App\Http\Controllers\Frontliner\Web;

use App\Http\Controllers\Controller;
use App\Domains\Table\Services\TableService;
use Illuminate\Http\Request;

class TableController extends Controller
{
	public function __construct(private readonly TableService $tableService)
	{
	}

	public function checkTableAvailability(int $tableId)
	{
		if (! $this->tableService->isKnownTable($tableId)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Table not found'
			], 404);
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Table availability retrieved',
			'data' => [
				'tableId' => $tableId,
				'available' => $this->tableService->isTableAvailable($tableId),
			]
		]);
	}

	public function clearTableSession(Request $request)
	{
		if (! $this->tableService->clearTableSession($request)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Session is not available'
			], 400);
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Table session cleared',
			'data' => [
				'tableId' => null,
			],
		]);
	}

}
