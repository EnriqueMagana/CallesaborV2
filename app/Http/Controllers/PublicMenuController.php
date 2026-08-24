<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DigitalMenuSetting;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicMenuController extends Controller
{
    public function __invoke(): View
    {
        $business = BusinessSetting::current();
        $menuSettings = DigitalMenuSetting::current();
        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->with(['products' => fn ($query) => $query
                ->where('is_active', true)
                ->with($this->productDetails())
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $uncategorized = Product::query()
            ->whereNull('category_id')
            ->where('is_active', true)
            ->with($this->productDetails())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $catalogProducts = $categories
            ->flatMap(fn (Category $category) => $category->products)
            ->concat($uncategorized)
            ->unique('id')
            ->values();
        $featured = $menuSettings->show_featured
            ? $this->featuredProducts($menuSettings, $catalogProducts)
            : collect();
        $galleryImages = collect($menuSettings->show_gallery ? $menuSettings->galleryItems() : [])
            ->filter(fn (array $item) => Storage::disk('public')->exists($item['path']))
            ->values();

        return view('public-menu.index', [
            'business' => $business,
            'menuSettings' => $menuSettings,
            'categories' => $categories,
            'uncategorized' => $uncategorized,
            'featured' => $featured,
            'galleryImages' => $galleryImages,
            'openingStatus' => $business->openingStatus(),
            'totalProducts' => $catalogProducts->count(),
        ]);
    }

    private function featuredProducts(DigitalMenuSetting $menuSettings, Collection $catalogProducts): Collection
    {
        $ids = collect($menuSettings->featured_product_ids)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $productsById = $catalogProducts->keyBy('id');

        return $ids
            ->map(fn (int $id) => $productsById->get($id))
            ->filter()
            ->values();
    }

    private function productDetails(): array
    {
        return [
            'category',
            'ingredients' => fn ($query) => $query->where('is_active', true),
            'addonGroups' => fn ($query) => $query
                ->where('is_active', true)
                ->with(['addons' => fn ($addons) => $addons->where('is_active', true)]),
        ];
    }
}
