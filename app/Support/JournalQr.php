<?php
namespace App\Support;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
class JournalQr
{
    public static function image(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd())))->writeString($url);
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
