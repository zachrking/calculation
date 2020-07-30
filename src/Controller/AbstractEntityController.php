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

use App\DataTable\Model\AbstractEntityDataTable;
use App\Entity\AbstractEntity;
use App\Interfaces\EntityVoterInterface;
use App\Pdf\PdfDocument;
use App\Pdf\PdfResponse;
use App\Repository\AbstractRepository;
use App\Security\EntityVoter;
use App\Util\Utils;
use Doctrine\Common\Collections\Criteria;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abstract controller for entites management.
 *
 * @author Laurent Muller
 */
abstract class AbstractEntityController extends AbstractController
{
    /**
     * The entity class name.
     *
     * @var string
     */
    protected $className;

    /**
     * Constructor.
     *
     * @param string $className the entity class name
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * Raised after the given entity is deleted.
     *
     * @param AbstractEntity $item the deleted entity
     */
    protected function afterDeleteEntity(AbstractEntity $item): void
    {
    }

    /**
     * Throws an exception unless the given attribute is granted against
     * the current authentication token and this entity class name.
     *
     * @param string $attribute the attribute to check permission for
     *
     *  @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException if the access is denied
     */
    protected function checkPermission(string $attribute): void
    {
        $subject = EntityVoter::getEntityName($this->className);
        $this->denyAccessUnlessGranted($attribute, $subject);
    }

    /**
     * Delete an entity.
     *
     * @param request        $request    the request
     * @param AbstractEntity $item       the entity to delete
     * @param array          $parameters the delete parameters. The following keys may be added:
     *                                   <ul>
     *                                   <li><code>title</code> : the dialog title (optional).</li>
     *                                   <li><code>message</code> : the dialog message (optional).</li>
     *                                   <li><code>success</code> : the message to display on success (optional).</li>
     *                                   <li><code>failure</code> : the message to display on failure (optional).</li>
     *                                   </ul>
     */
    protected function deleteEntity(Request $request, AbstractEntity $item, array $parameters = []): Response
    {
        // check permission
        $this->checkPermission(EntityVoterInterface::ATTRIBUTE_DELETE);

        // save display
        $display = $item->getDisplay();

        //add item as parameter
        $parameters['item'] = $parameters;

        // create form and handle request
        $form = $this->getForm();
        if ($this->handleRequestForm($request, $form)) {
            try {
                // remove
                $em = $this->getManager();
                $em->remove($item);
                $em->flush();
                $this->afterDeleteEntity($item);

                // message
                $message = Utils::getArrayValue($parameters, 'success', 'common.delete_success');
                $this->warningTrans($message, ['%name%' => $display]);
            } catch (Exception $e) {
                // show error
                $parameters['exception'] = $e;
                $failure = Utils::getArrayValue($parameters, 'failure', 'common.delete_failure');
                $parameters['failure'] = $this->trans($failure, ['%name%' => $display]);

                return $this->render('@Twig/Exception/exception.html.twig', $parameters);
            }

            // redirect
            $id = 0;
            $route = Utils::getArrayValue($parameters, 'route', $this->getDefaultRoute());

            return $this->getUrlGenerator()->redirect($request, $id, $route);
        }

        // get parameters
        $title = Utils::getArrayValue($parameters, 'title', 'common.delete_title');
        $message = Utils::getArrayValue($parameters, 'message', 'common.delete_message');
        $message = $this->trans($message, ['%name%' => $display]);

        // update parameters
        $parameters['title'] = $title;
        $parameters['message'] = $message;
        $parameters['selection'] = $item->getId();
        $parameters['form'] = $form->createView();

        // show page
        return $this->render('cards/card_delete.html.twig', $parameters);
    }

