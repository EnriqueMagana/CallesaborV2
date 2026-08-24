<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\DigitalMenuSetting;
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
        $data = $this->sharedData();
        abort_unless($data['menuSettings']->show_gallery, 404);

        return view('public-menu.gallery', $data);
    }

    public function contact(): View
    {
        return view('public-menu.contact', $this->sharedData());
    }

    private function sharedData(): array
    {
        $business = BusinessSetting::current();
        $menuSettings = DigitalMenuSetting::current();

        return [
            'business' => $business,
            'menuSettings' => $menuSettings,
            'openingStatus' => $business->openingStatus(),
            'galleryImages' => collect($menuSettings->show_gallery ? $menuSettings->galleryItems() : [])
                ->filter(fn (array $item) => Storage::disk('public')->exists($item['path']))
                ->values(),
        ];
    }
}
