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

use App\Pdf\Enums\PdfRectangleStyle;
use App\Pdf\Enums\PdfTextAlignment;
use App\Traits\MathTrait;
use App\Util\Utils;

/**
 * Class to build a table.
 *
 * @see PdfColumn
 */
class PdfTableBuilder
{
    use MathTrait;

    /**
     * The column alignment.
     */
    protected PdfTextAlignment $alignment = PdfTextAlignment::LEFT;

    /**
     * The border style.
     */
    protected PdfBorder $border;

    /**
     * The cells.
     *
     * @var PdfCell[]
     */
    protected array $cells = [];

    /**
     * The columns.
     *
     * @var PdfColumn[]
     */
    protected array $columns = [];

    /**
     * The header style.
     */
    protected ?PdfStyle $headerStyle = null;

    /**
     * The cell listener.
     */
    protected ?PdfCellListenerInterface $listener = null;

    /**
     * Print headers when a new page is added.
     */
    protected bool $repeatHeader = true;

    /**
     * The current row style.
     */
    protected ?PdfStyle $rowStyle = null;

    /**
     * Constructor.
     *
     * @param PdfDocument $parent    the parent document to print in
     * @param bool        $fullWidth a value indicating if the table take all the printable width
     */
    public function __construct(protected PdfDocument $parent, protected bool $fullWidth = true)
    {
        $this->border = PdfBorder::all();
    }

    /**
     * Adds a cell to the current row.
     *
     * @param ?string           $text      the text of the cell
     * @param int               $cols      the number of columns to span
     * @param ?PdfStyle         $style     the cell style to use or null to use the default cell style
     * @param ?PdfTextAlignment $alignment the cell alignment
     * @param ?string           $link      the link of the cell
     */
    public function add(?string $text = null, int $cols = 1, ?PdfStyle $style = null, ?PdfTextAlignment $alignment = null, ?string $link = null): static
    {
        return $this->addCell(new PdfCell($text, $cols, $style, $alignment, $link));
    }

    /**
     * Adds the given cell to the list of cells. Do nothing if the cell is null.
     *
     * @throws \LogicException if no current row is started
     */
    public function addCell(?PdfCell $cell): static
    {
        if (!$this->isRowStarted()) {
            throw new \LogicException('No current row is started.');
        }
        if (null !== $cell) {
            $this->cells[] = $cell;
        }

        return $this;
    }

    /**
     * Adds the given cells to the list of cells. The null cells are not added.
     *
     * @param PdfCell[] $cells the cells to add
     *
     * @throws \LogicException if no current row is started
     */
    public function addCells(array $cells): static
    {
        foreach ($cells as $cell) {
            $this->addCell($cell);
        }

        return $this;
    }

    /**
     * Adds the given column to the list of columns.  Do nothing if the column is null.
     */
    public function addColumn(?PdfColumn $column): static
    {
        if (null !== $column) {
            $this->columns[] = $column;
        }

        return $this;
    }

    /**
     * Adds the given columns to the list of columns. The null columns are not added.
     */
    public function addColumns(PdfColumn ...$columns): static
    {
        foreach ($columns as $column) {
            $this->addColumn($column);
        }

        return $this;
    }

    /**
     * Create and add a header row with the given values.
     *
     * @throws \LogicException if the row is already started
     */
    public function addHeaderRow(?string ...$values): static
    {
        $this->startHeaderRow();
        foreach ($values as $value) {
            $this->add($value);
        }

        return $this->completeRow();
    }

    /**
     * Create and add a row with the given values.
     *
     * @throws \LogicException if the row is already started
     */
    public function addRow(?string ...$values): static
    {
        $this->startRow();
        foreach ($values as $value) {
            $this->add($value);
        }

        return $this->completeRow();
    }

    /**
     * Adds a new page, if needed, for the given height.
     *
     * @param float $height The desired height
     *
     * @return bool true if a new page is added
     */
    public function checkNewPage(float $height): bool
    {
        $parent = $this->parent;
        if (!$parent->isPrintable($height)) {
            $parent->AddPage();
            if ($this->repeatHeader) {
                $this->outputHeaders();
            }

            return true;
        }

        return false;
    }

    /**
     * Completes the current row with empty cells.
     *
     * @param bool $endRow true to ending the row after completed
     *
     * @throws \LogicException if no current row is started
     */
    public function completeRow(bool $endRow = true): static
    {
        // started?
        if (!$this->isRowStarted()) {
            throw new \LogicException('No row started.');
        }

        // add remaining cells
        $remaining = $this->getColumnsCount() - $this->getCellsSpan();
        for ($i = 0; $i < $remaining; ++$i) {
            $this->add('');
        }
        if ($endRow) {
            return $this->endRow();
        }

        return $this;
    }

