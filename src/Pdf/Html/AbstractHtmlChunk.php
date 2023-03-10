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

namespace App\Pdf\Html;

use App\Pdf\Enums\PdfTextAlignment;
use App\Pdf\PdfBorder;
use App\Pdf\PdfDocument;
use App\Pdf\PdfFillColor;
use App\Pdf\PdfFont;
use App\Pdf\PdfTextColor;
use App\Report\HtmlReport;

/**
 * Represents an HTML chunk.
 */
abstract class AbstractHtmlChunk implements HtmlConstantsInterface
{
    /**
     * The class name.
     */
    protected ?string $className = null;

    /**
     * The css style.
     */
    protected ?string $css = null;

    /**
     * The parent chunk.
     */
    protected ?HtmlParentChunk $parent = null;

    /**
     * The style.
     */
    protected ?HtmlStyle $style = null;

    /**
     * Constructor.
     *
     * @param string           $name   the tag name
     * @param ?HtmlParentChunk $parent the parent chunk
     */
    public function __construct(protected string $name, ?HtmlParentChunk $parent = null)
    {
        // add to parent
        $parent?->add($this);

        // style
        $this->updateStyle();
    }

    /**
     * Apply this style (if any) to the given report.
     *
     * @param HtmlReport $report the report to update
     */
    public function applyStyle(HtmlReport $report): static
    {
        $this->style?->apply($report);

        return $this;
    }

    /**
     * Finds the parent for the given the tag names.
     */
    public function findParent(string ...$names): ?HtmlParentChunk
    {
        $parent = $this->parent;
        while (null !== $parent && !$parent->is(...$names)) {
            $parent = $parent->getParent();
        }

        return $parent;
    }

    /**
     * Gets the text alignment from this style or left, if none.
     */
    public function getAlignment(): PdfTextAlignment
    {
        return $this->style?->getAlignment() ?? PdfTextAlignment::LEFT;
    }

    /**
     * Gets the bottom margin from this style or 0 if none.
     */
    public function getBottomMargin(): float
    {
        return $this->style?->getBottomMargin() ?? 0;
    }

    /**
     * Gets the class name.
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * Gets the CSS style.
     */
    public function getCss(): ?string
    {
        return $this->css;
    }

    /**
     * Gets the left margin from this style or 0 if none.
     */
    public function getLeftMargin(): float
    {
        return $this->style?->getLeftMargin() ?? 0;
    }

    /**
     * Gets the tag name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the parent.
     */
    public function getParent(): ?HtmlParentChunk
    {
        return $this->parent;
    }

    /**
     * Gets the right margin from this style or 0 if none.
     */
    public function getRightMargin(): float
    {
        return $this->style?->getRightMargin() ?? 0;
    }

    /**
     * Gets the style.
     */
    public function getStyle(): ?HtmlStyle
    {
        return $this->style;
    }

    /**
     * Gets the top margin from this style or 0 if none.
     */
    public function getTopMargin(): float
    {
        return $this->style?->getTopMargin() ?? 0;
    }

    /**
     * Returns if a style is defined.
     */
    public function hasStyle(): bool
    {
        return null !== $this->style;
    }

    /**
     * Gets index of this chunk.
     *
     * @return int the index; -1 if root
     */
    public function index(): int
    {
        return $this->parent?->indexOf($this) ?? -1;
    }

