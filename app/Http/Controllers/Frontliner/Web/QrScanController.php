<?php

namespace App\Http\Controllers\Frontliner\Web;

use App\Http\Controllers\Controller;
use App\Domains\Table\Services\TableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QrScanController extends Controller
{
	public function __construct(private readonly TableService $tableService)
	{
	}

	public function accessFromMenuRoute(Request $request, int $tableId): RedirectResponse
	{
		return $this->storeTableAndRedirect($request, $tableId);
	}

	public function accessFromQueryParam(Request $request): RedirectResponse
	{
		$validated = $request->validate([
			'tableId' => 'required|integer|min:1|max:999',
		]);

		return $this->storeTableAndRedirect($request, (int) $validated['tableId']);
	}

	private function storeTableAndRedirect(Request $request, int $tableId): RedirectResponse
	{
		if (! $this->tableService->isKnownTable($tableId)) {
			abort(404, 'Table not found');
		}

		$this->tableService->storeTableSession($request, $tableId);

		return redirect('/menu');
	}
}