    /**
     * Output the current row.
     *
     * After this call, no more cell is defined.
     *
     * @throws \LengthException     if no cell is defined
     * @throws \OutOfRangeException if the number of spanned cells is not equal to the number of columns
     */
    public function endRow(): static
    {
        // check
        if (empty($this->cells)) {
            throw new \LengthException('No cell to add.');
        }
        if ($this->getCellsSpan() !== $this->getColumnsCount()) {
            throw new \OutOfRangeException('Invalid spanned cells.');
        }

        $cells = $this->cells;
        $parent = $this->parent;
        $columns = $this->columns;

        // compute
        [$texts, $styles, $aligns, $widths, $fixeds] = $this->computeCells($cells, $columns);

        // update widths
        if ($this->fullWidth) {
            $this->adjustCellWidths($cells, $fixeds, $widths);
        }

        // clear before adding new page
        $this->cells = [];
        $this->rowStyle = null;

        // check new page
        $height = $this->getRowHeight($texts, $widths, $styles, $cells);
        $this->checkNewPage($height);

        // output
        $this->drawRow($parent, $height, $texts, $widths, $styles, $aligns, $cells);

        return $this;
    }

    /**
     * Gets the border.
     */
    public function getBorder(): PdfBorder
    {
        return $this->border;
    }

    /**
     * Gets the cells.
     *
     * @return PdfCell[] the cells
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    /**
     * Gets the number of cells.
     */
    public function getCellsCount(): int
    {
        return \count($this->cells);
    }

    /**
     * Gets the columns.
     *
     * @return PdfColumn[] the columns
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Gets the number of columns.
     */
    public function getColumnsCount(): int
    {
        return \count($this->columns);
    }

    /**
     * Gets the header style.
     *
     * @return PdfStyle the custom header style, if set; the default header style otherwise
     *
     * @see PdfStyle::getHeaderStyle()
     */
    public function getHeaderStyle(): PdfStyle
    {
        return $this->headerStyle ?? PdfStyle::getHeaderStyle();
    }

    /**
     * Gets the cell listener.
     */
    public function getListener(): ?PdfCellListenerInterface
    {
        return $this->listener;
    }

    /**
     * Gets the parent.
     */
    public function getParent(): PdfDocument
    {
        return $this->parent;
    }

    /**
     * Gets a value indicating if the table take all the printable width.
     */
    public function isFullWidth(): bool
    {
        return $this->fullWidth;
    }

    /**
     * Returns if the header row is printed when a new page is added.
     */
    public function isRepeatHeader(): bool
    {
        return $this->repeatHeader;
    }

    /**
     * Returns a value indicating if a row is currently started.
     */
    public function isRowStarted(): bool
    {
        return null !== $this->rowStyle;
    }

    /**
     * Output a row with the header style and the columns texts.
     *
     * @throws \LengthException if no column is defined
     */
    public function outputHeaders(): static
    {
        if (empty($this->columns)) {
            throw new \LengthException('No column is defined.');
        }

        return $this->addHeaderRow(...\array_map(fn (PdfColumn $c): ?string => $c->getText(), $this->columns));
    }

    /**
     * Output a row.
     *
     * @param PdfCell[] $cells the cells to output
     * @param ?PdfStyle $style the row style or null for default cell style
     *
     * @throws \LogicException      if a row is already started
     * @throws \LengthException     if no cell is defined
     * @throws \OutOfRangeException if the number of spanned cells is not equal to the number of columns
     */
    public function row(array $cells, ?PdfStyle $style = null): static
    {
        return $this->startRow($style)
            ->addCells($cells)
            ->endRow();
    }

    /**
     * Sets the border.
     */
    public function setBorder(PdfBorder|string|int $border): static
    {
        $this->border = \is_string($border) || \is_int($border) ? new PdfBorder($border) : $border;

        return $this;
    }

    /**
     * Sets a value indicating if the table take all the printable width.
     *
     * @param bool $fullWidth true if the table take all the printable width
     */
    public function setFullWidth(bool $fullWidth): static
    {
        $this->fullWidth = $fullWidth;

        return $this;
    }

    /**
     * Sets the header style.
     *
     * @param ?PdfStyle $headerStyle the custom header style to set or null to use the default header style
     *
     * @see PdfStyle::getHeaderStyle()
     */
    public function setHeaderStyle(?PdfStyle $headerStyle): void
    {
        $this->headerStyle = $headerStyle;
    }

