<?php
/*
 * This file is part of the Calculation package.
 *
 * (c) bibi.nu <bibi@bibi.nu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Images constants.
 */
interface ImageExtensionInterface
{
    /**
     * The default image resolution (96) in dot per each (DPI).
     */
    final public const DEFAULT_RESOLUTION = 96;

    /**
     * The Bitmap file extension ("bmp").
     */
    final public const EXTENSION_BMP = 'bmp';

    /**
     * The Gif file extension ("gif").
     */
    final public const EXTENSION_GIF = 'gif';

    /**
     * The JPEG file extension ("jpeg").
     */
    final public const EXTENSION_JPEG = 'jpeg';

    /**
     * The JPG file extension ("jpg").
     */
    final public const EXTENSION_JPG = 'jpg';

    /**
     * The PNG file extension ("png").
     */
    final public const EXTENSION_PNG = 'png';

    /**
     * The XBM file extension ("xbm").
     */
    final public const EXTENSION_XBM = 'xbm';

    /**
     * The default image size (192 pixels).
     */
    final public const SIZE_DEFAULT = 192;

    /**
     * The medium image size used for user list (96 pixels).
     */
    final public const SIZE_MEDIUM = 96;

    /**
     * The small image size used for logged user (32 pixels).
     */
    final public const SIZE_SMALL = 32;
}
