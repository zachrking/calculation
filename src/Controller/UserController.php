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

namespace App\Controller;

use App\DataTables\UserDataTable;
use App\Entity\Comment;
use App\Entity\User;
use App\Form\ThemeType;
use App\Form\UserChangePasswordType;
use App\Form\UserCommentType;
use App\Form\UserImageType;
use App\Form\UserRightsType;
use App\Form\UserType;
use App\Pdf\PdfResponse;
use App\Report\UsersReport;
use App\Report\UsersRightsReport;
use App\Security\EntityVoter;
use App\Service\ThemeService;
use App\Utils\Utils;
use Doctrine\Common\Collections\Criteria;
use FOS\UserBundle\Model\UserManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * The controller for user entities.
 *
 * @Route("/user")
 */
class UserController extends EntityController
{
    /**
     * The delete route.
     */
    private const ROUTE_DELETE = 'user_delete';

    /**
     * The list route.
     */
    private const ROUTE_LIST = 'user_list';

    /**
     * The edit template.
     */
    private const TEMPLATE_EDIT = 'user/user_edit.html.twig';

    /**
     * @var UserManagerInterface
     */
    private $manager;

    /**
     * Constructor.
     *
     * @param UserManagerInterface $manager the user manager
     */
    public function __construct(UserManagerInterface $manager)
    {
        parent::__construct(User::class);
        $this->manager = $manager;
    }

    /**
     * Add an user.
     *
     * @param Request $request the request
     *
     * @Route("/add", name="user_add", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function add(Request $request): Response
    {
        return $this->editItem($request, ['item' => new User()]);
    }

    /**
     * Display the users as cards.
     *
     * @param Request $request the request
     *
     * @Route("", name="user_list", methods={"GET"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function card(Request $request): Response
    {
        return $this->renderCard($request, 'user/user_card.html.twig', 'username');
    }

    /**
     * Send comment to the web master.
     *
     * @param Request         $request the request
     * @param \Swift_Mailer   $mailer  the mailer to send message with
     * @param LoggerInterface $logger  the logger to log error
     *
     * @Route("/comment", name="user_comment", methods={"GET", "POST"})
     * @IsGranted("ROLE_USER")
     */
    public function comment(Request $request, \Swift_Mailer $mailer, LoggerInterface $logger): Response
    {
        $comment = new Comment(false);
        $comment->setSubject($this->getApplicationName())
            ->setFromUser($this->getUser())
            ->setTo($this->getParameter('mailer_user_email'), $this->getParameter('mailer_user_name'));

        // create and handle request
        $form = $this->createForm(UserCommentType::class, $comment);
        if ($this->handleFormRequest($form, $request)) {
            try {
                // send
                if ($comment->send($mailer)) {
                    $this->succesTrans('user.comment.success');
                } else {
                    $this->errorTrans('user.comment.error');
                }

                // home page
                return  $this->redirectToHomePage();
            } catch (\Swift_SwiftException $e) {
                $message = $this->trans('user.comment.error');
                $logger->error($message, [
                        'class' => Utils::getShortName($e),
                        'message' => $e->getMessage(),
                        'code' => (int) $e->getCode(),
                        'file' => $e->getFile() . ':' . $e->getLine(),
                ]);

                return $this->render('@Twig/Exception/exception.html.twig', [
                    'message' => $message,
                    'exception' => $e,
                ]);
            }
        }

        // render
        return $this->render('user/user_comment.html.twig', [
            'form' => $form->createView(),
            'isMail' => $comment->isMail(),
        ]);
    }