    /**
     * Sets the cell listener.
     */
    public function setListener(?PdfCellListenerInterface $listener): static
    {
        $this->listener = $listener;

        return $this;
    }

    /**
     * Sets if the header row is printed when a new page is added.
     *
     * @param bool $repeatHeader true to print the header on each new pages
     */
    public function setRepeatHeader(bool $repeatHeader): static
    {
        $this->repeatHeader = $repeatHeader;

        return $this;
    }

    /**
     * Output a row with a single cell.
     *
     * @param ?string           $text      the text of the cell
     * @param ?PdfStyle         $style     the row style to use or null to use the default cell style
     * @param ?PdfTextAlignment $alignment the cell alignment
     *
     * @throws \LogicException if a row is already started
     *
     * @see PdfTableBuilder::add()
     */
    public function singleLine(?string $text = null, ?PdfStyle $style = null, ?PdfTextAlignment $alignment = null): static
    {
        return $this->startRow()
            ->add($text, $this->getColumnsCount(), $style, $alignment)
            ->endRow();
    }

    /**
     * Starts a new row with the custom header style, if set; with the default header style otherwise.
     *
     * @see PdfTableBuilder::getHeaderStyle()
     * @see PdfStyle::getHeaderStyle()
     *
     * @throws \LogicException if a row is already started
     */
    public function startHeaderRow(): static
    {
        return $this->startRow($this->getHeaderStyle());
    }

    /**
     * Starts a new row.
     *
     * @param ?PdfStyle $style the row style to use or null to use the default cell style
     *
     * @throws \LogicException if the row is already started
     */
    public function startRow(?PdfStyle $style = null): static
    {
        if ($this->isRowStarted()) {
            throw new \LogicException('A row is already started.');
        }
        $this->rowStyle = $style ?? PdfStyle::getCellStyle();

        return $this;
    }

    /**
     * Output a single cell. The default behavior is to draw the cell border (if any),
     * fill the cell (if applicable) and draw the text.
     * After this call, the current position is at the top/right of the cell.
     *
     * @param PdfDocument      $parent    the parent document
     * @param int              $index     the column index
     * @param float            $width     the cell width
     * @param float            $height    the cell height
     * @param string           $text      the cell text
     * @param PdfTextAlignment $alignment the cell alignment
     * @param PdfStyle         $style     the cell style
     * @param PdfCell          $cell      the cell
     */
    protected function drawCell(PdfDocument $parent, int $index, float $width, float $height, string $text, PdfTextAlignment $alignment, PdfStyle $style, PdfCell $cell): void
    {
        // save the current position
        [$x, $y] = $parent->GetXY();

        // style
        $style->apply($parent);

        // cell bounds
        $bounds = new PdfRectangle($x, $y, $width, $height);

        // cell background
        if ($style->isFillColor()) {
            $this->drawCellBackground($parent, $index, clone $bounds);
            $parent->SetXY($x, $y);
        }

        // cell border
        $border = $style->getBorder()->isInherited() ? $this->border : $style->getBorder();
        if ($border->isDrawable()) {
            $this->drawCellBorder($parent, $index, clone $bounds, $border);
            $parent->SetXY($x, $y);
        }

        // cell content
        $margins = $parent->getCellMargin();
        if ($cell instanceof PdfImageCell) {
            // cell image
            $imageBounds = clone $bounds;
            $imageBounds->inflate(-$margins);
            $cell->drawImage($parent, $imageBounds, $alignment);
        } elseif (Utils::isString($text)) {
            // cell text
            $line_height = PdfDocument::LINE_HEIGHT;
            if (!$style->getFont()->isDefaultSize()) {
                $line_height = $parent->getFontSize() + 2 * $margins;
            }
            $textBounds = clone $bounds;
            $indent = $style->getIndent();
            if ($indent > 0) {
                $parent->SetX($x + $indent);
                $textBounds->indent($indent);
            }
            $this->drawCellText($parent, $index, $textBounds, $text, $alignment, $line_height);

            // cell link
            if ($link = $cell->getLink()) {
                $linkBounds = clone $textBounds;
                $linkBounds->inflate(-$margins);
                $linkWidth = $parent->GetStringWidth($text);
                $linkHeight = $parent->getLinesCount($text, $textBounds->width()) * $line_height - 2 * $margins;
                $linkBounds->setSize($linkWidth, $linkHeight);
                $this->drawCellLink($parent, $linkBounds, $link);
            }
        }

        // move the position to the top-right of the cell
        $parent->SetXY($x + $width, $y);
    }

