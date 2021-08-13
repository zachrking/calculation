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

namespace App\Listener;

use App\Entity\User;
use App\Traits\TranslatorFlashMessageTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Listener for the user interactive login event.
 *
 * @author Laurent Muller
 */
class LoginListener implements EventSubscriberInterface
{
    use TranslatorFlashMessageTrait;

    private string $appNameVersion;

    private EntityManagerInterface $manager;

    /**
     * Constructor.
     */
    public function __construct(EntityManagerInterface $manager, TranslatorInterface $translator, string $appNameVersion)
    {
        $this->manager = $manager;
        $this->translator = $translator;
        $this->appNameVersion = $appNameVersion;
    }

    public static function getSubscribedEvents()
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    /**
     * Handles the login success event.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();
        $this->updateUser($user);
        $this->notify($request, $user);
    }

    /**
     * Notify the success login to the user.
     */
    private function notify(Request $request, UserInterface $user): void
    {
        if ($this->setSessionFromRequest($request)) {
            $params = [
                '%username%' => $user,
                '%appname%' => $this->appNameVersion,
            ];
            $this->succesTrans('security.login.success', $params);
        }
    }

    /**
     * Update the last login date to now of the given user.
     */
    private function updateUser(UserInterface $user): void
    {
        if ($user instanceof User) {
            $user->updateLastLogin();
            $this->manager->flush();
        }
    }
}
