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

use App\Controller\IndexController;
use App\Service\ApplicationService;
use App\Service\CaptchaImageService;
use App\Traits\TranslatorFlashMessageTrait;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseNullableUserEvent;
use FOS\UserBundle\FOSUserEvents;
use ReCaptcha\ReCaptcha;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * User security events listener.
 *
 * @internal
 */
final class UserEventListener implements EventSubscriberInterface
{
    use TranslatorFlashMessageTrait;

    /**
     * @var ApplicationService
     */
    private $application;

    /**
     * @var UrlGeneratorInterface
     */
    private $generator;

    /**
     * @var ParameterBagInterface
     */
    private $params;

    /**
     * @var CaptchaImageService
     */
    private $service;

    /**
     * The user name parameter.
     *
     * @var string
     */
    private $switchParameterName;

    /**
     * Constructor.
     *
     * @param ParameterBagInterface $params              the parameters bag
     * @param SessionInterface      $session             the session
     * @param TranslatorInterface   $translator          the translator
     * @param UrlGeneratorInterface $generator           the URL generator
     * @param CaptchaImageService   $service             the captcha image service
     * @param ApplicationService    $application         the application service
     * @param string                $switchParameterName the switch user parameter name in query
     */
    public function __construct(ParameterBagInterface $params, SessionInterface $session, TranslatorInterface $translator, UrlGeneratorInterface $generator, CaptchaImageService $service, ApplicationService $application, string $switchParameterName = '_switch_user')
    {
        $this->params = $params;
        $this->session = $session;
        $this->translator = $translator;
        $this->generator = $generator;
        $this->service = $service;
        $this->application = $application;
        $this->switchParameterName = $switchParameterName;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FOSUserEvents::CHANGE_PASSWORD_SUCCESS => 'onSuccess',
            FOSUserEvents::PROFILE_EDIT_SUCCESS => 'onSuccess',
            FOSUserEvents::RESETTING_RESET_SUCCESS => ['onResetSuccess', 10],
            FOSUserEvents::RESETTING_SEND_EMAIL_INITIALIZE => 'onSendEmailInitialize',
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
            SecurityEvents::SWITCH_USER => 'onSwitchUser',
        ];
    }

    /**
     * Handle the intercative login event.
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        // check
        $request = $event->getRequest();
        $message = $this->validateCaptcha($request);
        if ($message) {
            $error = new CustomUserMessageAuthenticationException($message);
            $error->setToken($event->getAuthenticationToken());
            if ($this->session) {
                $this->session->set(Security::AUTHENTICATION_ERROR, $error);
            }
            throw $error;
        }

        // clear
        $this->service->clear();

        // message
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof UserInterface) {
            $this->loginSuccess($user);
        }
    }

    /**
     * Handle the reset success event.
     */
    public function onResetSuccess(FormEvent $event): void
    {
        $this->setHomePageResponse($event);
    }

    /**
     * Handles the send email initialize event.
     */
    public function onSendEmailInitialize(GetResponseNullableUserEvent $event): void
    {
        $request = $event->getRequest();
        $message = $this->validateCaptcha($request);
        if ($message) {
            // save exception
            $e = new CustomUserMessageAuthenticationException($message);
            if ($this->session) {
                $this->session->set(Security::AUTHENTICATION_ERROR, $e);
            }

            // get user name
            $parameters = [
                'error' => $e,
                'username' => $event->getUser()->getUsername(),
            ];

            // redirect
            $response = new RedirectResponse($this->generateUrl('fos_user_resetting_request', $parameters));
            $event->setResponse($response);
        }
    }

    /**
     * Handle the change succes event.
     */
    public function onSuccess(FormEvent $event, string $eventName): void
    {
        $this->setHomePageResponse($event);
    }

    /**
     * Handles the switch user event.
     */
    public function onSwitchUser(SwitchUserEvent $event): void
    {
        // get switch parameter
        $request = $event->getRequest();
        $key = $this->switchParameterName;
        if (!$switchValue = $request->get($key, $request->headers->get($key))) {
            return;
        }

        // current user
        $token = $event->getToken();
        $userName = \ucfirst($token->getUsername());

        // exit?
        if (SwitchUserListener::EXIT_VALUE === $switchValue) {
            $this->succesTrans('user.switch.exit.sucess', ['%name%' => $userName]);
        } elseif ($token instanceof SwitchUserToken) {
            $originalToken = $token->getOriginalToken();
            $originalUserName = \ucfirst($originalToken->getUserName());
            $this->succesTrans('user.switch.take.sucess', [
                '%orignal%' => $originalUserName,
                '%name%' => $userName,
            ]);
        } else {
            $this->succesTrans('user.switch.take.default', [
                '%name%' => $userName,
            ]);
        }
    }

    /**
     * Validate the reCaptcha token.
     *
     * @param Request $request the request
     *
     * @return string|null the error message if fail, null if valid
     */
    protected function validateRecaptcha(Request $request): ?string
    {
        // captcha used?
        if (!$this->application->isDisplayCaptcha()) {
            return null;
        }

        // get values
        $site_action = 'login';
        $token = $request->request->get('_recaptcha');
        $hostname = $request->server->get('HTTP_HOST');
        $secret = $this->getParameter('recaptcha_secret');

        // initialize
        $recaptcha = new ReCaptcha($secret);
        $recaptcha->setExpectedAction($site_action)
            ->setExpectedHostname($hostname)
            ->setChallengeTimeout(60)
            ->setScoreThreshold(0.5);

        // verify
        $result = $recaptcha->verify($token);
        if ($result->isSuccess()) {
            return null;
        }

        // build error
        $errors = $result->getErrorCodes();
        $error = empty($errors) ? 'unknown-error' : $errors[0];

        return "recaptcha.{$error}";
    }

    /**
     * Generates a URL or path for a specific route.
     *
     * @param string $route         the name of the route
     * @param mixed  $parameters    an array of parameters
     * @param int    $referenceType The type of reference to be generated (one of the constants)
     *
     * @return string the generated URL
     */
    private function generateURL(string $route, $parameters = [], $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->generator->generate($route, $parameters, $referenceType);
    }

    /**
     * Gets a container parameter.
     *
     * @param string $name the parameter name
     *
     * @return mixed the parameter value
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    private function getParameter(string $name)
    {
        return $this->params->get($name);
    }

    /**
     * Notify login success.
     *
     * @param UserInterface $user the logged user
     */
    private function loginSuccess(UserInterface $user): void
    {
        $params = [
            '%username%' => $user->getUsername(),
            '%appname%' => $this->getParameter('app_name'),
        ];
        $this->succesTrans('security.login.success', $params, 'FOSUserBundle');
    }

    /**
     * Sets home page as the response of the given event.
     *
     * @param formEvent $event the event to update
     */
    private function setHomePageResponse(FormEvent $event): void
    {
        $url = $this->generateURL(IndexController::HOME_PAGE);
        $response = RedirectResponse::create($url);
        $event->setResponse($response);
    }

    /**
     * Validate the captcha token.
     *
     * @param Request $request the request
     *
     * @return string|null the error message if fail, null if valid
     */
    private function validateCaptcha(Request $request): ?string
    {
        // captcha used?
        if (!$this->application->isDisplayCaptcha()) {
            return null;
        } elseif (!$this->service->validateTimeout()) {
            return 'captcha.timeout';
        } elseif (!$this->service->validateToken($request->get('_captcha'))) {
            return 'captcha.invalid';
        } else {
            return null;
        }
    }
}
