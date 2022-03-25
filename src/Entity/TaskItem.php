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

namespace App\Entity;

use App\Traits\PositionTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Represents an item of a task.
 *
 * @author Laurent Muller
 *
 * @ORM\Table(name="sy_TaskItem")
 * @ORM\Entity(repositoryClass="App\Repository\TaskItemRepository")
 * @UniqueEntity(fields={"task", "name"}, message="task_item.unique_name", errorPath="name")
 */
class TaskItem extends AbstractEntity implements \Countable
{
    use PositionTrait;

    /**
     * @ORM\OneToMany(targetEntity=TaskItemMargin::class, mappedBy="taskItem", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"minimum" = "ASC"})
     * @Assert\Valid
     *
     * @var TaskItemMargin[]|Collection
     * @psalm-var Collection<int, TaskItemMargin>
     */
    private Collection $margins;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     * @Assert\Length(max=255)
     */
    private ?string $name = null;

    /**
     * @ORM\ManyToOne(targetEntity=Task::class, inversedBy="items")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Task $task = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->margins = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        parent::__clone();

        // clone margins
        $this->margins = $this->margins->map(function (TaskItemMargin $margin) {
            return (clone $margin)->setTaskItem($this);
        });
    }

    public function addMargin(TaskItemMargin $margin): self
    {
        if (!$this->margins->contains($margin)) {
            $this->margins[] = $margin;
            $margin->setTaskItem($this);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return int the number of margins
     */
    public function count(): int
    {
        return $this->margins->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplay(): string
    {
        return (string) $this->name;
    }

    /**
     * Gets the margin for the given quantity.
     */
    public function getMargin(float $quantity): ?TaskItemMargin
    {
        foreach ($this->margins as $margin) {
            if ($margin->contains($quantity)) {
                return $margin;
            }
        }

        return null;
    }

    /**
     * @return TaskItemMargin[]|Collection
     * @psalm-return Collection<int, TaskItemMargin>
     */
    public function getMargins(): Collection
    {
        return $this->margins;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    /**
     * Returns if the task item does not contain margins.
     */
    public function isEmpty(): bool
    {
        return $this->margins->isEmpty();
    }

    public function removeMargin(TaskItemMargin $margin): self
    {
        if ($this->margins->removeElement($margin) && $margin->getTaskItem() === $this) {
            $margin->setTaskItem(null);
        }

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setTask(?Task $task): self
    {
        $this->task = $task;

        return $this;
    }

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context): void
    {
        /** @psalm-var ArrayCollection<int, TaskItemMargin> $margins */
        $margins = $this->getMargins();
        if (\count($margins) < 2) {
            return;
        }

        // sort
        $criteria = Criteria::create()
            ->orderBy(['minimum' => Criteria::ASC]);
        $margins = $margins->matching($criteria);

        $lastMin = null;
        $lastMax = null;
        foreach ($margins as $key => $margin) {
            // get values
            $min = $margin->getMinimum();
            $max = $margin->getMaximum();

            if (null === $lastMin) {
                // first time
                $lastMin = $min;
                $lastMax = $max;
            } elseif ($min <= $lastMin) {
                // the minimum is smaller than the previous maximum
                $context->buildViolation('margin.minimum_overlap')
                    ->atPath("margins[$key].minimum")
                    ->addViolation();
                break;
            } elseif ($min >= $lastMin && $min < $lastMax) {
                // the minimum is overlapping the previous margin
                $context->buildViolation('margin.minimum_overlap')
                    ->atPath("margins[$key].minimum")
                    ->addViolation();
                break;
            } elseif ($max > $lastMin && $max < $lastMax) {
                // the maximum is overlapping the previous margin
                $context->buildViolation('margin.maximum_overlap')
                    ->atPath("margins[$key].maximum")
                    ->addViolation();
                break;
            } elseif ($min !== $lastMax) {
                // the minimum is not equal to the previous maximum
                $context->buildViolation('margin.minimum_discontinued')
                    ->atPath("margins[$key].minimum")
                    ->addViolation();
                break;
            } else {
                // copy
                $lastMin = $min;
                $lastMax = $max;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getSearchTerms(): array
    {
        return [
            $this->name,
        ];
    }
}
