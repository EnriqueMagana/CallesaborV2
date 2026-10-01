<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DigitalMenuSetting;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\DigitalMenuAnalytics;
use App\Support\BusinessTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicMenuController extends Controller
{
    public function __invoke(DigitalMenuAnalytics $analytics): View
    {
        $business = BusinessSetting::current();
        $menuSettings = DigitalMenuSetting::current();
        $moment = BusinessTime::now();
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
            ? $this->featuredProducts($analytics, $catalogProducts)
            : collect();
        $galleryImages = collect($menuSettings->show_gallery ? $menuSettings->galleryItems() : [])
            ->filter(fn (array $item) => Storage::disk('public')->exists($item['path']))
            ->values();
        $campaigns = Promotion::query()
            ->available('digital_menu', $moment)
            ->with([
                'groups.products' => fn ($query) => $query
                    ->where('is_active', true)
                    ->select(['products.id', 'products.name', 'products.description', 'products.image']),
                'primaryProduct' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with($this->productDetails()),
            ])
            ->orderBy('name')
            ->get();
        $promotions = $campaigns
            ->where('presentation_type', 'promotion')
            ->values();
        $discountCampaigns = $campaigns
            ->where('presentation_type', 'discount')
            ->filter(fn (Promotion $campaign) => $campaign->primaryProduct)
            ->values();
        $newProductCampaigns = $campaigns
            ->filter(fn (Promotion $campaign) => $campaign->isProductLaunch() && $campaign->primaryProduct)
            ->values();
        $searchMetadata = $this->searchMetadata($catalogProducts, $featured, $campaigns);

        return view('public-menu.index', [
            'business' => $business,
            'menuSettings' => $menuSettings,
            'categories' => $categories,
            'uncategorized' => $uncategorized,
            'featured' => $featured,
            'galleryImages' => $galleryImages,
            'openingStatus' => $business->openingStatus($moment),
            'totalProducts' => $catalogProducts->count(),
            'promotions' => $promotions,
            'discountCampaigns' => $discountCampaigns,
            'newProductCampaigns' => $newProductCampaigns,
            'searchMetadata' => $searchMetadata,
        ]);
    }

    /** @return Collection<int, array{badges: array<int, string>, keywords: string}> */
    private function searchMetadata(Collection $catalogProducts, Collection $featured, Collection $campaigns): Collection
    {
        $metadata = $catalogProducts
            ->mapWithKeys(fn (Product $product): array => [
                (int) $product->id => ['badges' => [], 'keywords' => []],
            ])
            ->all();

        $addContext = function (int $productId, string $badge, string $keywords = '') use (&$metadata): void {
            if (! isset($metadata[$productId])) {
                return;
            }

            $metadata[$productId]['badges'][] = $badge;
            $metadata[$productId]['keywords'][] = $keywords;
        };

        $featured->each(fn (Product $product) => $addContext((int) $product->id, 'Favorito', 'favorito recomendado'));

        $campaigns->each(function (Promotion $campaign) use ($addContext): void {
            $keywords = collect([
                $campaign->name,
                $campaign->short_description,
                $campaign->description,
                $campaign->pricingRuleShortLabel(),
            ])->filter()->implode(' ');

            if ($campaign->presentation_type === 'discount' && $campaign->primaryProduct) {
                $addContext((int) $campaign->primaryProduct->id, 'Descuento', $keywords.' descuento oferta');
            } elseif ($campaign->isProductLaunch() && $campaign->primaryProduct) {
                $addContext((int) $campaign->primaryProduct->id, 'Nuevo', $keywords.' nuevo novedad');
            } else {
                collect([$campaign->primaryProduct])
                    ->concat($campaign->groups->flatMap(fn ($group) => $group->products))
                    ->filter()
                    ->unique('id')
                    ->each(fn (Product $product) => $addContext((int) $product->id, 'Promoción', $keywords.' promoción oferta'));
            }
        });

        return collect($metadata)->map(fn (array $item): array => [
            'badges' => array_values(array_unique($item['badges'])),
            'keywords' => implode(' ', array_filter($item['keywords'])),
        ]);
    }

    private function featuredProducts(DigitalMenuAnalytics $analytics, Collection $catalogProducts): Collection
    {
        $ids = $analytics->topProductIds(5);

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
