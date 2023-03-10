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

use App\Entity\Group;
use App\Form\Group\GroupType;
use App\Interfaces\RoleInterface;
use App\Report\GroupsReport;
use App\Repository\CalculationGroupRepository;
use App\Repository\GroupRepository;
use App\Response\PdfResponse;
use App\Response\SpreadsheetResponse;
use App\Spreadsheet\GroupsDocument;
use App\Table\GroupTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The controller for group entities.
 *
 * @template-extends AbstractEntityController<Group>
 */
#[AsController]
#[Route(path: '/group')]
#[IsGranted(RoleInterface::ROLE_USER)]
class GroupController extends AbstractEntityController
{
    /**
     * Constructor.
     *
     * @throws \ReflectionException
     */
    public function __construct(GroupRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Add a group.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/add', name: 'group_add')]
    public function add(Request $request): Response
    {
        return $this->editEntity($request, new Group());
    }

    /**
     * Clone (copy) a group.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/clone/{id}', name: 'group_clone', requirements: ['id' => Requirement::DIGITS])]
    public function clone(Request $request, Group $item): Response
    {
        $code = $this->trans('common.clone_description', ['%description%' => $item->getCode()]);
        $clone = $item->clone($code);
        $parameters = [
            'params' => ['id' => $item->getId()],
        ];

        return $this->editEntity($request, $clone, $parameters);
    }

    /**
     * Delete a group.
     *
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    #[Route(path: '/delete/{id}', name: 'group_delete', requirements: ['id' => Requirement::DIGITS])]
    public function delete(Request $request, Group $item, CalculationGroupRepository $groupRepository, LoggerInterface $logger): Response
    {
        // external references?
        $categories = $item->countCategories();
        $calculations = $groupRepository->countGroupReferences($item);
        if (0 !== $categories || 0 !== $calculations) {
            $items = [];
            if (0 !== $categories) {
                $items[] = $this->trans('counters.categories', ['count' => $categories]);
            }
            if (0 !== $calculations) {
                $items[] = $this->trans('counters.calculations', ['count' => $calculations]);
            }
            $message = $this->trans('group.delete.failure', ['%name%' => $item->getDisplay()]);

            $parameters = [
                'item' => $item,
                'id' => $item->getId(),
                'title' => 'group.delete.title',
                'message' => $message,
                'items' => $items,
                'back_page' => $this->getDefaultRoute(),
                'back_text' => 'common.button_back_list',
            ];
            $this->updateQueryParameters($request, $parameters, $item->getId());

            return $this->render('cards/card_warning.html.twig', $parameters);
        }

        return $this->deleteEntity($request, $item, $logger);
    }

    /**
     * Edit a group.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/edit/{id}', name: 'group_edit', requirements: ['id' => Requirement::DIGITS])]
    public function edit(Request $request, Group $item): Response
    {
        return $this->editEntity($request, $item);
    }

    /**
     * Export the groups to a Spreadsheet document.
     *
     * @throws NotFoundHttpException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/excel', name: 'group_excel')]
    public function excel(): SpreadsheetResponse
    {
        $groups = $this->getEntities('code');
        if (empty($groups)) {
            $message = $this->trans('group.list.empty');
            throw $this->createNotFoundException($message);
        }
        $doc = new GroupsDocument($this, $groups);

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Export the groups to a PDF document.
     *
     * @throws NotFoundHttpException                      if no group is found
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/pdf', name: 'group_pdf')]
    public function pdf(): PdfResponse
    {
        $groups = $this->getEntities('code');
        if (empty($groups)) {
            $message = $this->trans('group.list.empty');
            throw $this->createNotFoundException($message);
        }
        $doc = new GroupsReport($this, $groups);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Show properties of a group.
     */
    #[Route(path: '/show/{id}', name: 'group_show', requirements: ['id' => Requirement::DIGITS])]
    public function show(Group $item): Response
    {
        return $this->showEntity($item);
    }

    /**
     * Render the table view.
     *
     * @throws \ReflectionException
     */
    #[Route(path: '', name: 'group_table')]
    public function table(Request $request, GroupTable $table, LoggerInterface $logger): Response
    {
        return $this->handleTableRequest($request, $table, 'group/group_table.html.twig', $logger);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditFormType(): string
    {
        return GroupType::class;
    }
}
