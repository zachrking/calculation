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

namespace App\Controller;

use App\DataTable\UserDataTable;
use App\Entity\AbstractEntity;
use App\Entity\Comment;
use App\Entity\User;
use App\Excel\ExcelResponse;
use App\Form\User\UserChangePasswordType;
use App\Form\User\UserCommentType;
use App\Form\User\UserImageType;
use App\Form\User\UserRightsType;
use App\Form\User\UserType;
use App\Interfaces\RoleInterface;
use App\Pdf\PdfResponse;
use App\Report\UsersReport;
use App\Report\UsersRightsReport;
use App\Repository\AbstractRepository;
use App\Security\EntityVoter;
use App\Spreadsheet\UserDocument;
use App\Spreadsheet\UserRightsDocument;
use App\Util\Utils;
use Doctrine\Common\Collections\Criteria;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use SlopeIt\BreadcrumbBundle\Annotation\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * The controller for user entities.
 *
 * @author Laurent Muller
 *
 * @Route("/user")
 * @IsGranted("ROLE_ADMIN")
 * @Breadcrumb({
 *     {"label" = "index.title", "route" = "homepage" },
 *     {"label" = "user.list.title", "route" = "table_user", "params" = {
 *         "id" = "$params.[id]",
 *         "search" = "$params.[search]",
 *         "sort" = "$params.[sort]",
 *         "order" = "$params.[order]",
 *         "offset" = "$params.[offset]",
 *         "limit" = "$params.[limit]",
 *         "view" = "$params.[view]"
 *     }}
 * })
 * @template-extends AbstractEntityController<User>
 */
