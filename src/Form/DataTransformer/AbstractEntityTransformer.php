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

namespace App\Form\DataTransformer;

use App\Entity\AbstractEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Abstract data transformer to convert an entity to an identifier (integer).
 *
 * @template T of AbstractEntity
 *
 * @implements DataTransformerInterface<T, int>
 */
class AbstractEntityTransformer implements DataTransformerInterface
{
    /**
     * @var EntityRepository<T>
     */
    private readonly EntityRepository $repository;

    /**
     * Constructor.
     *
     * @param class-string<T> $className
     */
    public function __construct(EntityManagerInterface $manager, private readonly string $className)
    {
        $this->repository = $manager->getRepository($this->className);
    }

    /**
     * {@inheritdoc}
     *
     * @param int|string|null $value
     *
     * @return T|null
     */
    public function reverseTransform(mixed $value): ?AbstractEntity
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_numeric($value)) {
            $message = \sprintf('A "number" expected, a "%s" given.', \get_debug_type($value));
            throw new TransformationFailedException($message);
        }

        $entity = $this->repository->find((int) $value);
        if (!\is_a($entity, $this->className)) {
            $message = \sprintf('Unable to find a "%s" for the value "%s".', $this->className, $value);
            throw new TransformationFailedException($message);
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     *
     * @param AbstractEntity|null $value
     */
    public function transform(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!\is_a($value, $this->className)) {
            $message = \sprintf('A "%s" expected, a "%s" given.', $this->className, \get_debug_type($value));
            throw new TransformationFailedException($message);
        }

        return $value->getId();
    }
}
