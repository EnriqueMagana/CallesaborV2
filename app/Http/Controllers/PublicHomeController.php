<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        $business = BusinessSetting::current();
        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->with(['products' => fn ($query) => $query->where('is_active', true)->whereNotNull('image')->orderBy('sort_order')->limit(1)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $galleryImages = collect($business->galleryItems())
            ->filter(fn (array $item) => Storage::disk('public')->exists($item['path']))
            ->values();

        return view('public-home.index', [
            'business' => $business,
            'categories' => $categories,
            'galleryImages' => $galleryImages,
            'openingStatus' => $business->openingStatus(),
        ]);
    }
}
