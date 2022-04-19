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
use App\Entity\Task;
use App\Form\Task\TaskServiceType;
use App\Form\Task\TaskType;
use App\Report\TasksReport;
use App\Repository\TaskRepository;
use App\Response\PdfResponse;
use App\Response\SpreadsheetResponse;
use App\Service\TaskService;
use App\Spreadsheet\TasksDocument;
use App\Table\TaskTable;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The controller for task entities.
 *
 * @author Laurent Muller
 *
 * @Route("/task")
 * @IsGranted("ROLE_USER")
 * @template-extends AbstractEntityController<Task>
 */
class TaskController extends AbstractEntityController
{
    /**
     * Constructor.
     */
    public function __construct(TaskRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Add a task.
     *
     * @Route("/add", name="task_add")
     */
    public function add(Request $request): Response
    {
        $item = new Task();
        if (null !== ($category = $this->getApplication()->getDefaultCategory())) {
            $item->setCategory($category);
        }

        return $this->editEntity($request, $item);
    }

    /**
     * Edit a copy (cloned) task.
     *
     * @Route("/clone/{id}", name="task_clone", requirements={"id" = "\d+"})
     */
    public function clone(Request $request, Task $item): Response
    {
        $name = $this->trans('common.clone_description', ['%description%' => $item->getName()]);
        $clone = $item->clone($name);
        $parameters = [
            'params' => ['id' => $item->getId()],
        ];

        return $this->editEntity($request, $clone, $parameters);
    }

    /**
     * Display the form to compute a task.
     *
     * @Route("/compute/{id}", name="task_compute", requirements={"id" = "\d+"})
     */
    public function compute(Request $request, TaskService $service, TaskRepository $repository, Task $task = null): Response
    {
        // get tasks
        /** @var Task[] $tasks */
        $tasks = $repository->getSortedBuilder(false)
            ->getQuery()
            ->getResult();

        // set task
        if (null === $task || $task->isEmpty()) {
            $task = $tasks[0];
        }
        $service->setTask($task, true)
            ->compute();

        $form = $this->createForm(TaskServiceType::class, $service);
        if ($this->handleRequestForm($request, $form)) {
            $service->compute($request);
        }

        $parameters = [
            'form' => $form,
            'tasks' => $tasks,
        ];
        $this->updateQueryParameters($request, $parameters, (int) $task->getId());

        return $this->renderForm('task/task_compute.html.twig', $parameters);
    }

    /**
     * Delete a task.
     *
     * @Route("/delete/{id}", name="task_delete", requirements={"id" = "\d+"})
     */
    public function delete(Request $request, Task $item, LoggerInterface $logger): Response
    {
        $parameters = [
            'title' => 'task.delete.title',
            'message' => 'task.delete.message',
            'success' => 'task.delete.success',
            'failure' => 'task.delete.failure',
        ];

        return $this->deleteEntity($request, $item, $logger, $parameters);
    }

    /**
     * Edit a task.
     *
     * @Route("/edit/{id}", name="task_edit", requirements={"id" = "\d+"})
     */
    public function edit(Request $request, Task $item): Response
    {
        return $this->editEntity($request, $item);
    }

    /**
     * Export the tasks to a Spreadsheet document.
     *
     * @Route("/excel", name="task_excel")
     *
     * @throws NotFoundHttpException if no category is found
     */
    public function excel(): SpreadsheetResponse
    {
        $entities = $this->getEntities('name');
        if (empty($entities)) {
            $message = $this->trans('task.list.empty');
            throw new NotFoundHttpException($message);
        }

        $doc = new TasksDocument($this, $entities);

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Export the tasks to a PDF document.
     *
     * @Route("/pdf", name="task_pdf")
     *
     * @throws NotFoundHttpException if no category is found
     */
    public function pdf(): PdfResponse
    {
        $entities = $this->getEntities('name');
        if (empty($entities)) {
            $message = $this->trans('task.list.empty');
            throw new NotFoundHttpException($message);
        }

        $doc = new TasksReport($this, $entities);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Show properties of a task.
     *
     * @Route("/show/{id}", name="task_show", requirements={"id" = "\d+"})
     */
    public function show(Task $item): Response
    {
        return $this->showEntity($item);
    }

    /**
     * Render the table view.
     *
     * @Route("", name="task_table")
     */
    public function table(Request $request, TaskTable $table): Response
    {
        return $this->handleTableRequest($request, $table, 'task/task_table.html.twig');
    }

    /**
     * {@inheritdoc}
     *
     * @param Task $item
     */
    protected function editEntity(Request $request, AbstractEntity $item, array $parameters = []): Response
    {
        $parameters['item_index'] = $item->count();
        $parameters['margin_index'] = $item->countMargins();
        $parameters['success'] = $item->isNew() ? 'task.add.success' : 'task.edit.success';

        return parent::editEntity($request, $item, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditFormType(): string
    {
        return TaskType::class;
    }
}