    /**
     * Draws the cell background.
     *
     * @param PdfDocument  $parent the parent document
     * @param int          $index  the column index
     * @param PdfRectangle $bounds the cell bounds
     */
    protected function drawCellBackground(PdfDocument $parent, int $index, PdfRectangle $bounds): void
    {
        // handle by listener?
        if ($this->listener && $this->listener->drawCellBackground($this, $index, $bounds)) {
            return;
        }

        // default
        $parent->rectangle($bounds, PdfRectangleStyle::FILL);
    }

    /**
     * Draws the cell border.
     *
     * @param PdfDocument  $parent the parent document
     * @param int          $index  the column index
     * @param PdfRectangle $bounds the cell bounds
     * @param PdfBorder    $border the border style
     */
    protected function drawCellBorder(PdfDocument $parent, int $index, PdfRectangle $bounds, PdfBorder $border): void
    {
        // handle by listener?
        if ($this->listener && $this->listener->drawCellBorder($this, $index, $bounds, $border)) {
            return;
        }

        // get values
        $x = $bounds->x();
        $y = $bounds->y();
        if ($border->isRectangleStyle()) {
            $parent->rectangle($bounds, $border);
        } else {
            // draw each applicable border side
            $right = $bounds->right();
            $bottom = $bounds->bottom();
            if ($border->isLeft()) {
                $parent->Line($x, $y, $x, $bottom);
            }
            if ($border->isRight()) {
                $parent->Line($right, $y, $right, $bottom);
            }
            if ($border->isTop()) {
                $parent->Line($x, $y, $right, $y);
            }
            if ($border->isBottom()) {
                $parent->Line($x, $bottom, $right, $bottom);
            }
        }
    }

    /**
     * Draws the cell link.
     *
     * @param PdfDocument  $parent the parent document
     * @param PdfRectangle $bounds the link bounds
     * @param string       $link   the link URL
     */
    protected function drawCellLink(PdfDocument $parent, PdfRectangle $bounds, string $link): void
    {
        $parent->Link($bounds->x(), $bounds->y(), $bounds->width(), $bounds->height(), $link);
    }

    /**
     * Draws the cell text.
     *
     * @param PdfDocument      $parent    the parent document
     * @param int              $index     the column index
     * @param PdfRectangle     $bounds    the cell bounds
     * @param string           $text      the cell text
     * @param PdfTextAlignment $alignment the text alignment
     * @param float            $height    the line height
     */
    protected function drawCellText(PdfDocument $parent, int $index, PdfRectangle $bounds, string $text, PdfTextAlignment $alignment, float $height): void
    {
        // handle by listener?
        if ($this->listener && $this->listener->drawCellText($this, $index, $bounds, $text, $alignment, $height)) {
            return;
        }

        // default
        $parent->MultiCell($bounds->width(), $height, $text, PdfBorder::none(), $alignment);
    }

    /**
     * Output a row.
     *
     * @param PdfDocument        $parent the parent document
     * @param float              $height the row height
     * @param string[]           $texts  the cells text
     * @param float[]            $widths the cells width
     * @param PdfStyle[]         $styles the cells style
     * @param PdfTextAlignment[] $aligns the cells alignment
     * @param PdfCell[]          $cells  the cells
     */
    protected function drawRow(PdfDocument $parent, float $height, array $texts, array $widths, array $styles, array $aligns, array $cells): void
    {
        // horizontal alignment
        if (!$this->fullWidth) {
            switch ($this->alignment) {
                case PdfTextAlignment::CENTER:
                case PdfTextAlignment::JUSTIFIED:
                    $w = \array_sum($widths);
                    $x = $parent->getLeftMargin() + ($parent->getPrintableWidth() - $w) / 2;
                    $parent->SetX($x);
                    break;
                case PdfTextAlignment::RIGHT:
                    $w = \array_sum($widths);
                    $x = $parent->GetPageWidth() - $parent->getRightMargin() - $w;
                    $parent->SetX($x);
                    break;
                default:
                    break;
            }
        }

        // output cells
        $count = \count($texts);
        for ($i = 0; $i < $count; ++$i) {
            $this->drawCell($parent, $i, $widths[$i], $height, $texts[$i], $aligns[$i], $styles[$i], $cells[$i]);
        }

        // next line
        $parent->Ln($height);
    }