    /**
     * Returns if this tag name match the given one of the list of names.
     *
     * @return bool true if match
     */
    public function is(string ...$names): bool
    {
        foreach ($names as $name) {
            if (0 === \strcasecmp($this->name, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns if a new line must add at the end of the report output.
     */
    public function isNewLine(): bool
    {
        return false;
    }

    /**
     * Output this chunk to the given report.
     */
    public function output(HtmlReport $report): void
    {
        // apply style
        $this->applyStyle($report);

        // write text
        $text = $this->getOutputText();
        if (\is_string($text) && '' !== $text) {
            $this->outputText($report, $text);
        }
    }

    /**
     * Sets the class name.
     */
    public function setClassName(?string $className): static
    {
        // clear
        $this->className = null;

        // check names
        if ($className) {
            $names = \explode(' ', \strtolower($className));
            $className = \array_reduce($names, function (string $carry, string $name) {
                if (!empty($name = \trim($name)) && !\str_contains($carry, $name)) {
                    return \trim($carry . ' ' . $name);
                }

                return $carry;
            }, '');

            if (!empty($className)) {
                $this->className = $className;
            }
        }

        return $this->updateStyle();
    }

    /**
     * Sets the CSS style.
     */
    public function setCss(?string $css): static
    {
        $this->css = $css;

        return $this;
    }

    /**
     * Sets the style.
     */
    public function setStyle(?HtmlStyle $style): static
    {
        $this->style = $style;

        return $this;
    }

    /**
     * Apply the given font (if any), call the callback and restore the previous font.
     * Example:
     * <pre>
     * <code>
     *      $this->applyFont($report, $myFont, function(HtmlReport $report) {
     *          ...
     *      });
     * </code>
     * </pre>.
     *
     * @param HtmlReport                $report   the report to set and restore font
     * @param ?PdfFont                  $font     the font to apply
     * @param callable(HtmlReport):void $callback the callback to call after the font has been set. The report is passed as argument.
     */
    protected function applyFont(HtmlReport $report, ?PdfFont $font, callable $callback): void
    {
        if (null !== $font) {
            $oldFont = $report->applyFont($font);
            $callback($report);
            $report->applyFont($oldFont);
        } else {
            $callback($report);
        }
    }

    /**
     * Apply the given margins (if different from 0), call the callback and restore the previous margins.
     * Example:
     * <pre>
     * <code>
     *      $this->applyMargins($report, 10, 25, function(HtmlReport $report) {
     *          ...
     *      });
     * </code>
     * </pre>.
     *
     * @param HtmlReport                $report      the report to set and restore margins
     * @param float                     $leftMargin  the left margin to add
     * @param float                     $rightMargin the right margin to add
     * @param callable(HtmlReport):void $callback    the callback to call after the margins has been set. The report is passed as argument.
     */
    protected function applyMargins(HtmlReport $report, float $leftMargin, float $rightMargin, callable $callback): void
    {
        // get margins
        $oldLeft = $report->getLeftMargin();
        $oldRight = $report->getRightMargin();
        $newLeft = $oldLeft + $leftMargin;
        $newRight = $oldRight + $rightMargin;

        // apply new margins
        if ($newLeft !== $oldLeft) {
            $report->updateLeftMargin($newLeft);
        }
        if ($newRight !== $oldRight) {
            $report->updateRightMargin($newRight);
        }

        // call function
        $callback($report);

        // restore old margins
        if ($newLeft !== $oldLeft) {
            $report->updateLeftMargin($oldLeft);
        }
        if ($newRight !== $oldRight) {
            $report->updateRightMargin($oldRight);
        }
    }

    /**
     * Gets the report output text.
     */
    protected function getOutputText(): ?string
    {
        return null;
    }

    /**
     * Output the given text to the report.
     * By default, call the <code>write</code> method of the report.
     */
    protected function outputText(HtmlReport $report, string $text): void
    {
        $height = \max($report->getFontSize(), PdfDocument::LINE_HEIGHT);
        $report->Write($height, $text);
    }

    /**
     * Parses the border class.
     *
     * @param HtmlStyle $style the style to update
     * @param string    $class the border class name
     */
    protected function parseBorders(HtmlStyle $style, string $class): void
    {
        switch ($class) {
            case 'border':
                $style->setBorder(PdfBorder::ALL);
                break;

            case 'border-top':
                $style->setBorder(PdfBorder::TOP);
                break;

            case 'border-right':
                $style->setBorder(PdfBorder::RIGHT);
                break;

            case 'border-bottom':
                $style->setBorder(PdfBorder::BOTTOM);
                break;

            case 'border-left':
                $style->setBorder(PdfBorder::LEFT);
                break;

            case 'border-0':
                $style->setBorder(PdfBorder::NONE);
                break;

            case 'border-top-0':
            case 'border-right-0':
            case 'border-bottom-0':
            case 'border-left-0':
                break;
        }
    }

    /**
     * Parses the margins class.
     *
     * @param HtmlStyle $style the style to update
     * @param string    $class the margins class name
     */
    protected function parseMargins(HtmlStyle $style, string $class): void
    {
        $pattern = '/m[tblrxy]{?}-[012345]/';
        if (\preg_match($pattern, $class)) {
            $value = (float) $class[-1];
            switch ($class[1]) {
                case 't':
                    $style->setTopMargin($value);
                    break;
                case 'b':
                    $style->setBottomMargin($value);
                    break;
                case 'l':
                    $style->setLeftMargin($value);
                    break;
                case 'r':
                    $style->setRightMargin($value);
                    break;
                case 'x':
                    $style->setXMargins($value);
                    break;
                case 'y':
                    $style->setYMargins($value);
                    break;
                default: // '-' = all
                    $style->setMargins($value);
                    break;
            }
        }
    }

    /**
     * Sets the parent.
     */
    protected function setParent(?HtmlParentChunk $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Update this style, depending on the CSS.
     */
    protected function updateCss(): static
    {
        if ($this->css) {
            $matches = [];
            if (\preg_match_all("/([\w-]+)\s*:\s*([^;]+)\s*;?/", $this->css, $matches, \PREG_SET_ORDER)) {
                $update = false;
                $style = $this->getStyle() ?? new HtmlStyle();

                foreach ($matches as $match) {
                    $name = \strtolower($match[1]);
                    $value = \trim($match[2]);

                    switch ($name) {
                        case 'color':
                            $color = PdfTextColor::create($value);
                            if (null !== $color) {
                                $style->setTextColor($color);
                                $update = true;
                            }
                            break;

                        case 'background-color':
                            $color = PdfFillColor::create($value);
                            if (null !== $color) {
                                $style->setFillColor($color);
                                $update = true;
                            }
                            break;
                    }
                }

                if ($update) {
                    $this->setStyle($style);
                }
            }
        }

        return $this;
    }

    /**
     * Update this style, depending on the tag name and class.
     */
    protected function updateStyle(): static
    {
        // create style by tag name
        $style = HtmlStyleFactory::create($this->name);
        if (!$style instanceof HtmlStyle) {
            return $this->setStyle(null);
        }

        // class
        if ($this->className) {
            /** @var string[] $classNames */
            $classNames = \preg_split('/\s+/m', $this->className);
            foreach ($classNames as $class) {
                switch ($class) {
                    case 'text-left':
                        $style->setAlignment(PdfTextAlignment::LEFT);
                        break;

                    case 'text-right':
                        $style->setAlignment(PdfTextAlignment::RIGHT);
                        break;

                    case 'text-center':
                        $style->setAlignment(PdfTextAlignment::CENTER);
                        break;

                    case 'text-justify':
                        $style->setAlignment(PdfTextAlignment::JUSTIFIED);
                        break;

                    case 'font-weight-bold':
                        $style->bold(true);
                        break;

                    case 'font-italic':
                        $style->italic(true);
                        break;

                    case 'font-weight-normal':
                        $style->regular();
                        break;

                    case 'text-monospace':
                        $style->regular()->getFont()->setName(PdfFont::NAME_COURIER);
                        break;

                    case 'text-primary':
                        $color = PdfTextColor::create(HtmlBootstrapColors::PRIMARY);
                        if (null !== $color) {
                            $style->setTextColor($color);
                        }
                        break;

                    case 'text-secondary':
                        $color = PdfTextColor::create(HtmlBootstrapColors::SECONDARY);
                        if (null !== $color) {
                            $style->setTextColor($color);
                        }
                        break;

                    case 'text-success':
                        $color = PdfTextColor::create(HtmlBootstrapColors::SUCCESS);
                        if (null !== $color) {
                            $style->setTextColor($color);
                        }
                        break;

                    case 'text-danger':
                        $color = PdfTextColor::create(HtmlBootstrapColors::DANGER);
                        if (null !== $color) {
                            $style->setTextColor($color);
                        }
                        break;

                    case 'text-warning':
                        $color = PdfTextColor::create(HtmlBootstrapColors::WARNING);
                        if (null !== $color) {
                            $style->setTextColor($color);
                        }
                        break;

                    case 'text-info':
                        $color = PdfTextColor::create(HtmlBootstrapColors::INFO);
                        if (null !== $color) {
                            $style->setTextColor($color);
                        }
                        break;

                    default:
                        $this->parseMargins($style, $class);
                        break;
                }
            }
        }

        return $this->setStyle($style);
    }
}
