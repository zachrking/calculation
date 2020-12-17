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

use App\DataTable\CustomerDataTable;
use App\Entity\AbstractEntity;
use App\Entity\Customer;
use App\Excel\ExcelResponse;
use App\Form\Customer\CustomerType;
use App\Pdf\PdfResponse;
use App\Report\CustomersReport;
use App\Repository\CustomerRepository;
use App\Spreadsheet\CustomerDocument;
use Doctrine\Common\Collections\Criteria;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The controller for customer entities.
 *
 * @see \App\Entity\Customer
 *
 * @Route("/customer")
 * @IsGranted("ROLE_USER")
 */
class CustomerController extends AbstractEntityController
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(Customer::class);
    }

    /**
     * Add a customer.
     *
     * @Route("/add", name="customer_add", methods={"GET", "POST"})
     */
    public function add(Request $request): Response
    {
        return $this->editEntity($request, new Customer());
    }

    /**
     * List the customers.
     *
     * @Route("", name="customer_list", methods={"GET"})
     */
    public function card(Request $request): Response
    {
        $sortedFields = [
            ['name' => CustomerRepository::NAME_COMPANY_FIELD, 'label' => 'customer.fields.nameAndCompany'],
            ['name' => CustomerRepository::ZIP_CITY_FIELD, 'label' => 'customer.fields.zipCity'],
        ];

        return $this->renderCard($request, CustomerRepository::NAME_COMPANY_FIELD, Criteria::ASC, $sortedFields);
    }

    /**
     * Delete a customer.
     *
     * @Route("/delete/{id}", name="customer_delete", requirements={"id": "\d+" })
     */
    public function delete(Request $request, Customer $item): Response
    {
        $parameters = [
            'title' => 'customer.delete.title',
            'message' => 'customer.delete.message',
            'success' => 'customer.delete.success',
            'failure' => 'customer.delete.failure',
        ];

        return $this->deleteEntity($request, $item, $parameters);
    }

    /**
     * Edit a customer.
     *
     * @Route("/edit/{id}", name="customer_edit", requirements={"id": "\d+" }, methods={"GET", "POST"})
     */
    public function edit(Request $request, Customer $item): Response
    {
        return $this->editEntity($request, $item);
    }

    /**
     * Export the customers to an Excel document.
     *
     * @Route("/excel", name="customer_excel")
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no customer is found
     */
    public function excel(CustomerRepository $repository): ExcelResponse
    {
        /** @var Customer[] $entities */
        $entities = $repository->findAllByNameAndCompany();
        if (empty($entities)) {
            $message = $this->trans('customer.list.empty');
            throw $this->createNotFoundException($message);
        }

        $doc = new CustomerDocument($this, $entities);

        return $this->renderExcelDocument($doc);
    }

    /**
     * Export the customers to a PDF document.
     *
     * @Route("/pdf", name="customer_pdf")
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no customer is found
     */
    public function pdf(Request $request, CustomerRepository $repository): PdfResponse
    {
        /** @var Customer[] $entities */
        $entities = $repository->findAllByNameAndCompany();
        if (empty($entities)) {
            $message = $this->trans('customer.list.empty');
            throw $this->createNotFoundException($message);
        }

        $grouped = (bool) $request->get('grouped', true);
        $report = new CustomersReport($this, $entities, $grouped);

        return $this->renderPdfDocument($report);
    }

    /**
     * Show properties of a customer.
     *
     * @Route("/show/{id}", name="customer_show", requirements={"id": "\d+" }, methods={"GET"})
     */
    public function show(Customer $item): Response
    {
        return $this->showEntity($item);
    }

    /**
     * Render the table view.
     *
     * @Route("/table", name="customer_table", methods={"GET", "POST"})
     */
    public function table(Request $request, CustomerDataTable $table): Response
    {
        return $this->renderTable($request, $table);
    }

    /**
     * {@inheritdoc}
     */
    protected function editEntity(Request $request, AbstractEntity $item, array $parameters = []): Response
    {
        // update parameters
        $parameters['success'] = $item->isNew() ? 'customer.add.success' : 'customer.edit.success';

        return parent::editEntity($request, $item, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditFormType(): string
    {
        return CustomerType::class;
    }
}
