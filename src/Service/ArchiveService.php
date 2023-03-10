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

namespace App\Service;

use App\Entity\Calculation;
use App\Entity\CalculationState;
use App\Form\CalculationState\CalculationStateListType;
use App\Form\FormHelper;
use App\Model\ArchiveQuery;
use App\Model\ArchiveResult;
use App\Repository\CalculationRepository;
use App\Repository\CalculationStateRepository;
use App\Traits\SessionAwareTrait;
use App\Traits\TranslatorAwareTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Service to archive calculations.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ArchiveService implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;
    use SessionAwareTrait;
    use TranslatorAwareTrait;

    private const KEY_DATE = 'archive.date';
    private const KEY_SIMULATE = 'archive.simulate';
    private const KEY_SOURCES = 'archive.sources';
    private const KEY_TARGET = 'archive.target';

    /**
     * Constructor.
     */
    public function __construct(
        private readonly CalculationRepository $calculationRepository,
        private readonly CalculationStateRepository $stateRepository,
        private readonly FormFactoryInterface $factory
    ) {
    }

    /**
     * Create the edit form.
     */
    public function createForm(ArchiveQuery $query): FormInterface
    {
        // create helper
        $builder = $this->factory->createBuilder(FormType::class, $query);
        $helper = new FormHelper($builder, 'archive.fields.');
        $sources = $this->getSources(false);

        // add fields
        $helper->field('date')
            ->updateAttributes([
                'min' => $this->getDateMinConstraint($sources),
                'max' => $this->getDateMaxConstraint($sources),
            ])
            ->addDateType();

        $helper->field('sources')
            ->updateOptions([
                'multiple' => true,
                'expanded' => true,
                'group_by' => fn () => null,
                'query_builder' => static fn (CalculationStateRepository $repository): QueryBuilder => $repository->getEditableQueryBuilder(),
            ])
            ->labelClass('switch-custom')
            ->widgetClass('form-check form-check-inline')
            ->add(CalculationStateListType::class);

        $helper->field('target')
            ->updateOptions([
                'group_by' => fn () => null,
                'query_builder' => static fn (CalculationStateRepository $repository): QueryBuilder => $repository->getNotEditableQueryBuilder(),
            ])
            ->add(CalculationStateListType::class);

        $helper->addCheckboxSimulate()
            ->addCheckboxConfirm($this->getTranslator(), $query->isSimulate());

        return $helper->createForm();
    }

    /**
     * Create the archive query.
     */
    public function createQuery(): ArchiveQuery
    {
        $query = new ArchiveQuery();
        $query->setSources($this->getSources(true))
            ->setSimulate($this->isSimulate())
            ->setTarget($this->getTarget())
            ->setDate($this->getDate());

        return $query;
    }

    /**
     * Process the archive query and return the result.
     */
    public function processQuery(ArchiveQuery $query): ArchiveResult
    {
        $date = $query->getDate();
        $target = $query->getTarget();
        $simulate = $query->isSimulate();
        $result = new ArchiveResult();
        $result->setDate($date)
            ->setTarget($target)
            ->setSimulate($simulate);

        $calculations = $this->getCalculations($date, $query->getSources());
        foreach ($calculations as $calculation) {
            $oldState = $calculation->getState();
            if (null !== $oldState && $oldState !== $target) {
                $result->addCalculation($oldState, $calculation);
                $calculation->setState($target);
            }
        }
        if (!$simulate && $result->isValid()) {
            $this->calculationRepository->flush();
        }

        return $result;
    }

    /**
     * Save the query values to the session.
     */
    public function saveQuery(ArchiveQuery $query): void
    {
        $date = $query->isSimulate() ? $query->getDate()->getTimestamp() : null;
        $this->setSessionValues([
            self::KEY_SOURCES => $this->getIds($query->getSources()),
            self::KEY_TARGET => $query->getTarget()?->getId(),
            self::KEY_SIMULATE => $query->isSimulate(),
            self::KEY_DATE => $date,
        ]);
    }

    /**
     * @param CalculationState[] $sources
     */
    private function createQueryBuilder(array $sources, ?\DateTimeInterface $date = null): QueryBuilder
    {
        $builder = $this->calculationRepository
            ->createQueryBuilder('c');
        if (!empty($sources)) {
            $ids = $this->getIds($sources);
            $builder->andWhere('c.state IN (:states)')
                ->setParameter('states', $ids);
        }
        if (null !== $date) {
            $builder->andWhere('c.date <= :date')
                ->setParameter('date', $date, Types::DATE_MUTABLE);
        }

        return $builder;
    }

    /**
     * Gets the calculations to archive.
     *
     * @param CalculationState[] $sources
     *
     * @return Calculation[]
     */
    private function getCalculations(\DateTimeInterface $date, array $sources): array
    {
        if (empty($sources)) {
            return [];
        }

        $builder = $this->createQueryBuilder($sources, $date);

        /** @var Calculation[] $result */
        $result = $builder->getQuery()->getResult();

        return $result;
    }

    private function getDate(): \DateTimeInterface
    {
        $date = $this->getSessionDate(self::KEY_DATE);
        if (null !== $date) {
            return $date;
        }

        $sources = $this->getSources(false);
        $minDate = $this->getDateMin($sources);
        if (!$minDate instanceof \DateTime) {
            return (new \DateTime())->sub(new \DateInterval('P6M'));
        }
        $interval = new \DateInterval('P1M');
        $minDate->add($interval);
        $maxDate = $this->getDateMax($sources);
        if (null !== $maxDate && $minDate >= $maxDate) {
            return $maxDate->sub($interval);
        }

        return $minDate;
    }

    /**
     * @param CalculationState[] $sources
     */
    private function getDateMax(array $sources): ?\DateTime
    {
        $builder = $this->createQueryBuilder($sources)
            ->select('MAX(c.date)');

        try {
            /** @var string|null $date */
            $date = $builder->getQuery()->getSingleScalarResult();
            if (null !== $date) {
                return new \DateTime($date);
            }
        } catch (\Exception) {
        }

        return null;
    }

    /**
     * @param CalculationState[] $sources
     */
    private function getDateMaxConstraint(array $sources): ?string
    {
        $date = $this->getDateMax($sources);

        return $date?->sub(new \DateInterval('P1M'))?->format('Y-m-d');
    }

    /**
     * @param CalculationState[] $sources
     */
    private function getDateMin(array $sources): ?\DateTime
    {
        $builder = $this->createQueryBuilder($sources)
            ->select('MIN(c.date)');

        try {
            /** @var string|null $date */
            $date = $builder->getQuery()->getSingleScalarResult();
            if (null !== $date) {
                return new \DateTime($date);
            }
        } catch (\Exception) {
        }

        return null;
    }

    /**
     * @param CalculationState[] $sources
     */
    private function getDateMinConstraint(array $sources): ?string
    {
        $date = $this->getDateMin($sources);

        return $date?->format('Y-m-d');
    }

    /**
     * @param CalculationState[] $sources
     *
     * @return int[]
     */
    private function getIds(array $sources): array
    {
        return \array_map(fn (CalculationState $state): int => (int) $state->getId(), $sources);
    }

    /**
     * @return CalculationState[]
     */
    private function getSources(bool $useSession): array
    {
        /** @var CalculationState[] $sources */
        $sources = $this->stateRepository->getEditableQueryBuilder()->getQuery()->getResult();

        if ($useSession) {
            /** @var int[] $ids */
            $ids = $this->getSessionValue(self::KEY_SOURCES, []);
            if (!empty($ids)) {
                return \array_filter($sources, fn (CalculationState $state): bool => \in_array($state->getId(), $ids, true));
            }
        }

        return $sources;
    }

    private function getTarget(): ?CalculationState
    {
        $id = $this->getSessionInt(self::KEY_TARGET, 0);
        if (0 !== $id) {
            return $this->stateRepository->find($id);
        }

        return null;
    }

    private function isSimulate(): bool
    {
        return $this->isSessionBool(self::KEY_SIMULATE, true);
    }
}
