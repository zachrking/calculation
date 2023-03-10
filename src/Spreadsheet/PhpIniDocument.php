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

namespace App\Spreadsheet;

use App\Controller\AbstractController;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Document containing PHP configuration.
 */
class PhpIniDocument extends AbstractDocument
{
    private ?string $key = null;

    /**
     * Constructor.
     *
     * @param array<string, array<string, mixed>> $content $content
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(AbstractController $controller, private readonly array $content, private readonly string $version)
    {
        parent::__construct($controller);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function render(): bool
    {
        $title = $this->trans('about.php');
        if (!empty($this->version)) {
            $title .= ' ' . $this->version;
        }
        $this->start($title);
        $this->setActiveTitle('Configuration', $this->controller);

        $content = $this->content;
        if (empty($content)) {
            $this->setCellValue($this->getActiveSheet(), 1, 1, $this->trans('about.error'))
                ->finish('A1');

            return true;
        }

        \ksort($content, \SORT_STRING | \SORT_FLAG_CASE);
        $row = $this->setHeaderValues([
            'Directive' => Alignment::HORIZONTAL_LEFT,
            'Local Value' => Alignment::HORIZONTAL_LEFT,
            'Master Value' => Alignment::HORIZONTAL_LEFT,
        ]);

        foreach ($content as $key => $value) {
            if ($this->outputGroup($row, $key)) {
                ++$row;
            }
            $row = $this->outputEntries($row, $value);
        }

        $this->getActiveSheet()
            ->getStyle('A')
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP);

        $this->setWrapText(2)
            ->setAutoSize(1)
            ->setColumnWidth(2, 50)
            ->setColumnWidth(3, 50, true)
            ->finish();

        $this->getPageSetup()
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        return true;
    }

    private function applyStyle(Worksheet $sheet, int $column, int $row, string $var): self
    {
        $color = null;
        $italic = false;
        if (\preg_match('/#[\dA-Fa-f]{6}/i', $var)) {
            $color = \substr($var, 1);
        } elseif (0 === \strcasecmp('no value', $var)) {
            $color = '7F7F7F';
            $italic = true;
        }
        if (null !== $color || $italic) {
            $font = $sheet->getCell([$column, $row])
                ->getStyle()->getFont();
            if ($italic) {
                $font->setItalic(true);
            }
            if (null !== $color) {
                $font->setColor(new Color($color));
            }
        }

        return $this;
    }

    /**
     * @param mixed $var the variable to convert
     */
    private function convert(mixed $var): string
    {
        if (\is_bool($var)) {
            return \ucfirst((string) \json_encode($var));
        } else {
            return \htmlspecialchars_decode((string) $var);
        }
    }

    /**
     * @param array<string, mixed> $entries
     */
    private function outputEntries(int $row, array $entries): int
    {
        $this->sortEntries($entries);
        $sheet = $this->getActiveSheet();
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

        /** @var mixed $entry */
        foreach ($entries as $key => $entry) {
            $keyValue = $this->convert($key);
            if (\is_array($entry)) {
                $local = $this->convert(\reset($entry));
                $master = $this->convert(\end($entry));
                $sheet->setCellValue([1, $row], $keyValue)
                    ->setCellValue([2, $row], $local)
                    ->setCellValue([3, $row], $master);
                $this->applyStyle($sheet, 2, $row, $local)
                    ->applyStyle($sheet, 3, $row, $master);
            } else {
                $entryValue = $this->convert($entry);
                $sheet->setCellValue([1, $row], $keyValue)
                    ->setCellValue([2, $row], $entryValue);
                $this->applyStyle($sheet, 2, $row, $entryValue);
            }
            ++$row;
        }

        return $row;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception if an error occurs
     */
    private function outputGroup(int $row, string $group): bool
    {
        if ($this->key !== $group) {
            $this->setRowValues($row, [$group]);
            $this->mergeCells(1, 3, $row);
            $style = $this->getActiveSheet()->getStyle("A$row");
            $style->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('F5F5F5');

            $style->getFont()->setBold(true);
            $this->key = $group;

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entries
     */
    private function sortEntries(array &$entries): void
    {
        \uksort($entries, function (string $a, string $b) use ($entries): int {
            $isArrayA = \is_array($entries[$a]);
            $isArrayB = \is_array($entries[$b]);
            if ($isArrayA !== $isArrayB) {
                return $isArrayA <=> $isArrayB;
            } else {
                return \strcasecmp($a, $b);
            }
        });
    }
}