    /**
     * Gets the cell height.
     *
     * @param ?string  $text  the cell text
     * @param float    $width the cell width
     * @param PdfStyle $style the cell style
     * @param PdfCell  $cell  the cell
     *
     * @return float the cell height
     */
    protected function getCellHeight(?string $text, float $width, PdfStyle $style, PdfCell $cell): float
    {
        $parent = $this->parent;

        // image?
        if ($cell instanceof PdfImageCell) {
            $height = $parent->pixels2UserUnit($cell->getHeight());

            return $height + 2 * $parent->getCellMargin();
        }

        $style->apply($parent);
        $width = \max(0, $width - $style->getIndent());
        $lines = $parent->getLinesCount($text, $width);

        $height = PdfDocument::LINE_HEIGHT;
        if (PdfFont::DEFAULT_SIZE !== $style->getFont()->getSize()) {
            $height = $parent->getFontSize() + 2 * $parent->getCellMargin();
        }

        return $lines * $height;
    }

    /**
     * Gets the total columns span.
     *
     * @return int the number of columns span
     *
     * @see PdfCell::getCols();
     */
    protected function getCellsSpan(): int
    {
        return \array_reduce($this->cells, fn (int $carry, PdfCell $cell) => $carry + $cell->getCols(), 0);
    }

    /**
     * Gets the row height.
     *
     * @param string[]   $texts  the cell texts
     * @param float[]    $widths the cell widths
     * @param PdfStyle[] $styles the cell styles
     * @param PdfCell[]  $cells  the cells
     *
     * @return float the line height
     *
     * @see PdfTableBuilder::getCellHeight()
     */
    protected function getRowHeight(array $texts, array $widths, array $styles, array $cells): float
    {
        $height = 0;
        foreach ($texts as $index => $text) {
            $height = \max($height, $this->getCellHeight($text, $widths[$index], $styles[$index], $cells[$index]));
        }

        return $height;
    }

    /**
     * @param PdfCell[] $cells
     * @param bool[]    $fixeds
     * @param float[]   $widths
     */
    private function adjustCellWidths(array $cells, array $fixeds, array &$widths): void
    {
        $count = \count($cells);
        $parent = $this->parent;

        // only 1 cell?
        if (1 === $count) {
            $widths[0] = $parent->getPrintableWidth();

            return;
        }

        // get fixed and resizable widths
        [$fixedWidth, $resizableWidth] = $this->computeCellWidths($fixeds, $widths);

        // update resizable widths
        $remainingWidth = $parent->getPrintableWidth() - $fixedWidth;
        if (!$this->isFloatZero($resizableWidth) && !$this->isFloatZero($remainingWidth) && $resizableWidth !== $remainingWidth) {
            $factor = $remainingWidth / $resizableWidth;
            for ($i = 0; $i < $count; ++$i) {
                if (!$fixeds[$i]) {
                    $widths[$i] *= $factor;
                }
            }
        }
    }

    /**
     * @param PdfCell[]   $cells
     * @param PdfColumn[] $columns
     *
     * @return array{
     *     0: string[],
     *     1: PdfStyle[],
     *     2: PdfTextAlignment[],
     *     3: float[],
     *     4: bool[]
     * }
     */
    private function computeCells(array $cells, array $columns): array
    {
        $texts = [];
        $styles = [];
        $aligns = [];
        $widths = [];
        $fixeds = [];

        $index = 0;
        foreach ($cells as $cell) {
            $texts[] = $cell->getText() ?? '';
            $styles[] = $cell->getStyle() ?? $this->rowStyle ?? PdfStyle::getCellStyle();
            $aligns[] = $cell->getAlignment() ?? $columns[$index]->getAlignment() ?? PdfTextAlignment::LEFT;

            $width = 0.0;
            $fixed = $columns[$index]->isFixed();
            for ($i = 0, $count = $cell->getCols(); $i < $count; ++$i) {
                // check if one of the columns is not fixed
                if ($fixed && !$columns[$index]->isFixed()) {
                    $fixed = false;
                }
                $width += $columns[$index]->getWidth();
                ++$index;
            }
            $widths[] = $width;
            $fixeds[] = $fixed;
        }

        return [
            $texts,
            $styles,
            $aligns,
            $widths,
            $fixeds,
        ];
    }

    /**
     * Compute fixed and resizable widths.
     *
     * @param bool[]  $fixeds
     * @param float[] $widths
     *
     * @return array{0: float, 1: float}
     */
    private function computeCellWidths(array $fixeds, array $widths): array
    {
        $fixedWidth = 0.0;
        $resizableWidth = 0.0;
        foreach ($fixeds as $index => $fixed) {
            if ($fixed) {
                $fixedWidth += $widths[$index];
            } else {
                $resizableWidth += $widths[$index];
            }
        }

        return [$fixedWidth, $resizableWidth];
    }
}