    /**
     * Delete an user.
     *
     * @param Request $request the request
     * @param User    $item    the user to delete
     *
     * @Route("/delete/{id}", name="user_delete", requirements={"id": "\d+" }, methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function delete(Request $request, User $item): Response
    {
        // same?
        if ($this->isConnectedUser($item)) {
            $this->warningTrans('user.delete.connected');

            //redirect
            return $this->getUrlGenerator()->redirect($request, $item->getId(), self::ROUTE_LIST);
        }

        $parameters = [
            'item' => $item,
            'page_list' => self::ROUTE_LIST,
            'page_delete' => self::ROUTE_DELETE,
            'title' => 'user.delete.title',
            'message' => 'user.delete.message',
            'success' => 'user.delete.success',
            'failure' => 'user.delete.failure',
        ];

        return $this->deletItem($request, $parameters);
    }

    /**
     * Edit an user.
     *
     * @param Request $request the request
     * @param User    $item    the user to edit
     *
     * @Route("/edit/{id}", name="user_edit", requirements={"id": "\d+" }, methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function edit(Request $request, User $item): Response
    {
        return $this->editItem($request, ['item' => $item]);
    }

    /**
     * Edit an user's image.
     *
     * @param Request $request the request
     * @param User    $item    the user to edit
     *
     * @Route("/image/{id}", name="user_image", requirements={"id": "\d+" }, methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function image(Request $request, User $item): Response
    {
        // form
        $form = $this->createForm(UserImageType::class, $item);
        if ($this->handleFormRequest($form, $request)) {
            // update
            $this->updateItem($item);

            // message
            $this->succesTrans('user.image.success', ['%name%' => $item->getDisplay()]);

            // redirect
            return $this->getUrlGenerator()->redirect($request, $item->getId(), self::ROUTE_LIST);
        }

        // render
        return $this->render('user/user_image.html.twig', [
            'form' => $form->createView(),
            'item' => $item,
        ]);
    }

    /**
     * Send an email from the current user to an other user.
     *
     * @param Request         $request the request
     * @param User            $user    the user to send mail to
     * @param \Swift_Mailer   $mailer  the mailer to send message with
     * @param LoggerInterface $logger  the logger to log error
     *
     * @Route("/message/{id}", name="user_message", requirements={"id": "\d+" }, methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function message(Request $request, User $user, \Swift_Mailer $mailer, LoggerInterface $logger): Response
    {
        // same user?
        if ($this->isConnectedUser($user)) {
            $this->warningTrans('user.message.connected');

            //redirect
            return $this->getUrlGenerator()->redirect($request, $user->getId(), self::ROUTE_LIST);
        }

        $comment = new Comment(true);
        $comment->setSubject($this->getApplicationName())
            ->setFromUser($this->getUser())
            ->setToUser($user);

        // create and handle request
        $form = $this->createForm(UserCommentType::class, $comment);
        if ($this->handleFormRequest($form, $request)) {
            try {
                // send
                if ($comment->send($mailer)) {
                    $this->succesTrans('user.message.success', ['%name%' => $user->getDisplay()]);
                } else {
                    $this->errorTrans('user.message.error');
                }

                // list
                return $this->getUrlGenerator()->redirect($request, $user->getId(), self::ROUTE_LIST);
            } catch (\Swift_SwiftException $e) {
                $message = $this->trans('user.message.error');
                $logger->error($message, [
                        'class' => Utils::getShortName($e),
                        'message' => $e->getMessage(),
                        'code' => (int) $e->getCode(),
                        'file' => $e->getFile() . ':' . $e->getLine(),
                ]);

                return $this->render('@Twig/Exception/exception.html.twig', [
                    'message' => $message,
                    'exception' => $e,
                ]);
            }
        }

        // render
        return $this->render('user/user_comment.html.twig', [
            'item' => $user,
            'form' => $form->createView(),
            'isMail' => $comment->isMail(),
        ]);
    }

    /**
     * Change password for an existing user.
     *
     * @param Request $request the request
     * @param User    $item    the user to edit
     *
     * @Route("/password/{id}", name="user_password", requirements={"id": "\d+" }, methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function password(Request $request, User $item): Response
    {
        // form
        $form = $this->createForm(UserChangePasswordType::class, $item);
        if ($this->handleFormRequest($form, $request)) {
            // update
            $this->updateItem($item);

            // message
            $this->succesTrans('password.change.success', ['%name%' => $item->getDisplay()], 'FOSUserBundle');

            // redirect
            return $this->getUrlGenerator()->redirect($request, $item->getId(), self::ROUTE_LIST);
        }

        // show form
        return $this->render('user/user_password.html.twig', [
            'selection' => $item->getId(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Export the users to a PDF document.
     *
     * @param Request                $request the request
     * @param PropertyMappingFactory $factory the factory to get mapping informations
     * @param StorageInterface       $storage the storage to get images path
     * @param KernelInterface        $kernel  the kernel to get the default image path
     *
     * @Route("/pdf", name="user_pdf", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function pdf(Request $request, PropertyMappingFactory $factory, StorageInterface $storage, KernelInterface $kernel): PdfResponse
    {
        // get users
        $users = $this->getItems();
        if (empty($users)) {
            $message = $this->trans('user.list.empty');

            throw $this->createNotFoundException($message);
        }

        // create and render report
        $report = new UsersReport($this, $factory, $storage, $kernel);
        $report->setUsers($users);

        return $this->renderDocument($report);
    }

    /**
     * Edit user access rights.
     *
     * @param Request $request the request
     * @param User    $item    the user to edit
     *
     * @Route("/rights/{id}", name="user_rights", requirements={"id": "\d+" }, methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function rights(Request $request, User $item): Response
    {
        // same and not super admin?
        if ($this->isConnectedUser($item) && !$item->hasRole(User::ROLE_SUPER_ADMIN)) {
            $this->warningTrans('user.rights.connected');

            // redirect
            return $this->getUrlGenerator()->redirect($request, $item->getId(), self::ROUTE_LIST);
        }

        // form
        $form = $this->createForm(UserRightsType::class, $item);
        if ($this->handleFormRequest($form, $request)) {
            // update
            $this->updateItem($item);

            // message
            $this->succesTrans('user.rights.success', ['%name%' => $item->getDisplay()]);

            // redirect
            return $this->getUrlGenerator()->redirect($request, $item->getId(), self::ROUTE_LIST);
        }

        // show form
        return $this->render('user/user_rights.html.twig', [
            'selection' => $item->getId(),
            'form' => $form->createView(),
            'default' => EntityVoter::getRole($item),
        ]);
    }

    /**
     * Export user access rights to a PDF document.
     *
     * @param Request $request the request
     *
     * @Route("/rights/pdf", name="user_rights_pdf", methods={"GET"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function rightsPdf(Request $request): PdfResponse
    {
        $users = $this->getItems();
        if (empty($users)) {
            $message = $this->trans('user.list.empty');

            throw $this->createNotFoundException($message);
        }

        // create and render report
        $report = new UsersRightsReport($this);
        $report->setUsers($users);

        return $this->renderDocument($report);
    }

    /**
     * Show the properties of a user.
     *
     * @param User $item the user to display properties for
     *
     * @Route("/show/{id}", name="user_show", requirements={"id": "\d+" }, methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function show(User $item): Response
    {
        return $this->showItem('user/user_show.html.twig', $item);
    }

    /**
     * Display the users as a table view.
     *
     * @param Request       $request the request
     * @param UserDataTable $table   the data table to render
     *
     * @Route("/table", name="user_table", methods={"GET", "POST"})
     */
    public function table(Request $request, UserDataTable $table): Response
    {
        return $this->showTable($request, $table, 'user/user_table.html.twig');
    }

