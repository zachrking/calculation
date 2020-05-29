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

namespace App\Form;

use App\Entity\Calculation;
use App\Form\Type\PlainType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Edit calculation state type.
 *
 * @author Laurent Muller
 */
class CalculationEditStateType extends BaseType
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(Calculation::class);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $helper = new FormHelper($builder, 'calculation.fields.');

        $helper->field('id')
            ->updateOption('number_pattern', PlainType::NUMBER_IDENTIFIER)
            ->className('text-center')
            ->addPlainType(true);

        $helper->field('date')
            ->updateOption('time_format', PlainType::FORMAT_NONE)
            ->className('text-center')
            ->addPlainType(true);

        $helper->field('overallTotal')
            ->updateOption('number_pattern', PlainType::NUMBER_AMOUNT)
            ->className('text-right')
            ->addPlainType(true);

        $helper->field('customer')
            ->addPlainType(true);

        $helper->field('description')
            ->addPlainType(true);

        $helper->field('state')
            ->label('calculation.state.newstate')
            ->addStateType();
    }
}
