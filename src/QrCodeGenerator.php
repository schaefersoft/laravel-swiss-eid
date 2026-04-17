<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeGenerator
{
    /**
     * Generate an inline SVG string for the given data.
     *
     * @param  string  $data  The content to encode (typically a deeplink URL).
     * @param  int  $size  Output size in pixels (maps to the SVG viewBox).
     */
    public function svg(string $data, int $size = 300): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'addQuietzone' => true,
            'imageBase64' => false,
        ]);

        return (new QRCode($options))->render($data);
    }

    /**
     * Generate a base64-encoded data URI of the SVG QR code.
     *
     * @param  string  $data  The content to encode.
     * @param  int  $size  Output size in pixels.
     */
    public function dataUri(string $data, int $size = 300): string
    {
        $svg = $this->svg($data, $size);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
