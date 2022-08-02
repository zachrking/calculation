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

namespace App\Controller;

use App\Form\User\UserLoginType;
use App\Interfaces\RoleInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Controller for login user.
 */
#[AsController]
class SecurityController extends AbstractController
{
    #[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $utils): Response
    {
        $username = $utils->getLastUsername();
        $error = $utils->getLastAuthenticationError();
        $form = $this->createForm(UserLoginType::class, [
            'username' => $username,
            'remember_me' => true,
        ]);

        return $this->renderForm('security/login.html.twig', [
            'form' => $form,
            'error' => $error,
        ]);
    }

    #[IsGranted(RoleInterface::ROLE_USER)]
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method should never be reached.');
    }
}
