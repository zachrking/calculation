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

namespace App\Listener;

use App\Entity\User;
use App\Traits\TranslatorFlashMessageAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Listener for the user interactive login event.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class LoginListener implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;
    use TranslatorFlashMessageAwareTrait;

    /**
     * Constructor.
     */
    public function __construct(private readonly EntityManagerInterface $manager, private readonly string $appNameVersion)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    /**
     * Handles the login success event.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $this->updateUser($user);
        $this->notify($user);
    }

    /**
     * Notify the success login to the user.
     */
    private function notify(UserInterface $user): void
    {
        $params = [
            '%username%' => $user->getUserIdentifier(),
            '%appname%' => $this->appNameVersion,
        ];
        $this->successTrans('security.login.success', $params);
    }

    /**
     * Update the last login date of the given user.
     */
    private function updateUser(UserInterface $user): void
    {
        if ($user instanceof User) {
            $user->updateLastLogin();
            $this->manager->flush();
        }
    }
}