    /**
     * Display the page to select the web site theme.
     *
     * @param Request      $request the request
     * @param ThemeService $service the service to select theme
     *
     * @Route("/theme", name="user_theme")
     * @IsGranted("ROLE_USER")
     */
    public function theme(Request $request, ThemeService $service): Response
    {
        // create form and handle request
        $theme = $service->getCurrentTheme();
        $background = $service->getThemeBackground($request);

        $data = [
            'theme' => $theme,
            'background' => $background,
        ];

        $form = $this->createForm(ThemeType::class, $data);
        if ($this->handleFormRequest($form, $request)) {
            // get values
            $data = $form->getData();
            $theme = $data['theme'];
            $background = $data['background'];
            $dark = $theme->isDark();

            // check values
            $css = $theme->getCss();
            if (ThemeService::DEFAULT_CSS === $css) {
                $css = null;
            }
            if (ThemeService::DEFAULT_BACKGROUND === $background) {
                $background = null;
            }
            if (ThemeService::DEFAULT_DARK === $dark) {
                $dark = null;
            }

            // create response and update cookies
            $response = $this->redirectToHomePage();
            $this->updateCookie($request, $response, ThemeService::KEY_CSS, $css)
                ->updateCookie($request, $response, ThemeService::KEY_BACKGROUND, $background)
                ->updateCookie($request, $response, ThemeService::KEY_DARK, (string) $dark);

            $this->succesTrans('theme.success', ['%name%' => $theme->getName()]);

            return $response;
        }

        // render
        return $this->render('user/user_theme.html.twig', [
            'asset_base' => $this->getParameter('asset_base'),
            'form' => $form->createView(),
            'themes' => $service->getThemes(),
            'theme' => $theme,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function editItem(Request $request, array $parameters): Response
    {
        /** @var User $item */
        $item = $parameters['item'];

        // update parameters
        $parameters['type'] = UserType::class;
        $parameters['template'] = self::TEMPLATE_EDIT;
        $parameters['route'] = self::ROUTE_LIST;
        $parameters['success'] = $item->isNew() ? 'user.add.success' : 'user.edit.success';

        return parent::editItem($request, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getItems(?string $field = null, string $mode = Criteria::ASC): array
    {
        $result = parent::getItems($field, $mode);

        // remove super admin users if not in role
        if (!$this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return \array_filter($result, function (User $user) {
                return !$user->isSuperAdmin();
            });
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param User $item
     */
    protected function updateItem($item): bool
    {
        // update
        $item->checkRoles();
        $this->manager->updateUser($item);

        return false;
    }

    /**
     * Returns if the given user is the same as the logged-in user.
     *
     * @param User $user the user to verify
     *
     * @return bool true if the same
     */
    private function isConnectedUser(User $user): bool
    {
        $connectedUser = $this->getUser();

        return $connectedUser instanceof User && $connectedUser->getId() === $user->getId();
    }

    /**
     * Update a response by adding or removing a cookie.
     *
     * @param Request  $request  the request to get base URL
     * @param Response $response the response to update
     * @param string   $name     the cookie name
     * @param string   $value    the cookie value or null to remove
     * @param int      $days     the number of days the cookie expires after
     */
    private function updateCookie(Request $request, Response $response, string $name, ?string $value, ?int $days = 30): self
    {
        $headers = $response->headers;
        $path = $this->getParameter('cookie_path');

        if (null !== $value) {
            $time = \strtotime("now + {$days} day");
            $secure = $this->getParameter('cookie_secure');
            $cookie = new Cookie($name, $value, $time, $path, null, $secure, true, true);
            $headers->setCookie($cookie);
        } else {
            $headers->clearCookie($name, $path);
        }

        return $this;
    }
}
