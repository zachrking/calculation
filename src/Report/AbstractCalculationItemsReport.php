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

namespace App\Report;

use App\Controller\AbstractController;
use App\Pdf\Enums\PdfDocumentOrientation;
use App\Pdf\Enums\PdfMove;
use App\Pdf\PdfBorder;
use App\Pdf\PdfColumn;
use App\Pdf\PdfStyle;
use App\Pdf\PdfTableBuilder;
use App\Pdf\PdfTextColor;
use App\Util\FormatUtils;

/**
 * Report for calculations with invalid items.
 *
 * @extends AbstractArrayReport<array{
 *      id: int,
 *      date: \DateTimeInterface,
 *      stateCode: string,
 *      customer: string,
 *      description: string,
 *      items: array<array{
 *          description: string,
 *          quantity: float,
 *          price: float,
 *          count: int}>
 *      }>
 */
abstract class AbstractCalculationItemsReport extends AbstractArrayReport
{
    /**
     * Constructor.
     *
     * @psalm-param array<int, array{
     *      id: int,
     *      date: \DateTimeInterface,
     *      stateCode: string,
     *      customer: string,
     *      description: string,
     *      items: array<array{
     *          description: string,
     *          quantity: float,
     *          price: float,
     *          count: int}>
     *      }> $items
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function __construct(AbstractController $controller, array $items, string $title, string $description)
    {
        parent::__construct($controller, $items, PdfDocumentOrientation::LANDSCAPE);
        $this->header->setDescription($this->trans($description));
        $this->setTitleTrans($title, [], true);
    }

    /**
     * Compute the number of items.
     *
     * @param array $items the calculations
     *
     * @return int the number of items
     */
    abstract protected function computeItemsCount(array $items): int;

    /**
     * {@inheritdoc}
     */
    protected function doRender(array $entities): bool
    {
        // new page
        $this->AddPage();

        // table
        $table = $this->createTable();

        // items style
        $style = PdfStyle::getCellStyle()
            ->setTextColor(PdfTextColor::red());

        foreach ($entities as $entity) {
            $table->startRow()
                ->add(FormatUtils::formatId($entity['id']))
                ->add(FormatUtils::formatDate($entity['date']))
                ->add($entity['stateCode'])
                ->add($entity['customer'])
                ->add($entity['description'])
                ->add(text: $this->formatItems($entity['items']), style: $style)
                ->endRow();
        }
        PdfStyle::getDefaultStyle()->apply($this);

        // counters
        $parameters = [
            '%calculations%' => \count($entities),
            '%items%' => $this->computeItemsCount($entities),
        ];
        $text = $this->transCount($parameters);

        $margins = $this->setCellMargin(0);
        $this->Cell(0, self::LINE_HEIGHT, $text, PdfBorder::none(), PdfMove::NEW_LINE);
        $this->setCellMargin($margins);

        return true;
    }

    /**
     * Formats the calculation items.
     *
     * @param array $items the calculation items
     *
     * @return string the formatted items
     *
     * @psalm-param array<array{
     *          description: string,
     *          quantity: float,
     *          price: float,
     *          count: int}> $items
     */
    abstract protected function formatItems(array $items): string;

    /**
     * Translate the counters.
     *
     * @param array $parameters the parameters
     *
     * @return string the translated counters
     */
    abstract protected function transCount(array $parameters): string;

    /**
     * Creates the table.
     */
    private function createTable(): PdfTableBuilder
    {
        $table = new PdfTableBuilder($this);

        return $table->addColumns(
            PdfColumn::center($this->trans('calculation.fields.id'), 17, true),
            PdfColumn::center($this->trans('calculation.fields.date'), 20, true),
            PdfColumn::left($this->trans('calculation.fields.state'), 20, true),
            PdfColumn::left($this->trans('calculation.fields.customer'), 60),
            PdfColumn::left($this->trans('calculation.fields.description'), 60),
            PdfColumn::left($this->trans('calculation.fields.items'), 70),
        )->outputHeaders();
    }
}
