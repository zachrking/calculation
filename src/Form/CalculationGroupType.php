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

use App\Entity\CalculationGroup;
use App\Repository\CategoryRepository;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Calculation group edit type.
 *
 * @author Laurent Muller
 */
class CalculationGroupType extends BaseType
{
    /**
     * @var CategoryRepository
     */
    private $repository;

    /**
     * Constructor.
     *
     * @param CategoryRepository $repository the repository to update group's category
     */
    public function __construct(CategoryRepository $repository)
    {
        parent::__construct(CalculationGroup::class);
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // default
        $helper = new FormHelper($builder);
        $helper->field('categoryId')->addHiddenType();
        $helper->field('code')->addHiddenType();

        // items
        $helper->field('items')
            ->updateOption('prototype_name', '__itemIndex__')
            ->addCollectionType(CalculationItemType::class);

        // add event
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this, 'onPostSubmit']);
    }

    /**
     * Handles the post submit event.
     */
    public function onPostSubmit(FormEvent $event): void
    {
        // get values
        $form = $event->getForm();

        /** @var CalculationGroup $group */
        $group = $form->getData();

        // update category if needed
        if (null === $group->getCategory() && $form->has('categoryId')) {
            // update
            $id = (int) $form->get('categoryId')->getData();
            $category = $this->repository->find($id);
            $group->setCategory($category);
        }
    }
}
