<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicInfoController extends Controller
{
    public function hours(): View
    {
        return view('public-menu.hours', $this->sharedData());
    }

    public function gallery(): View
    {
        return view('public-menu.gallery', $this->sharedData());
    }

    public function contact(): View
    {
        return view('public-menu.contact', $this->sharedData());
    }

    private function sharedData(): array
    {
        $business = BusinessSetting::current();

        return [
            'business' => $business,
            'openingStatus' => $business->openingStatus(),
            'galleryImages' => collect($business->galleryItems())
                ->filter(fn (array $item) => Storage::disk('public')->exists($item['path']))
                ->values(),
        ];
    }
}