    /**
     * Edit an entity.
     *
     * @param request        $request    the request
     * @param AbstractEntity $item       the entity to edit
     * @param array          $parameters the edit parameters. The following keys may be added:
     *                                   <ul>
     *                                   <li><code>success</code> : the message to display on success (optional).</li>
     *                                   <li><code>route</code> : the route to display on success (optional).</li>
     *                                   </ul>
     */
    protected function editEntity(Request $request, AbstractEntity $item, array $parameters = []): Response
    {
        // check permission
        $isNew = $item->isNew();
        $attribute = $isNew ? EntityVoterInterface::ATTRIBUTE_ADD : EntityVoterInterface::ATTRIBUTE_EDIT;
        $this->checkPermission($attribute);

        // form
        $type = $this->getEditFormType();
        $form = $this->createForm($type, $item);
        if ($this->handleRequestForm($request, $form)) {
            // update
            if ($this->updateEntity($item)) {
                // save
                $em = $this->getManager();
                if ($isNew) {
                    $em->persist($item);
                }
                $em->flush();
            }

            // message
            if ($isNew) {
                $message = Utils::getArrayValue($parameters, 'success', 'common.add_success');
            } else {
                $message = Utils::getArrayValue($parameters, 'success', 'common.edit_success');
            }
            $message = $this->trans($message, ['%name%' => $item->getDisplay()]);
            if ($title = Utils::getArrayValue($parameters, 'title')) {
                $title = $this->trans($title);
                $message = "{$title}|{$message}";
            }
            $this->succes($message);

            // redirect
            $id = $item->getId();
            $route = Utils::getArrayValue($parameters, 'route', $this->getDefaultRoute());

            return $this->getUrlGenerator()->redirect($request, $id, $route);
        }

        // remove unused parameters
        unset($parameters['success'], $parameters['route']);

        // update parameters
        $parameters['new'] = $isNew;
        $parameters['item'] = $item;
        $parameters['form'] = $form->createView();
        if (!$isNew) {
            $parameters['selection'] = (int) $item->getId();
        }

        // template
        $template = $this->getEditTemplate();

        // show form
        return $this->render($template, $parameters);
    }

    /**
     * Gets the Twig template (path) name used to display entities as card.
     */
    abstract protected function getCardTemplate(): string;

    /**
     * Gets the default route name used to display the list of entities.
     */
    abstract protected function getDefaultRoute(): string;

    /**
     * Gets sorted distinct and not null values of the given column.
     *
     * @param string $field  the column name to get values for
     * @param string $search a value to search within the column
     * @param int    $limit  the maximum number of results to retrieve or -1 for all
     */
    protected function getDistinctValues(string $field, ?string $search = null, int $limit = -1): array
    {
        return $this->getRepository()->getDistinctValues($field, $search, $limit);
    }

    /**
     * Gets the form type (class name) used to edit an entity.
     */
    abstract protected function getEditFormType(): string;

    /**
     * Gets the Twig template (path) name used to edit an entity.
     */
    abstract protected function getEditTemplate(): string;

    /**
     * Gets the entities to display.
     *
     * @param string $field the sorted field
     * @param string $mode  the sort mode ("ASC" or "DESC")
     *
     * @return AbstractEntity[] the entities
     */
    protected function getEntities(?string $field = null, string $mode = Criteria::ASC): array
    {
        $sortedFields = $field ? [$field => $mode] : [];

        return $this->getRepository()
            ->getSearchQuery($sortedFields)
            ->getResult();
    }

    /**
     * Gets the repository for the given manager.
     * This function use the class name given at the constructor.
     *
     * @return \App\Repository\AbstractRepository the repository
     */
    protected function getRepository(): AbstractRepository
    {
        return $this->getManager()->getRepository($this->className);
    }

    /**
     * Gets the Twig template (path) name used to show an entity.
     */
    abstract protected function getShowTemplate(): string;

    /**
     * Gets the Twig template (path) name used to display entities as table.
     */
    abstract protected function getTableTemplate(): string;

    /**
     * Gets the translated class name.
     *
     * @return string the translated class name
     */
    protected function getTranslatedClassName(): ?string
    {
        $className = Utils::getShortName($this->className);

        return $this->trans(\strtolower($className) . '.name');
    }

