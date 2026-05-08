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
		return $this->storeTableAndRedirect($request, $tableId, '/menu');
	}

	public function accessFromQueryParam(Request $request): RedirectResponse
	{
		$validated = $request->validate([
			'tableId' => 'required|integer|min:1|max:999',
			'return_to' => 'nullable|string|max:255',
		]);

		$returnTo = $this->sanitizeReturnPath((string) ($validated['return_to'] ?? ''));

		return $this->storeTableAndRedirect($request, (int) $validated['tableId'], $returnTo);
	}

	private function storeTableAndRedirect(Request $request, int $tableId, string $redirectPath = '/menu'): RedirectResponse
	{
		if (! $this->tableService->isKnownTable($tableId)) {
			abort(404, 'Table not found');
		}

		$this->tableService->storeTableSession($request, $tableId);

		return redirect($redirectPath);
	}

	private function sanitizeReturnPath(string $path): string
	{
		$trimmed = trim($path);
		if ($trimmed === '') {
			return '/menu';
		}

		if (!str_starts_with($trimmed, '/')) {
			return '/menu';
		}

		if (str_starts_with($trimmed, '//')) {
			return '/menu';
		}

		return $trimmed;
	}
}
