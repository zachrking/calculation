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

namespace App\DataTables;

use App\DataTables\Columns\DataColumn;
use App\DataTables\Tables\AbstractEntityDataTable;
use App\Entity\Calculation;
use App\Repository\CalculationRepository;
use App\Service\ApplicationService;
use DataTables\DataTablesInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Calculation data table handler.
 *
 * @author Laurent Muller
 */
class CalculationDataTable extends AbstractEntityDataTable
{
    /**
     * The datatable identifier.
     */
    public const ID = Calculation::class;

    /**
     * Constructor.
     *
     * @param ApplicationService    $application the application to get parameters
     * @param SessionInterface      $session     the session to save/retrieve user parameters
     * @param DataTablesInterface   $datatables  the datatables to handle request
     * @param CalculationRepository $repository  the repository to get entities
     */
    public function __construct(ApplicationService $application, SessionInterface $session, DataTablesInterface $datatables, CalculationRepository $repository)
    {
        parent::__construct($application, $session, $datatables, $repository);
    }

    /**
     * {@inheritdoc}
     */
    protected function createColumns(): array
    {
        //callback
        $dateFormatter = function (\DateTimeInterface $date) {
            return $this->localeDate($date);
        };
        $percentFormatter = function (float $value, Calculation $item) {
            return $this->localePercent($item->getOverallMargin());
        };

        return [
            DataColumn::identifier('id')
                ->setTitle('calculation.fields.id')
                ->setDescending()
                ->setCallback('renderStateColor')
                ->setFormatter([$this, 'localeId']),
            DataColumn::date('date')
                ->setTitle('calculation.fields.date')
                ->setDefault(true)
                ->setDescending()
                ->setFormatter($dateFormatter),
            DataColumn::instance('state.code')
                ->setTitle('calculation.fields.state')
                ->setClassName('text-state'),
            DataColumn::instance('customer')
                ->setTitle('calculation.fields.customer')
                ->setClassName('w-20 cell'),
            DataColumn::instance('description')
                ->setTitle('calculation.fields.description')
                ->setClassName('w-25 cell'),
            DataColumn::percent('overallMargin')
                ->setTitle('calculation.fields.margin')
                ->setCallback('renderTooltip')
                ->setSearchable(false)
                ->setFormatter($percentFormatter),
            DataColumn::currency('overallTotal')
                ->setTitle('calculation.fields.total')
                ->setFormatter([$this, 'localeAmount']),
            DataColumn::hidden('state.color'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOrder(): array
    {
        return ['id' => DataColumn::SORT_DESC];
    }
}
