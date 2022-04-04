<?php
/*
 * This file is part of the Calculation package.
 *
 * (c) bibi.nu. <bibi@bibi.nu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Pdf;

/**
 * This trait allows class to have a border property.
 *
 * The default value is <code>PdfConstantsInterface::BORDER_ALL</code>.
 *
 * @author Laurent Muller
 */
trait PdfBorderTrait
{
    /**
     * The border style.
     */
    protected string|int $border = PdfConstantsInterface::BORDER_ALL;

    /**
     * Gets the border style.
     *
     * @return int|string How the borders must be drawn around the cells.The value can be either:
     *                    <ul>
     *                    <li>A number:
     *                    <ul>
     *                    <li><b>0</b> : No border (default value).</li>
     *                    <li><b>1</b> : Frame.</li>
     *                    </ul>
     *                    </li>
     *                    <li>A string containing some or all of the following characters (in any order):
     *                    <ul>
     *                    <li>'<b>L</b>' : Left.</li>
     *                    <li>'<b>T</b>' : Top.</li>
     *                    <li>'<b>R</b>' : Right.</li>
     *                    <li>'<b>B</b>' : Bottom.</li>
     *                    </ul>
     *                    </li>
     *                    </ul>
     */
    public function getBorder(): int|string
    {
        return $this->border;
    }

    /**
     * Returns if this border is inherited.
     *
     * @return bool true if inherited
     */
    public function isBorderInherited(): bool
    {
        return PdfConstantsInterface::BORDER_INHERITED === $this->border;
    }

    /**
     * Sets the border style.
     *
     * @param int|string $border indicates if borders must be drawn around the cell. The value can be either:
     *                           <ul>
     *                           <li>A number:
     *                           <ul>
     *                           <li><b>0</b> : No border (default value).</li>
     *                           <li><b>1</b> : Frame.</li>
     *                           </ul>
     *                           </li>
     *                           <li>A string containing some or all of the following characters (in any order):
     *                           <ul>
     *                           <li>'<b>L</b>' : Left.</li>
     *                           <li>'<b>T</b>' : Top.</li>
     *                           <li>'<b>R</b>' : Right.</li>
     *                           <li>'<b>B</b>' : Bottom.</li>
     *                           </ul>
     *                           </li>
     *                           </ul>
     */
    public function setBorder(int|string $border): self
    {
        $this->border = $this->validateBorder($border);

        return $this;
    }

    /**
     * Gets the textual representation of this border.
     *
     * @return string the textual representation
     */
    protected function getBorderText(): string
    {
        $result = [];
        $border = $this->getBorder();

        if (PdfConstantsInterface::BORDER_ALL === $border) {
            $result[] = 'All';
        } elseif (PdfConstantsInterface::BORDER_INHERITED === $border) {
            $result[] = 'Inherited';
        } elseif (\is_string($border)) {
            for ($i = 0, $count = \strlen($border); $i < $count; ++$i) {
                switch ($border[$i]) {
                    case PdfConstantsInterface::BORDER_LEFT:
                        $result[] = 'Left';
                        break;

                    case PdfConstantsInterface::BORDER_RIGHT:
                        $result[] = 'Right';
                        break;

                    case PdfConstantsInterface::BORDER_TOP:
                        $result[] = 'Top';
                        break;

                    case PdfConstantsInterface::BORDER_BOTTOM:
                        $result[] = 'Bottom';
                        break;
                }
            }
        }
        if (empty($result)) {
            $result[] = 'None';
        }

        return 'PdfBorder(' . \implode(' ', $result) . ')';
    }

    /**
     * Validate the given border.
     *
     * @param string|int $border the border to validate
     *
     * @return string|int a valid border
     * @psalm-suppress RedundantCast
     */
    protected function validateBorder(string|int $border): string|int
    {
        if (empty($border)) {
            return PdfConstantsInterface::BORDER_NONE;
        }
        if (PdfConstantsInterface::BORDER_ALL === $border || PdfConstantsInterface::BORDER_INHERITED === $border) {
            return $border;
        }

        $result = '';
        $border = \strtoupper((string) $border);
        for ($i = 0, $count = \strlen($border); $i < $count; ++$i) {
            switch ($border[$i]) {
                case PdfConstantsInterface::BORDER_LEFT:
                case PdfConstantsInterface::BORDER_RIGHT:
                case PdfConstantsInterface::BORDER_TOP:
                case PdfConstantsInterface::BORDER_BOTTOM:
                    if (!\str_contains((string) $result, $border[$i])) {
                        $result .= $border[$i];
                    }
                    break;
            }
        }

        return $result ?: PdfConstantsInterface::BORDER_NONE;
    }
}
