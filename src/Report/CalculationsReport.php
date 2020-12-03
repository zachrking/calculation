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

namespace App\Report;

use App\Controller\AbstractController;
use App\Entity\Calculation;
use App\Pdf\PdfColumn;
use App\Pdf\PdfConstantsInterface;
use App\Pdf\PdfGroupTableBuilder;
use App\Pdf\PdfStyle;
use App\Pdf\PdfTextColor;
use App\Traits\MathTrait;
use App\Util\FormatUtils;

/**
 * Report for the list of calculations.
 *
 * @author Laurent Muller
 */
class CalculationsReport extends AbstractArrayReport
{
    use MathTrait;

    /**
     * Set if the calculations are grouped by state.
     *
     * @var bool
     */
    protected $grouped = false;

    /**
     * The minimum margin style.
     *
     * @var PdfStyle|null
     */
    protected $marginStyle;

    /**
     * The minimum margin.
     *
     * @var float
     */
    protected $minMargin;

    /**
     * Constructor.
     *
     * @param AbstractController $controller the parent controller
     * @param Calculation[]      $entities   the calculations to render
     * @param bool               $grouped    true if calculations are grouped by state
     */
    public function __construct(AbstractController $controller, array $entities, bool $grouped = true)
    {
        parent::__construct($controller, $entities, self::ORIENTATION_LANDSCAPE);
        $this->minMargin = $controller->getApplication()->getMinMargin();
        $this->grouped = $grouped;
    }

    /**
     * {@inheritdoc}
     */
    protected function doRender(array $entities): bool
    {
        // title
        $this->setTitleTrans('calculation.list.title');

        // new page
        $this->AddPage();

        // grouping?
        if ($this->grouped) {
            $table = $this->outputByGroup($entities);
        } else {
            $table = $this->outputByList($entities);
        }

        // totals
        $items = 0.0;
        $overall = 0.0;

        /** @var Calculation $entity */
        foreach ($entities as $entity) {
            $items += $entity->getItemsTotal();
            $overall += $entity->getOverallTotal();
        }
        $margins = $this->isFloatZero($items) ? 0 : $this->safeDivide($overall, $items) - 1;

        $text = $this->trans('common.count', [
            '%count%' => \count($entities),
        ]);

        $columns = $table->getColumnsCount() - 3;
        $table->getColumns()[0]->setAlignment(PdfConstantsInterface::ALIGN_LEFT)
            ->setFixed(false);
        $table->startHeaderRow()
            ->add($text, $columns)
            ->add(FormatUtils::formatAmount($items))
            ->add(FormatUtils::formatPercent($margins))
            ->add(FormatUtils::formatAmount($overall))
            ->endRow();

        return true;
    }

    /**
     * Creates the table.
     *
     * @param bool $grouped true if calculations are grouped by state
     */
    private function createTable(bool $grouped): PdfGroupTableBuilder
    {
        // create table
        $columns = [
            PdfColumn::center($this->trans('calculation.fields.id'), 17, true),
            PdfColumn::center($this->trans('calculation.fields.date'), 20, true),
        ];
        if (!$grouped) {
            $columns[] = PdfColumn::left($this->trans('calculation.fields.state'), 12, false);
        }
        $columns = \array_merge($columns, [
            PdfColumn::left($this->trans('calculation.fields.customer'), 50, false),
            PdfColumn::left($this->trans('calculation.fields.description'), 50, false),
            PdfColumn::right($this->trans('report.calculation.amount'), 25, true),
            PdfColumn::right($this->trans('report.calculation.margin_percent'), 20, true),
            PdfColumn::right($this->trans('calculation.fields.total'), 25, true),
        ]);

        $table = new PdfGroupTableBuilder($this);
        $table->addColumns($columns)
            ->outputHeaders();

        return $table;
    }

    /**
     * Gets the style for the margin below.
     *
     * @param Calculation $calculation the calculation to get style for
     *
     * @return PdfStyle|null the margin style, if applicable, null otherwise
     */
    private function getMarginStyle(Calculation $calculation): ?PdfStyle
    {
        if ($calculation->isMarginBelow($this->minMargin)) {
            if (!$this->marginStyle) {
                $this->marginStyle = PdfStyle::getCellStyle()->setTextColor(PdfTextColor::red());
            }

            return $this->marginStyle;
        }

        return null;
    }

    /**
     * Outputs the calculations grouped by state.
     *
     * @param Calculation[] $entities the calculations to render
     *
     * @return PdfGroupTableBuilder the table builder
     */
    private function outputByGroup(array $entities): PdfGroupTableBuilder
    {
        // groups the calculations by state
        $groups = [];
        foreach ($entities as $entity) {
            $key = $entity->getStateCode();
            $groups[$key][] = $entity;
        }

        // create table
        $table = $this->createTable(true);

        // output
        foreach ($groups as $group => $items) {
            $table->setGroupKey($group);
            foreach ($items as $item) {
                $this->outputItem($table, $item, true);
            }
        }

        return $table;
    }

    /**
     * Ouput the calculations as list.
     *
     * @param Calculation[] $entities the calculations to render
     *
     * @return PdfGroupTableBuilder the table builder
     */
    private function outputByList(array $entities): PdfGroupTableBuilder
    {
        // create table
        $table = $this->createTable(false);

        // output
        foreach ($entities as $entity) {
            $this->outputItem($table, $entity, false);
        }

        return $table;
    }

    /**
     * Output a single calculation.
     *
     * @param PdfGroupTableBuilder $table        the table to write in
     * @param Calculation          $c            the calculation to output
     * @param bool                 $groupByState true if grouped by state
     */
    private function outputItem(PdfGroupTableBuilder $table, Calculation $c, bool $groupByState): void
    {
        // margin below style
        $style = $this->getMarginStyle($c);

        $table->startRow()
            ->add(FormatUtils::formatId($c->getId()))
            ->add(FormatUtils::formatDate($c->getDate()));

        if (!$groupByState) {
            $table->add($c->getStateCode());
        }

        $table->add($c->getCustomer())
            ->add($c->getDescription())
            ->add(FormatUtils::formatAmount($c->getItemsTotal()))
            ->add(FormatUtils::formatPercent($c->getOverallMargin()), 1, $style)
            ->add(FormatUtils::formatAmount($c->getOverallTotal()))
            ->endRow();
    }
}
