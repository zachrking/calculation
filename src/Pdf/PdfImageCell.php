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

namespace App\Pdf;

use App\Interfaces\ImageExtensionInterface;
use App\Traits\MathTrait;

/**
 * Specialized cell containing an image.
 *
 * @author Laurent Muller
 */
class PdfImageCell extends PdfCell implements ImageExtensionInterface
{
    use MathTrait;

    /**
     * The image height.
     *
     * @var int
     */
    protected $height;

    /**
     * The original image height.
     *
     * @var int
     */
    protected $originalHeight;

    /**
     * The original image width.
     *
     * @var int
     */
    protected $originalWidth;

    /**
     * The full image path.
     *
     * @var string
     */
    protected $path;

//     /**
//      * The resolution in dot per each (DPI).
//      *
//      * @var int
//      */
//     protected $resolution;

    /**
     * The image width.
     *
     * @var int
     */
    protected $width;

    /**
     * Constructor.
     *
     * @param string            $path      the full image path
     * @param int               $cols      the cell columns span
     * @param \App\Pdf\PdfStyle $style     the cell style
     * @param string            $alignment the cell alignment
     *
     * @throws \InvalidArgumentException if the path file does not exist
     */
    public function __construct(string $path, $cols = 1, ?PdfStyle $style = null, string $alignment = PdfConstantsInterface::ALIGN_INHERITED)
    {
        if (!\file_exists($path)) {
            throw new \InvalidArgumentException("The image '{$path}' does not exist.");
        }

        parent::__construct(null, $cols, $style, $alignment);

        $this->path = $path;
        list($this->width, $this->height) = \getimagesize($path);
        $this->originalWidth = $this->width;
        $this->originalHeight = $this->height;
    }

    /**
     * Draw this image.
     *
     * @param PdfDocument  $parent    the parent document
     * @param PdfRectangle $bounds    the target bounds
     * @param string       $alignment the horizontal alignment
     */
    public function drawImage(PdfDocument $parent, PdfRectangle $bounds, string $alignment): void
    {
        // convert size
        $width = $parent->pixels2UserUnit($this->width);
        $height = $parent->pixels2UserUnit($this->height);

        // get default position
        $x = $bounds->x();
        $y = $bounds->y() + ($bounds->height() - $height) / 2;

        switch ($alignment) {
            case PdfConstantsInterface::ALIGN_RIGHT:
                $x += $bounds->width() - $width;
                break;
            case PdfConstantsInterface::ALIGN_CENTER:
            case PdfConstantsInterface::ALIGN_JUSTIFIED:
                $x += ($bounds->width() - $width) / 2;
                break;
        }

        // draw
        $parent->Image($this->path, $x, $y, $width, $height);
    }

    /**
     * Gets the current image height.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Gets the original image height.
     */
    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    /**
     * Gets the original image ratio (the original width divided by the original height).
     */
    public function getOriginalRatio(): float
    {
        return $this->safeDivide($this->originalWidth, $this->originalHeight, 1);
    }

    /**
     * Gets the original image width and height.
     *
     * @return array an array with 2 elements. Index 0 and 1 contains respectively the original width and the original height.
     */
    public function getOriginalSize(): array
    {
        return [$this->originalWidth, $this->originalHeight];
    }

    /**
     * Gets the original image width.
     */
    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    /**
     * Gets the image path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Gets the current image ratio (the current width divided by the current height).
     */
    public function getRatio(): float
    {
        return $this->safeDivide($this->width, $this->height, 1);
    }

    /**
     * Gets the current image size.
     *
     * @return array an array with 2 elements. Index 0 and 1 contains respectively the width and the height.
     */
    public function getSize(): array
    {
        return [$this->width, $this->height];
    }

    /**
     * Gets the current image width.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Resize the image.
     *
     * If both height and width arguments are equal to 0, the new width and height are equals to original size.
     *
     * @param int $height the new height or 0 to take the original width as reference
     * @param int $width  the new width or 0 to take the original height as reference
     */
    public function resize(int $height = 0, int $width = 0): self
    {
        if (0 === $height && 0 === $width) {
            $this->height = $this->originalHeight;
            $this->width = $this->originalWidth;

            return $this;
        }

        $ratio = $this->getOriginalRatio();
        if ($height > 0) {
            $width = $height * $ratio;
        } elseif ($width > 0) {
            $height = $width / $ratio;
        }

        $this->width = (int) \round($width);
        $this->height = (int) \round($height);

        return $this;
    }
}
