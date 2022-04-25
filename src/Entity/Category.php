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

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a category of products and tasks.
 *
 * @author Laurent Muller
 *
 * @ORM\Table(name="sy_Category", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="unique_category_code", columns={"code"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\CategoryRepository")
 * @UniqueEntity(fields="code", message="category.unique_code")
 */
class Category extends AbstractEntity
{
    /**
     * The unique code.
     *
     * @ORM\Column(type="string", length=30, unique=true)
     * @Assert\NotBlank
     * @Assert\Length(max=30)
     */
    private ?string $code = null;

    /**
     * The description.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length(max=255)
     */
    private ?string $description = null;

    /**
     * The parent group.
     *
     * @ORM\ManyToOne(targetEntity=Group::class, inversedBy="categories")
     * @ORM\JoinColumn(name="group_id", nullable=false)
     */
    private ?Group $group = null;

    /**
     * The list of products that fall into this category.
     *
     * @ORM\OneToMany(targetEntity=Product::class, mappedBy="category")
     *
     * @var Product[]|Collection
     * @psalm-var Collection<int, Product>
     */
    private Collection $products;

    /**
     * The list of tasks that fall into this category.
     *
     * @ORM\OneToMany(targetEntity=Task::class, mappedBy="category")
     *
     * @var Task[]|Collection
     * @psalm-var Collection<int, Task>
     */
    private Collection $tasks;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->tasks = new ArrayCollection();
    }

    /**
     * Add a product.
     */
    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products[] = $product;
            $product->setCategory($this);
        }

        return $this;
    }

    public function addTask(Task $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks[] = $task;
            $task->setCategory($this);
        }

        return $this;
    }

    /**
     * Clone this category.
     *
     * @param string|null $code the new code
     */
    public function clone(?string $code = null): self
    {
        /** @var Category $copy */
        $copy = clone $this;

        if ($code) {
            $copy->setCode($code);
        }

        return $copy;
    }

    /**
     * Gets the number of products and tasks.
     */
    public function countItems(): int
    {
        return $this->countProducts() + $this->countTasks();
    }

    /**
     * Gets the number of products.
     */
    public function countProducts(): int
    {
        return $this->products->count();
    }

    /**
     * Gets the number of tasks.
     */
    public function countTasks(): int
    {
        return $this->tasks->count();
    }

    /**
     * Get code.
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * Get description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplay(): string
    {
        return (string) $this->getCode();
    }

    /**
     * Gets the code and the group code.
     */
    public function getFullCode(): ?string
    {
        $code = $this->code;
        if ($parent = $this->getGroupCode()) {
            return \sprintf('%s - %s', (string) $code, $parent);
        }

        return $code;
    }

    /**
     * Gets the group.
     */
    public function getGroup(): ?Group
    {
        return $this->group;
    }

    /**
     * Gets the group code.
     */
    public function getGroupCode(): ?string
    {
        return $this->group?->getCode();
    }

    /**
     * Gets the group identifier.
     */
    public function getGroupId(): ?int
    {
        return $this->group?->getId();
    }

    /**
     * Get products.
     *
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    /**
     * Returns if this category contains one or more products.
     */
    public function hasProducts(): bool
    {
        return !$this->products->isEmpty();
    }

    /**
     * Returns if this category contains one or more tasks.
     */
    public function hasTasks(): bool
    {
        return !$this->tasks->isEmpty();
    }

    /**
     * Remove a product.
     */
    public function removeProduct(Product $product): self
    {
        if ($this->products->removeElement($product) && $product->getCategory() === $this) {
            $product->setCategory(null);
        }

        return $this;
    }

    /**
     * Remove a task.
     */
    public function removeTask(Task $task): self
    {
        if ($this->tasks->removeElement($task) && $task->getCategory() === $this) {
            $task->setCategory(null);
        }

        return $this;
    }

    /**
     * Set code.
     */
    public function setCode(?string $code): self
    {
        $this->code = $this->trim($code);

        return $this;
    }

    /**
     * Set description.
     */
    public function setDescription(?string $description): self
    {
        $this->description = $this->trim($description);

        return $this;
    }

    /**
     * Sets the group.
     */
    public function setGroup(?Group $group): self
    {
        $this->group = $group;

        return $this;
    }
}
