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

use App\BootstrapTable\ProductTable;
use App\Entity\AbstractEntity;
use App\Entity\Product;
use App\Form\Product\ProductType;
use App\Report\ProductsReport;
use App\Repository\ProductRepository;
use App\Response\PdfResponse;
use App\Response\SpreadsheetResponse;
use App\Spreadsheet\ProductsDocument;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use SlopeIt\BreadcrumbBundle\Annotation\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The controller for product entities.
 *
 * @author Laurent Muller
 *
 * @Route("/product")
 * @IsGranted("ROLE_USER")
 * @Breadcrumb({
 *     {"label" = "index.title", "route" = "homepage"}
 * })
 * @template-extends AbstractEntityController<Product>
 */
class ProductController extends AbstractEntityController
{
    /**
     * Constructor.
     */
    public function __construct(ProductRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Add a product.
     *
     * @Route("/add", name="product_add")
     * @Breadcrumb({
     *     {"label" = "product.list.title", "route" = "product_table"},
     *     {"label" = "product.add.title"}
     * })
     */
    public function add(Request $request): Response
    {
        $item = new Product();
        if (null !== ($category = $this->getApplication()->getDefaultCategory())) {
            $item->setCategory($category);
        }

        return $this->editEntity($request, $item);
    }

    /**
     * Clone (copy) a product.
     *
     * @Route("/clone/{id}", name="product_clone", requirements={"id" = "\d+"})
     * @Breadcrumb({
     *     {"label" = "product.list.title", "route" = "product_table"},
     *     {"label" = "breadcrumb.clone"}
     * })
     */
    public function clone(Request $request, Product $item): Response
    {
        $description = $this->trans('common.clone_description', ['%description%' => $item->getDescription()]);
        $clone = $item->clone($description);
        $parameters = [
            'params' => ['id' => $item->getId()],
        ];

        return $this->editEntity($request, $clone, $parameters);
    }

    /**
     * Delete a product.
     *
     * @Route("/delete/{id}", name="product_delete", requirements={"id" = "\d+"})
     * @Breadcrumb({
     *     {"label" = "product.list.title", "route" = "product_table"},
     *     {"label" = "breadcrumb.delete"},
     *     {"label" = "$item.display"}
     * })
     */
    public function delete(Request $request, Product $item, LoggerInterface $logger): Response
    {
        $parameters = [
            'title' => 'product.delete.title',
            'message' => 'product.delete.message',
            'success' => 'product.delete.success',
            'failure' => 'product.delete.failure',
        ];

        return $this->deleteEntity($request, $item, $logger, $parameters);
    }

    /**
     * Edit a product.
     *
     * @Route("/edit/{id}", name="product_edit", requirements={"id" = "\d+"})
     * @Breadcrumb({
     *     {"label" = "product.list.title", "route" = "product_table"},
     *     {"label" = "breadcrumb.edit"},
     *     {"label" = "$item.display"}
     * })
     */
    public function edit(Request $request, Product $item): Response
    {
        return $this->editEntity($request, $item);
    }

    /**
     * Export the products to a Spreadsheet document.
     *
     * @Route("/excel", name="product_excel")
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no product is found
     */
    public function excel(ProductRepository $repository): SpreadsheetResponse
    {
        $entities = $repository->findAllByGroup();
        if (empty($entities)) {
            $message = $this->trans('product.list.empty');
            throw $this->createNotFoundException($message);
        }

        $doc = new ProductsDocument($this, $entities);

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Export the products to a PDF document.
     *
     * @Route("/pdf", name="product_pdf")
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no product is found
     */
    public function pdf(ProductRepository $repository): PdfResponse
    {
        $entities = $repository->findAllByGroup();
        if (empty($entities)) {
            $message = $this->trans('product.list.empty');
            throw $this->createNotFoundException($message);
        }

        $doc = new ProductsReport($this, $entities);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Show properties of a product.
     *
     * @Route("/show/{id}", name="product_show", requirements={"id" = "\d+"})
     * @Breadcrumb({
     *     {"label" = "product.list.title", "route" = "product_table"},
     *     {"label" = "breadcrumb.property"},
     *     {"label" = "$item.display"}
     * })
     */
    public function show(Product $item): Response
    {
        return $this->showEntity($item);
    }

    /**
     * Render the table view.
     *
     * @Route("", name="product_table")
     * @Breadcrumb({
     *     {"label" = "product.list.title" }
     * })
     */
    public function table(Request $request, ProductTable $table): Response
    {
        return $this->handleTableRequest($request, $table, 'product/product_table.html.twig');
    }

    /**
     * {@inheritdoc}
     */
    protected function editEntity(Request $request, AbstractEntity $item, array $parameters = []): Response
    {
        // update parameters
        $parameters['success'] = $item->isNew() ? 'product.add.success' : 'product.edit.success';

        return parent::editEntity($request, $item, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditFormType(): string
    {
        return ProductType::class;
    }
}
