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

namespace App\Form\GlobalMargin;

use App\Entity\GlobalMargin;
use App\Form\AbstractMarginType;
use App\Repository\GlobalMarginRepository;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Global margin edit type.
 *
 * @author Laurent Muller
 */
class GlobalMarginType extends AbstractMarginType
{
    /**
     * The entity manager to check overlap.
     *
     * @var GlobalMarginRepository
     */
    private $repository;

    /**
     * Constructor.
     */
    public function __construct(GlobalMarginRepository $repository)
    {
        parent::__construct(GlobalMargin::class);
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        // callback
        $resolver->setDefaults([
            'constraints' => [
                new Callback([$this, 'validate']),
            ],
        ]);

        // entity manager
        $resolver->setDefined('manager');
    }

    /**
     * Validation callback.
     */
    public function validate(GlobalMargin $data, ExecutionContextInterface $context): void
    {
        $min = $data->getMinimum();
        $max = $data->getMaximum();
        $margins = $this->repository->findAll();
        foreach ($margins as $margin) {
            // same?
            if ($margin->getId() === $data->getId()) {
                continue;
            }

            // check minimum
            if ($min >= $margin->getMinimum() && $min < $margin->getMaximum()) {
                $context->buildViolation('abstract_margin.minimum_overlap')
                    ->atPath('minimum')
                    ->addViolation();
                break;
            }

            // check maximum
            if ($max > $margin->getMinimum() && $max < $margin->getMaximum()) {
                $context->buildViolation('abstract_margin.maximum_overlap')
                    ->atPath('maximum')
                    ->addViolation();
                break;
            }
        }
    }
}
