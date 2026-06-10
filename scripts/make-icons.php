<?php

/**
 * Generates Argent PWA icons with GD: dark rounded square, metallic ring,
 * serif "A" monogram. Run once locally:
 *   php scripts/make-icons.php
 */
$out = __DIR__.'/../public/icons';
@mkdir($out, 0777, true);

function makeIcon(int $size, string $path, bool $maskable = false): void
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    $bg = imagecolorallocate($img, 11, 12, 16);
    $r = $maskable ? 0 : (int) ($size * 0.22);

    // rounded square background
    if ($r === 0) {
        imagefilledrectangle($img, 0, 0, $size, $size, $bg);
    } else {
        imagefilledrectangle($img, $r, 0, $size - $r, $size, $bg);
        imagefilledrectangle($img, 0, $r, $size, $size - $r, $bg);
        foreach ([[$r, $r], [$size - $r, $r], [$r, $size - $r], [$size - $r, $size - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $bg);
        }
    }

    // metallic ring — concentric circles faking a gradient
    $cx = (int) ($size / 2);
    $cy = (int) ($size / 2);
    $ringR = (int) ($size * 0.36);
    $thick = max(2, (int) ($size * 0.025));
    $steps = 64;
    for ($i = 0; $i < $steps; $i++) {
        $a = $i / $steps * 2 * M_PI;
        // brightness varies around the ring for a brushed-metal feel
        $b = 165 + (int) (75 * sin($a * 2 + 0.8));
        $col = imagecolorallocate($img, $b, $b - 6, max(0, $b - 22));
        $x1 = $cx + cos($a) * $ringR;
        $y1 = $cy + sin($a) * $ringR;
        $a2 = ($i + 1.6) / $steps * 2 * M_PI;
        $x2 = $cx + cos($a2) * $ringR;
        $y2 = $cy + sin($a2) * $ringR;
        imagesetthickness($img, $thick);
        imageline($img, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $col);
    }

    // "A" monogram
    $font = 'C:/Windows/Fonts/georgia.ttf';
    if (! file_exists($font)) {
        $font = 'C:/Windows/Fonts/times.ttf';
    }
    $fsize = $size * 0.30;
    $silver = imagecolorallocate($img, 222, 224, 230);
    $bbox = imagettfbbox($fsize, 0, $font, 'A');
    $tw = $bbox[2] - $bbox[0];
    $th = $bbox[1] - $bbox[7];
    imagettftext($img, $fsize, 0, (int) ($cx - $tw / 2 - $bbox[0]), (int) ($cy + $th / 2 - 2), $silver, $font, 'A');

    imagepng($img, $path);
    imagedestroy($img);
    echo "wrote $path\n";
}

makeIcon(192, "$out/icon-192.png");
makeIcon(512, "$out/icon-512.png");
makeIcon(512, "$out/icon-maskable-512.png", true);
