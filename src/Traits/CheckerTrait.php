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

use App\Interfaces\EntityVoterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Trait to check grant actions.
 *
 * @author Laurent Muller
 */
trait CheckerTrait
{
    /**
     * The authorization checker to get user rights.
     *
     * @var AuthorizationCheckerInterface
     */
    protected ?AuthorizationCheckerInterface $checker = null;

    /**
     * The granted values.
     *
     * @var bool[]
     */
    private $rights = [];

    /**
     * Returns if the given action for the given subject is granted.
     *
     * @param string $action  the action to be tested
     * @param string $subject the subject (the entity name)
     *
     * @return bool true if the action is granted
     */
    protected function isGranted(string $action, string $subject): bool
    {
        $key = "$action.$subject";
        if (!isset($this->rights[$key])) {
            if (null !== $this->checker) {
                return $this->rights[$key] = $this->checker->isGranted($action, $subject);
            }

            return $this->rights[$key] = false;
        }

        return $this->rights[$key];
    }

    /**
     * Returns if the given subject can be added.
     *
     * @param string $subject the subject (entity name)
     *
     * @return bool true if the subject can be added
     */
    protected function isGrantedAdd(string $subject): bool
    {
        return $this->isGranted(EntityVoterInterface::ATTRIBUTE_ADD, $subject);
    }

    /**
     * Returns if the given subject can be deleted.
     *
     * @param string $subject the subject (entity name)
     *
     * @return bool true if the subject can be deleted
     */
    protected function isGrantedDelete(string $subject): bool
    {
        return $this->isGranted(EntityVoterInterface::ATTRIBUTE_DELETE, $subject);
    }

    /**
     * Returns if the given subject can be edited.
     *
     * @param string $subject the subject (entity name)
     *
     * @return bool true if the subject can be edited
     */
    protected function isGrantedEdit(string $subject): bool
    {
        return $this->isGranted(EntityVoterInterface::ATTRIBUTE_EDIT, $subject);
    }

    /**
     * Returns if the given subject can be exported.
     *
     * @param string $subject the subject (entity name)
     *
     * @return bool true if the subject can be exported
     */
    protected function isGrantedExport(string $subject): bool
    {
        return $this->isGranted(EntityVoterInterface::ATTRIBUTE_EXPORT, $subject);
    }

    /**
     * Returns if the given list of subjects can be displayed.
     *
     * @param string $subject the subject (entity name)
     *
     * @return bool true if the list of subjects can be displayed
     */
    protected function isGrantedList(string $subject): bool
    {
        return $this->isGranted(EntityVoterInterface::ATTRIBUTE_LIST, $subject);
    }

    /**
     * Returns if the given subject can be displayed.
     *
     * @param string $subject the subject (entity name)
     *
     * @return bool true if the subject can be displayed
     */
    protected function isGrantedShow(string $subject): bool
    {
        return $this->isGranted(EntityVoterInterface::ATTRIBUTE_SHOW, $subject);
    }
}
