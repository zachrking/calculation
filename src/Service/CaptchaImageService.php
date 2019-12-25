<?php
/*
 * This file is part of the Calculation package.
 *
 * Copyright (c) 2019 bibi.nu. All rights reserved.
 *
 * This computer code is protected by copyright law and international
 * treaties. Unauthorised reproduction or distribution of this code, or
 * any portion of it, may result in severe civil and criminal penalties,
 * and will be prosecuted to the maximum extent possible under the law.
 */

declare(strict_types=1);

namespace App\Service;

use App\Traits\SessionTrait;
use App\Utils\ImageHandler;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Service to generate and validate a captcha image.
 *
 * @author Laurent Muller
 */
class CaptchaImageService
{
    use SessionTrait;

    /**
     * the allowed characters.
     */
    private const ALLOWED_VALUES = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /**
     * The space between characters.
     */
    private const CHAR_SPACE = 3;

    /**
     * The font path and name.
     */
    private const FONT_PATH = '/resources/fonts/captcha.ttf';

    /**
     * The image data prefix.
     */
    private const IMAGE_PREFIX = 'data:image/png;base64,';

    /**
     * The attribute name for the encoded image data.
     */
    private const KEY_DATA = 'captcha_data';

    /**
     * The attribute name for the captcha text.
     */
    private const KEY_TEXT = 'captcha_text';

    /**
     * The attribute name for the captcha timeout.
     */
    private const KEY_TIME = 'captcha_time';

    /**
     * The maximum validation timeout in seconds (3 minutes).
     */
    private const MAX_TIME_OUT = 180;

    /**
     * The font file name.
     *
     * @var string
     */
    private $font;

    /**
     * Constructor.
     */
    public function __construct(SessionInterface $session, KernelInterface $kernel)
    {
        $this->session = $session;
        $this->font = $kernel->getProjectDir() . self::FONT_PATH;
    }

    /**
     * Remove captcha values from the session.
     */
    public function clear(): self
    {
        $this->removeSessionValue(self::KEY_TEXT);
        $this->removeSessionValue(self::KEY_TIME);
        $this->removeSessionValue(self::KEY_DATA);

        return $this;
    }

    /**
     * Generate a captcha image and save values to the session.
     *
     * @param bool $force  true to recreate an image, false to take the previous created image (if any)
     * @param int  $length the number of characters to output
     * @param int  $width  the image width
     * @param int  $height the image height
     *
     * @return string the image encoded with the base 64
     */
    public function generateImage(bool $force, int $length = 6, int $width = 150, int $height = 30): string
    {
        // not force and valid?
        if (!$force && $this->validateTimeout() && $this->hasSessionValue(self::KEY_DATA)) {
            return $this->getSessionValue(self::KEY_DATA);
        }

        // clear previous values
        $this->clear();

        // text
        $text = $this->generateRandomString($length);

        // image
        $image = $this->createImage($text, $width, $height);

        // convert image
        $data = self::IMAGE_PREFIX . $this->encodeImage($image);

        // save
        $this->setSessionValue(self::KEY_TEXT, $text);
        $this->setSessionValue(self::KEY_DATA, $data);
        $this->setSessionValue(self::KEY_TIME, \time());

        return $data;
    }

    /**
     * Validate the timeout.
     *
     * @return bool true if the timeout valid
     */
    public function validateTimeout(): bool
    {
        $actual = \time();
        $last = $this->getSessionInt(self::KEY_TIME, 0);
        $delta = $actual - $last;

        return $delta <= self::MAX_TIME_OUT;
    }

    /**
     * Validate the given token; ignoring case.
     *
     * @param string $token the token to validate
     *
     * @return bool true if the token is valid
     */
    public function validateToken(?string $token): bool
    {
        return $token && 0 === \strcasecmp($token, $this->session->get(self::KEY_TEXT));
    }