class UserController extends AbstractEntityController
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(User::class);
    }

    /**
     * Add an user.
     *
     * @Route("/add", name="user_add")
     * @Breadcrumb({
     *     {"label" = "breadcrumb.add"}
     * })
     */
    public function add(Request $request): Response
    {
        return $this->editEntity($request, new User());
    }

    /**
     * Display the users as cards.
     *
     * @Route("/card", name="user_card")
     */
    public function card(Request $request): Response
    {
        return $this->renderCard($request, 'username');
    }

    /**
     * Delete an user.
     *
     * @Route("/delete/{id}", name="user_delete", requirements={"id" = "\d+" })
     * @Breadcrumb({
     *     {"label" = "$item.display" },
     *     {"label" = "breadcrumb.delete" }
     * })
     */
    public function delete(Request $request, User $item): Response
    {
        // same?
        if ($this->isConnectedUser($item)) {
            $this->warningTrans('user.delete.connected');

            //redirect
            return $this->getUrlGenerator()->redirect($request, $item->getId(), $this->getDefaultRoute());
        }

        $parameters = [
            'title' => 'user.delete.title',
            'message' => 'user.delete.message',
            'success' => 'user.delete.success',
            'failure' => 'user.delete.failure',
        ];

        return $this->deleteEntity($request, $item, $parameters);
    }

    /**
     * Edit an user.
     *
     * @Route("/edit/{id}", name="user_edit", requirements={"id" = "\d+" })
     * @Breadcrumb({
     *     {"label" = "$item.display" },
     *     {"label" = "breadcrumb.edit" }
     * })
     */
    public function edit(Request $request, User $item): Response
    {
        return $this->editEntity($request, $item);
    }

    /**
     * Export the customers to an Excel document.
     *
     * @Route("/excel", name="user_excel")
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no user is found
     */
    public function excel(PropertyMappingFactory $factory, StorageInterface $storage): ExcelResponse
    {
        /** @var User[] $entities */
        $entities = $this->getEntities('username');
        if (empty($entities)) {
            $message = $this->trans('user.list.empty');
            throw $this->createNotFoundException($message);
        }

        $doc = new UserDocument($this, $entities, $factory, $storage);

        return $this->renderExcelDocument($doc);
    }

    /**
     * Edit an user's image.
     *
     * @Route("/image/{id}", name="user_image", requirements={"id" = "\d+" })
     * @Breadcrumb({
     *     {"label" = "$item.display" },
     *     {"label" = "user.image.title" }
     * })
     */
    public function image(Request $request, User $item): Response
    {
        // form
        $form = $this->createForm(UserImageType::class, $item);
        if ($this->handleRequestForm($request, $form)) {
            // save
            $this->saveToDatabase($item);

            // message
            $this->succesTrans('user.image.success', ['%name%' => $item->getDisplay()]);

            // redirect
            return $this->getUrlGenerator()->redirect($request, $item->getId(), $this->getDefaultRoute());
        }

        // parameters
        $parameters = [
            'params' => ['id' => $item->getId()],
            'form' => $form->createView(),
            'item' => $item,
        ];

        // render
        return $this->render('user/user_image.html.twig', $parameters);
    }

    /**
     * Send an email from the current user to an other user.
     *
     * @Route("/message/{id}", name="user_message", requirements={"id" = "\d+" })
     * @Breadcrumb({
     *     {"label" = "$item.display" },
     *     {"label" = "user.message.title" }
     * })
     */
    public function message(Request $request, User $user, MailerInterface $mailer, LoggerInterface $logger): Response
    {
        // same user?
        if ($this->isConnectedUser($user)) {
            $this->warningTrans('user.message.connected');

            //redirect
            return $this->getUrlGenerator()->redirect($request, $user->getId(), $this->getDefaultRoute());
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $comment = new Comment(true);
        $comment->setSubject($this->getApplicationName())
            ->setFromUser($currentUser)
            ->setToUser($user);

        // create and handle request
        $form = $this->createForm(UserCommentType::class, $comment);
        if ($this->handleRequestForm($request, $form)) {
            try {
                // send
                $comment->send($mailer);
                $this->succesTrans('user.message.success', ['%name%' => $user->getDisplay()]);

                // list
                return $this->getUrlGenerator()->redirect($request, $user->getId(), $this->getDefaultRoute());
            } catch (TransportExceptionInterface $e) {
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

        // parameters
        $parameters = [
            'item' => $user,
            'form' => $form->createView(),
            'isMail' => $comment->isMail(),
            'params' => ['id' => $user->getId()],
        ];

        // render
        return $this->render('user/user_comment.html.twig', $parameters);
    }

    /**
     * Change password for an existing user.
     *
     * @Route("/password/{id}", name="user_password", requirements={"id" = "\d+" })
     * @Breadcrumb({
     *     {"label" = "$item.display" },
     *     {"label" = "user.change_password.title" }
     * })
     */
    public function password(Request $request, User $item): Response
    {
        // form
        $form = $this->createForm(UserChangePasswordType::class, $item);
        if ($this->handleRequestForm($request, $form)) {
            // save
            $this->saveToDatabase($item);

            // message
            $this->succesTrans('user.change_password.success', ['%name%' => $item->getDisplay()]);

            // redirect
            return $this->getUrlGenerator()->redirect($request, $item->getId(), $this->getDefaultRoute());
        }

        // parameters
        $parameters = [
            'item' => $item,
            'form' => $form->createView(),
            'params' => ['id' => $item->getId()],
        ];

        // show form
        return $this->render('user/user_password.html.twig', $parameters);
    }

    /**
     * Export the users to a PDF document.
     *
     * @Route("/pdf", name="user_pdf")
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no user is found
     */
    public function pdf(PropertyMappingFactory $factory, StorageInterface $storage): PdfResponse
    {
        /** @var User[] $entities */
        $entities = $this->getEntities('username');
        if (empty($entities)) {
            $message = $this->trans('user.list.empty');
            throw $this->createNotFoundException($message);
        }

        $doc = new UsersReport($this, $entities, $factory, $storage);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Edit user access rights.
     *
     * @Route("/rights/{id}", name="user_rights", requirements={"id" = "\d+" })
     * @Breadcrumb({
     *     {"label" = "$item.display" },
     *     {"label" = "user.rights.title" }
     * })
     */
    public function rights(Request $request, User $item, RoleHierarchyInterface $hierarchy): Response
    {
        // same user?
        if ($this->isConnectedUser($item)) {
            // super admin?
            $roles = $hierarchy->getReachableRoleNames($item->getRoles());
            if (!\in_array(RoleInterface::ROLE_SUPER_ADMIN, $roles, true)) {
                $this->warningTrans('user.rights.connected');

                // redirect
                return $this->getUrlGenerator()->redirect($request, $item->getId(), $this->getDefaultRoute());
            }
        }

        // form
        $form = $this->createForm(UserRightsType::class, $item);
        if ($this->handleRequestForm($request, $form)) {
            // save
            $this->getManager()->flush();

            // message
            $this->succesTrans('user.rights.success', ['%name%' => $item->getDisplay()]);

            // redirect
            return $this->getUrlGenerator()->redirect($request, $item->getId(), $this->getDefaultRoute());
        }

        // parameters
        $parameters = [
            'item' => $item,
            'form' => $form->createView(),
            'default' => EntityVoter::getRole($item),
            'params' => ['id' => $item->getId()],
        ];

        // show form
        return $this->render('user/user_rights.html.twig', $parameters);
    }

    /**
     * Export the user access rights to an Excel document.
     *
     * @Route("/rights/excel", name="user_rights_excel")
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no user is found
     */
    public function rightsExcel(): ExcelResponse
    {
        /** @var User[] $entities */
        $entities = $this->getEntities('username');
        if (empty($entities)) {
            $message = $this->trans('user.list.empty');
            throw $this->createNotFoundException($message);
        }

        $doc = new UserRightsDocument($this, $entities);

        return $this->renderExcelDocument($doc);
    }

    /**
     * Export user access rights to a PDF document.
     *
     * @Route("/rights/pdf", name="user_rights_pdf")
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no user is found
     */
    public function rightsPdf(): PdfResponse
    {
        /** @var User[] $users */
        $users = $this->getEntities('username');
        if (empty($users)) {
            $message = $this->trans('user.list.empty');
            throw $this->createNotFoundException($message);
        }

        $doc = new UsersRightsReport($this, $users);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Show the properties of a user.
     *
     * @Route("/show/{id}", name="user_show", requirements={"id" = "\d+" })
     * @Breadcrumb({
     *     {"label" = "$item.display" },
     *     {"label" = "breadcrumb.property" }
     * })
     */
    public function show(User $item): Response
    {
        return $this->showEntity($item);
    }

    /**
     * Display the users as a table view.
     *
     * @Route("", name="user_table")
     */
    public function table(Request $request, UserDataTable $table): Response
    {
        return $this->renderTable($request, $table);
    }

    /**
     * {@inheritdoc}
     */
    protected function editEntity(Request $request, AbstractEntity $item, array $parameters = []): Response
    {
        // update parameters
        $parameters['success'] = $item->isNew() ? 'user.add.success' : 'user.edit.success';

        return parent::editEntity($request, $item, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditFormType(): string
    {
        return UserType::class;
    }

    /**
     * {@inheritdoc}
     */
    protected function getEntities(?string $field = null, string $mode = Criteria::ASC, array $criterias = [], string $alias = AbstractRepository::DEFAULT_ALIAS): array
    {
        // remove super admin users if not granted
        $role = RoleInterface::ROLE_SUPER_ADMIN;
        if (!$this->isGranted($role)) {
            $criterias[] = "$alias.role <> '$role' or $alias.role IS NULL";
        }

        return parent::getEntities($field, $mode, $criterias, $alias);
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
}
