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

namespace App\Listener;

use App\Interfaces\TimestampableInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Listener to update timestampable entities.
 *
 * @author Laurent Muller
 *
 * @see TimestampableInterface
 */
class TimestampableListener
{
    /**
     * @var Security
     */
    private $security;

    /**
     * the default user name.
     */
    private $username;

    /**
     * Constructor.
     */
    public function __construct(Security $security, TranslatorInterface $translator)
    {
        $this->security = $security;
        $this->username = $translator->trans('calculation.edit.empty_user');
    }

    /**
     * Handles the flush event.
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getEntityManager();
        $unitOfWork = $em->getUnitOfWork();
        $entities = \array_merge($unitOfWork->getScheduledEntityInsertions(), $unitOfWork->getScheduledEntityUpdates());
        if (empty($entities)) {
            return;
        }

        foreach ($entities as $entity) {
            if ($entity instanceof TimestampableInterface) {
                // update
                $this->updateEntity($entity);
                $em->persist($entity);

                // recompute
                $class_name = \get_class($entity);
                $metadata = $em->getClassMetadata($class_name);
                $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
            }
        }
    }

    /**
     * Gets the user name.
     */
    private function getUserName(): string
    {
        if ($user = $this->security->getUser()) {
            return $user->getUsername();
        }

        // default user
        return $this->username;
    }

    /**
     * Update the given entity.
     */
    private function updateEntity(TimestampableInterface $entity): void
    {
        $user = $this->getUserName();
        $date = new \DateTimeImmutable();
        if (null === $entity->getCreatedAt()) {
            $entity->setCreatedAt($date);
        }
        if (null === $entity->getCreatedBy()) {
            $entity->setCreatedBy($user);
        }
        $entity->setUpdatedAt($date)
            ->setUpdatedBy($user);
    }
}