    /**
     * Compute the text layout.
     *
     * @param ImageHandler $image the image to draw to
     * @param float        $size  the font size
     * @param string       $font  the font file
     * @param string       $text  the text to compute
     *
     * @return array the text layout
     */
    private function computeText(ImageHandler $image, float $size, string $font, string $text): array
    {
        $result = [];
        for ($i = 0,  $length = \mb_strlen($text); $i < $length; ++$i) {
            $angle = \random_int(-8, 8);
            $char = \mb_substr($text, $i, 1);
            [$width, $height] = $image->ttfSize($size, $angle, $font, $char);

            $result[] = [
                'char' => $char,
                'angle' => $angle,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $result;
    }

    /**
     * Create an image.
     *
     * @param string $text   the text to output
     * @param int    $width  the image width
     * @param int    $height the image height
     *
     * @return ImageHandler|bool the image resource identifier on success, false on errors
     */
    private function createImage(string $text, int $width, int $height)
    {
        // create image
        if (!$image = ImageHandler::fromTrueColor($width, $height)) {
            return false;
        }

        // draw
        $this->drawBackground($image, $width, $height)
            ->drawPoints($image, $width, $height)
            ->drawLines($image, $width, $height)
            ->drawText($image, $width, $height, $text);

        return $image;
    }

    /**
     * Draws the white background image.
     *
     * @param ImageHandler $image  the image to draw to
     * @param int          $width  the image width
     * @param int          $height the image height
     */
    private function drawBackground(ImageHandler $image, int $width, int $height): self
    {
        $color = $image->allocateWhite();
        $image->fill($color);

        return $this;
    }

    /**
     * Draws horizontal gray lines in the background.
     *
     * @param ImageHandler $image  the image to draw to
     * @param int          $width  the image width
     * @param int          $height the image height
     */
    private function drawLines(ImageHandler $image, int $width, int $height): self
    {
        $lines = \random_int(3, 7);
        $color = $image->allocate(195, 195, 195);
        for ($i = 0; $i < $lines; ++$i) {
            $y1 = \random_int(0, $height);
            $y2 = \random_int(0, $height);
            $image->line(0, $y1, $width, $y2, $color);
        }

        return $this;
    }

    /**
     * Draws blue points in the background.
     *
     * @param ImageHandler $image  the image to draw to
     * @param int          $width  the image width
     * @param int          $height the image height
     */
    private function drawPoints(ImageHandler $image, int $width, int $height): self
    {
        $points = \random_int(300, 400);
        $color = $image->allocate(0, 0, 255);
        for ($i = 0; $i < $points; ++$i) {
            $x = \random_int(0, $width);
            $y = \random_int(0, $height);
            $image->setPixel($x, $y, $color);
        }

        return $this;
    }

    /**
     * Draws the image text.
     *
     * @param ImageHandler $image  the image to draw to
     * @param int          $width  the image width
     * @param int          $height the image height
     * @param string       $text   the text to draw
     */
    private function drawText(ImageHandler $image, int $width, int $height, string $text): self
    {
        // font and color
        $font = $this->font;
        $color = $image->allocateBlack();

        // get layout
        $size = (int) ($height * 0.7);
        $items = $this->computeText($image, $size, $font, $text, $height);

        // get position
        $textHeight = 0;
        $textWidth = -self::CHAR_SPACE;
        foreach ($items as $item) {
            $textWidth += $item['width'] + self::CHAR_SPACE;
            $textHeight = \max($textHeight, $item['height']);
        }
        $x = \intdiv($width - $textWidth, 2);
        $y = \intdiv($height - $textHeight, 2) + $size;

        //draw
        foreach ($items as $item) {
            $char = $item['char'];
            $angle = $item['angle'];
            $image->ttfText($size, $angle, $x, $y, $color, $font, $char);
            $x += $item['width'] + self::CHAR_SPACE;
        }

        return $this;
    }

    /**
     * Encodes the image with MIME base64.
     *
     * @param ImageHandler $image the image to encode
     *
     * @return string the encoded image
     */
    private function encodeImage(ImageHandler $image): string
    {
        // save
        \ob_start();
        $image->toPng();
        $buffer = \ob_get_clean();
        \ob_end_clean();

        // encode
        return \base64_encode($buffer);
    }

    /**
     * Generate a random string.
     *
     * @param number $length the number of characters to output
     *
     * @return string the random string
     */
    private function generateRandomString($length): string
    {
        $length = \max($length, 2);
        $result = \str_shuffle(self::ALLOWED_VALUES);

        return \substr($result, 0, $length);
    }
}