    /**
     * Render the entities as card.
     *
     * @param Request $request    the request
     * @param string  $sortField  the default sorted field
     * @param string  $sortMode   the default sorted direction
     * @param array   $sortFields the allowed sorted fields
     * @param array   $parameters the parameters to pass to the template
     *
     * @return Response the rendered template
     */
    protected function renderCard(Request $request, string $sortField, string $sortMode = Criteria::ASC, array $sortFields = [], array $parameters = []): Response
    {
        // check permission
        $this->checkPermission(EntityVoterInterface::ATTRIBUTE_LIST);

        // get session values
        $key = Utils::getShortName($this->className);
        $field = $this->getSessionString($key . '.sortField', $sortField);
        $mode = $this->getSessionString($key . '.sortMode', $sortMode);

        // get request values
        $field = $request->get('sortField', $field);
        $mode = $request->get('sortMode', $mode);
        $selection = (int) $request->get('selection', 0);
        $query = $request->get('query', '');

        // update session values
        if ($sortField === $field && $sortMode === $mode) {
            $this->removeSessionValue($key . '.sortField');
            $this->removeSessionValue($key . '.sortMode');
        } else {
            $this->setSessionValue($key . '.sortField', $field);
            $this->setSessionValue($key . '.sortMode', $mode);
        }

        // get items
        $items = $this->getEntities($field, $mode);

        // default action
        $edit = $this->getApplication()->isEditAction();

        // parameters
        $parameters = \array_merge([
            'items' => $items,
            'query' => $query,
            'selection' => $selection,
            'sortField' => $field,
            'sortMode' => $mode,
            'sortFields' => $sortFields,
            'edit' => $edit,
        ], $parameters);

        return $this->render($this->getCardTemplate(), $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function renderDocument(PdfDocument $doc, bool $inline = true, string $name = ''): PdfResponse
    {
        $this->checkPermission(EntityVoterInterface::ATTRIBUTE_PDF);

        return parent::renderDocument($doc, $inline, $name);
    }

    /**
     * Render the entities as data table.
     *
     * @param Request                 $request    the request to get parameters
     * @param AbstractEntityDataTable $table      the data table
     * @param array                   $attributes additional data table attributes
     * @param array                   $parameters parameters to pass to the view
     *
     * @return Response a JSON response if is a callback, the data table view otherwise
     */
    protected function renderTable(Request $request, AbstractEntityDataTable $table, array $attributes = [], array $parameters = []): Response
    {
        $results = $table->handleRequest($request);
        if ($table->isCallback()) {
            return $this->json($results);
        }

        // check permission
        $this->checkPermission(EntityVoterInterface::ATTRIBUTE_LIST);

        // update attributes
        $attributes['edit-action'] = \json_encode($this->getApplication()->isEditAction());

        // parameters
        $parameters += [
            'results' => $results,
            'attributes' => $attributes,
            'columns' => $table->getColumns(),
        ];

        return $this->render($this->getTableTemplate(), $parameters);
    }

    /**
     * Show properties of an entity.
     *
     * @param AbstractEntity $item       the entity to show
     * @param array          $parameters the additional parameters to pass to the template
     *
     * @throws \Symfony\Component\Finder\Exception\AccessDeniedException if the access is denied
     */
    protected function showEntity(AbstractEntity $item, array $parameters = []): Response
    {
        // check permission
        $this->checkPermission(EntityVoterInterface::ATTRIBUTE_SHOW);

        // add item parameter
        $parameters['item'] = $item;

        // render
        return $this->render($this->getShowTemplate(), $parameters);
    }

    /**
     * This function is called before an entity is saved to the database.
     *
     * Derived class can compute values and update entity.
     *
     * @param AbstractEntity $item the entity to be saved
     *
     * @return bool true if updated successfully; false to not save entity to the database
     */
    protected function updateEntity(AbstractEntity $item): bool
    {
        return true;
    }
}
