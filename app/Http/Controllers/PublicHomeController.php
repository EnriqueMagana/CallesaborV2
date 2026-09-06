<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DigitalMenuSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        $business = BusinessSetting::current();
        $menuSettings = DigitalMenuSetting::current();
        $moment = now(config('app.business_timezone', 'America/Mexico_City'));
        $categories = $menuSettings->show_categories
            ? Category::query()
                ->where('is_active', true)
                ->whereHas('products', fn ($query) => $query->where('is_active', true))
                ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
                ->with(['products' => fn ($query) => $query->where('is_active', true)->whereNotNull('image')->orderBy('sort_order')->limit(1)])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : collect();

        $galleryImages = collect($menuSettings->show_gallery ? $menuSettings->galleryItems() : [])
            ->filter(fn (array $item) => Storage::disk('public')->exists($item['path']))
            ->values();

        return view('public-home.index', [
            'business' => $business,
            'menuSettings' => $menuSettings,
            'categories' => $categories,
            'galleryImages' => $galleryImages,
            'openingStatus' => $business->openingStatus($moment),
            'weeklySchedule' => $business->weeklySchedule($moment),
        ]);
    }
}
