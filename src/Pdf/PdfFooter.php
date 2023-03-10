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

namespace App\Pdf;

use App\Pdf\Enums\PdfMove;
use App\Pdf\Enums\PdfTextAlignment;
use App\Report\AbstractReport;
use App\Util\FormatUtils;

/**
 * Class to output footer in PDF documents.
 */
class PdfFooter
{
    /**
     * The footer text.
     */
    protected ?string $text = null;

    /**
     * The footer URL.
     */
    protected ?string $url = null;

    /**
     * the top border.
     */
    private readonly PdfBorder $border;

    /**
     * Constructor.
     */
    public function __construct(protected readonly PdfDocument $parent)
    {
        $this->border = PdfBorder::top();
    }

    /**
     * Output this content to the parent document.
     */
    public function output(): void
    {
        // margins
        $parent = $this->parent;
        $margins = $parent->setCellMargin(0);

        // position and cells width
        $parent->SetY(PdfDocument::FOOTER_OFFSET);
        $cellWidth = $parent->getPrintableWidth() / 3;

        // style and line color
        PdfStyle::getDefaultStyle()->setFontSize(8)->apply($parent);

        // pages (left) +  text and url (center) + date (right)
        $this->outputText($this->getPage(), $cellWidth, PdfTextAlignment::LEFT)
            ->outputText($this->text ?? '', $cellWidth, PdfTextAlignment::CENTER, $this->url ?? '')
            ->outputText($this->getDate(), $cellWidth, PdfTextAlignment::RIGHT);

        // reset
        $parent->resetStyle()->setCellMargin($margins);
    }

    /**
     * Sets the content.
     */
    public function setContent(string $text, ?string $url = null): self
    {
        $this->text = $text;
        $this->url = $url;

        return $this;
    }

    /**
     * Gets the formatted current date.
     */
    private function getDate(): string
    {
        return FormatUtils::formatDateTime(new \DateTime(), \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    }

    /**
     * Gets the formatted current and total pages.
     */
    private function getPage(): string
    {
        $parent = $this->parent;
        $page = $parent->PageNo();
        if ($parent instanceof AbstractReport) {
            return $parent->trans('report.page', ['{0}' => $page, '{1}' => '{nb}']);
        }

        return "Page $page / {nb}";
    }

    /**
     * Output the given text.
     */
    private function outputText(string $text, float $cellWidth, PdfTextAlignment $align, string $link = ''): self
    {
        $this->parent->Cell($cellWidth, PdfDocument::LINE_HEIGHT, $text, $this->border, PdfMove::RIGHT, $align, false, $link);

        return $this;
    }
}
