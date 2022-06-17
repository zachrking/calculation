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

namespace App\Traits;

use App\Enums\EntityName;
use App\Enums\EntityPermission;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Trait to check grant actions.
 */
trait CheckerTrait
{
    /**
     * The authorization checker to get user rights.
     */
    protected ?AuthorizationCheckerInterface $checker = null;

    /**
     * The granted values.
     *
     * @var bool[]
     */
    private array $rights = [];

    /**
     * Sets the authorization checker.
     */
    public function setChecker(AuthorizationCheckerInterface $checker): void
    {
        $this->checker = $checker;
    }

    /**
     * Returns if the given action for the given subject (entity name) is granted.
     */
    protected function isGranted(string|EntityPermission $action, string|EntityName $subject): bool
    {
        if ($action instanceof EntityPermission) {
            $action = $action->name;
        }
        if ($subject instanceof EntityName) {
            $subject = $subject->value;
        }
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
     * Returns if the given subject (entity name) can be added.
     */
    protected function isGrantedAdd(string $subject): bool
    {
        return $this->isGranted(EntityPermission::ADD, $subject);
    }

    /**
     * Returns if the given subject (entity name) can be deleted.
     */
    protected function isGrantedDelete(string $subject): bool
    {
        return $this->isGranted(EntityPermission::DELETE, $subject);
    }

    /**
     * Returns if the given subject (entity name) can be edited.
     */
    protected function isGrantedEdit(string $subject): bool
    {
        return $this->isGranted(EntityPermission::EDIT, $subject);
    }

    /**
     * Returns if the given subject (entity name) can be exported.
     */
    protected function isGrantedExport(string $subject): bool
    {
        return $this->isGranted(EntityPermission::EXPORT, $subject);
    }

    /**
     * Returns if the given list of subjects (entity name) can be displayed.
     */
    protected function isGrantedList(string $subject): bool
    {
        return $this->isGranted(EntityPermission::LIST, $subject);
    }

    /**
     * Returns if the given subject (entity name) can be displayed.
     */
    protected function isGrantedShow(string $subject): bool
    {
        return $this->isGranted(EntityPermission::SHOW, $subject);
    }
}
