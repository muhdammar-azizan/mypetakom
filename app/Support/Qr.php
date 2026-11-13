<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class Qr
{
    /**
     * Render a QR code as an inline SVG string.
     */
    public static function svg(string $content, int $size = 240): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd());

        return trim(preg_replace('/^<\?xml.*?\?>\s*/', '', (new Writer($renderer))->writeString($content)));
    }
}
