<?php

namespace App\Http\Controllers\Frontliner\Web;

use App\Http\Controllers\Controller;
use App\Domains\Menu\Services\MenuService;
use App\Domains\Table\Services\TableService;
use Illuminate\Http\Request;

class MenuController extends Controller
{
	public function __construct(
		private readonly MenuService $menuService,
		private readonly TableService $tableService
	)
	{
	}

	public function semua(Request $request)
	{
		$this->tableService->clearTableSessionIfInactive($request);

		$menus = $this->menuService->listAll();
		return view('frontliner.menu', ['menus' => $menus]);
	}

	public function makananUtama(Request $request)
	{
		return $this->renderCategory($request, 'makanan utama');
	}

	public function hidangan(Request $request)
	{
		return $this->makananUtama($request);
	}

	public function cemilan(Request $request)
	{
		return $this->renderCategory($request, 'cemilan');
	}

	public function minuman(Request $request)
	{
		return $this->renderCategory($request, 'minuman');
	}

	private function renderCategory(Request $request, string $category)
	{
		// If all orders for the current session table are no longer active
		// (e.g. moved to DELIVERED), clear stale table context from session.
		$this->tableService->clearTableSessionIfInactive($request);

		$menus = $this->menuService->filterByCategory($category);
		return view('frontliner.menu', ['menus' => $menus]);
	}
}
