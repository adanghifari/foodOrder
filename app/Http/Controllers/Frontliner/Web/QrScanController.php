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

	public function accessTakeAwayRoute(Request $request): RedirectResponse
	{
		return $this->storeTakeAwayAndRedirect($request, '/menu');
	}

	public function accessFromQueryParam(Request $request): RedirectResponse
	{
		$validated = $request->validate([
			'tableId' => 'nullable',
			'mode' => 'nullable|string|in:take_away',
			'return_to' => 'nullable|string|max:255',
		]);

		$returnTo = $this->sanitizeReturnPath((string) ($validated['return_to'] ?? ''));
		$mode = strtolower((string) ($validated['mode'] ?? ''));
		$tableIdRaw = strtolower(trim((string) ($validated['tableId'] ?? '')));

		if ($mode === 'take_away' || $tableIdRaw === 'take_away') {
			return $this->storeTakeAwayAndRedirect($request, $returnTo);
		}

		$tableId = (int) $request->input('tableId', 0);
		if ($tableId <= 0 || $tableId > 999) {
			return redirect('/kedai/scan?return_to=' . urlencode($returnTo))
				->with('error', 'QR tidak valid. Silakan scan ulang.');
		}

		return $this->storeTableAndRedirect($request, $tableId, $returnTo);
	}

	private function storeTableAndRedirect(Request $request, int $tableId, string $redirectPath = '/menu'): RedirectResponse
	{
		if (! $this->tableService->isKnownTable($tableId)) {
			abort(404, 'Table not found');
		}

		$this->tableService->storeTableSession($request, $tableId);

		return redirect($redirectPath);
	}

	private function storeTakeAwayAndRedirect(Request $request, string $redirectPath = '/menu'): RedirectResponse
	{
		$this->tableService->storeTakeAwaySession($request);
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
