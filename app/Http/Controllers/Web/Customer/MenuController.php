<?php

namespace App\Http\Controllers\Web\Customer;

use App\Http\Controllers\Controller;
use App\Services\MenuService;

class MenuController extends Controller
{
	public function __construct(private readonly MenuService $menuService)
	{
	}

	public function hidangan()
	{
		return $this->renderCategory('Hidangan');
	}

	public function cemilan()
	{
		return $this->renderCategory('Cemilan');
	}

	public function minuman()
	{
		return $this->renderCategory('Minuman');
	}

	private function renderCategory(string $category)
	{
		$menus = $this->menuService->filterByCategory($category);
		return view('menu', ['menus' => $menus]);
	}
}
