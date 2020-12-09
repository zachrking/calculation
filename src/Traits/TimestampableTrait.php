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

namespace App\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trait to implement the ORM TimestampableInterface.
 *
 * @author Laurent Muller
 *
 * @see App\Interfaces\TimestampableInterface
 */
trait TimestampableTrait
{
    /**
     * The creation date.
     *
     * @var ?\DateTimeInterface
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected $createdAt;

    /**
     * The creation user name.
     *
     * @var ?string
     * @ORM\Column(nullable=true)
     */
    protected $createdBy;

    /**
     * The updated date.
     *
     * @var ?\DateTimeInterface
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected $updatedAt;

    /**
     * The updated user name.
     *
     * @var ?string
     * @ORM\Column(nullable=true)
     */
    protected $updatedBy;

    /**
     * Gets the creation date.
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Gets the creation user name.
     */
    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    /**
     * Gets the updated date.
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Gets the updated user name.
     */
    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    /**
     * Sets the creation date.
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Sets the creation user name.
     */
    public function setCreatedBy(string $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * Sets the updated date.
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Sets the updated user name.
     */
    public function setUpdatedBy(string $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }
}
