<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicMenuController extends Controller
{
    public function __invoke(): View
    {
        $business = BusinessSetting::current();
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

        $featured = $this->featuredProducts($business);
        $galleryImages = collect($business->galleryItems())
            ->filter(fn (array $item) => Storage::disk('public')->exists($item['path']))
            ->values();

        return view('public-menu.index', [
            'business' => $business,
            'categories' => $categories,
            'uncategorized' => $uncategorized,
            'featured' => $featured,
            'galleryImages' => $galleryImages,
            'openingStatus' => $business->openingStatus(),
        ]);
    }

    private function featuredProducts(BusinessSetting $business): Collection
    {
        $ids = collect($business->featured_product_ids)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return Product::query()
                ->where('is_active', true)
                ->whereNotNull('image')
                ->with($this->productDetails())
                ->orderBy('sort_order')
                ->limit(6)
                ->get();
        }

        return Product::query()
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->with($this->productDetails())
            ->get()
            ->sortBy(fn (Product $product) => $ids->search($product->id))
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
