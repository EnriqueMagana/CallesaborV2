<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class KioskQrCode
{
    public function dataUri(string $content): string
    {
        $renderer = new ImageRenderer(new RendererStyle(360, 2), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($content);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
