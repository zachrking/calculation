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

use App\Entity\AbstractEntity;
use App\Entity\Category;
use App\Form\Category\CategoryType;
use App\Interfaces\RoleInterface;
use App\Report\CategoriesReport;
use App\Repository\CalculationCategoryRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\TaskRepository;
use App\Response\PdfResponse;
use App\Response\SpreadsheetResponse;
use App\Spreadsheet\CategoriesDocument;
use App\Table\CategoryTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The controller for category entities.
 *
 * @template-extends AbstractEntityController<Category>
 */
#[AsController]
#[Route(path: '/category')]
#[IsGranted(RoleInterface::ROLE_USER)]
class CategoryController extends AbstractEntityController
{
    /**
     * Constructor.
     *
     * @throws \ReflectionException
     */
    public function __construct(CategoryRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Add a category.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/add', name: 'category_add')]
    public function add(Request $request): Response
    {
        return $this->editEntity($request, new Category());
    }

    /**
     * Clone (copy) a category.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/clone/{id}', name: 'category_clone', requirements: ['id' => Requirement::DIGITS])]
    public function clone(Request $request, Category $item): Response
    {
        $code = $this->trans('common.clone_description', ['%description%' => $item->getCode()]);
        $clone = $item->clone($code);
        $parameters = [
            'params' => ['id' => $item->getId()],
        ];

        return $this->editEntity($request, $clone, $parameters);
    }

    /**
     * Delete a category.
     *
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    #[Route(path: '/delete/{id}', name: 'category_delete', requirements: ['id' => Requirement::DIGITS])]
    public function delete(Request $request, Category $item, TaskRepository $taskRepository, ProductRepository $productRepository, CalculationCategoryRepository $categoryRepository, LoggerInterface $logger): Response
    {
        // external references?
        $tasks = $taskRepository->countCategoryReferences($item);
        $products = $productRepository->countCategoryReferences($item);
        $calculations = $categoryRepository->countCategoryReferences($item);
        if (0 !== $tasks || 0 !== $products || 0 !== $calculations) {
            $items = [];
            if (0 !== $calculations) {
                $items[] = $this->trans('counters.calculations', ['count' => $calculations]);
            }
            if (0 !== $products) {
                $items[] = $this->trans('counters.products', ['count' => $products]);
            }
            if (0 !== $tasks) {
                $items[] = $this->trans('counters.tasks', ['count' => $tasks]);
            }
            $message = $this->trans('category.delete.failure', ['%name%' => $item->getDisplay()]);

            // parameters
            $parameters = [
                'item' => $item,
                'id' => $item->getId(),
                'title' => 'category.delete.title',
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
     * Edit a category.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/edit/{id}', name: 'category_edit', requirements: ['id' => Requirement::DIGITS])]
    public function edit(Request $request, Category $item): Response
    {
        return $this->editEntity($request, $item);
    }

    /**
     * Export the categories to a Spreadsheet document.
     *
     * @throws NotFoundHttpException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/excel', name: 'category_excel')]
    public function excel(): SpreadsheetResponse
    {
        $entities = $this->getEntities('code');
        if (empty($entities)) {
            $message = $this->trans('category.list.empty');
            throw $this->createNotFoundException($message);
        }
        $doc = new CategoriesDocument($this, $entities);

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Export the categories to a PDF document.
     *
     * @throws NotFoundHttpException                      if no category is found
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/pdf', name: 'category_pdf')]
    public function pdf(): PdfResponse
    {
        $entities = $this->getEntities('code');
        if (empty($entities)) {
            $message = $this->trans('category.list.empty');
            throw $this->createNotFoundException($message);
        }
        $doc = new CategoriesReport($this, $entities);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Show properties of a category.
     */
    #[Route(path: '/show/{id}', name: 'category_show', requirements: ['id' => Requirement::DIGITS])]
    public function show(Category $item): Response
    {
        return $this->showEntity($item);
    }

    /**
     * Render the table view.
     *
     * @throws \ReflectionException
     */
    #[Route(path: '', name: 'category_table')]
    public function table(Request $request, CategoryTable $table, LoggerInterface $logger): Response
    {
        return $this->handleTableRequest($request, $table, 'category/category_table.html.twig', $logger);
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-param Category $item
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function deleteFromDatabase(AbstractEntity $item): void
    {
        $this->getApplication()->updateDeletedCategory($item);
        parent::deleteFromDatabase($item);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditFormType(): string
    {
        return CategoryType::class;
    }
}
