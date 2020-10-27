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

use App\Entity\Calculation;
use App\Entity\Role;
use App\Form\Admin\ParametersType;
use App\Form\FormHelper;
use App\Form\User\RoleRightsType;
use App\Interfaces\ApplicationServiceInterface;
use App\Interfaces\RoleInterface;
use App\Listener\CalculationListener;
use App\Listener\TimestampableListener;
use App\Repository\CalculationRepository;
use App\Security\EntityVoter;
use App\Service\CalculationService;
use App\Service\SwissPostService;
use App\Util\SymfonyUtils;
use Doctrine\Common\EventManager;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;

/**
 * Controller for administation tasks.
 *
 * @Route("/admin")
 */
class AdminController extends AbstractController
{
    /**
     * Edit rights for the administrator role ('ROLE_ADMIN').
     *
     * @Route("/rights/admin", name="admin_rights_admin")
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function adminRights(Request $request): Response
    {
        // get values
        $roleName = RoleInterface::ROLE_ADMIN;
        $rights = $this->getApplication()->getAdminRights();
        $default = EntityVoter::getRoleAdmin();
        $property = ApplicationServiceInterface::ADMIN_RIGHTS;

        return $this->editRights($request, $roleName, $rights, $default, $property);
    }

    /**
     * Clear the application cache.
     *
     * @Route("/clear", name="admin_clear")
     * @IsGranted("ROLE_ADMIN")
     */
    public function clearCache(Request $request, KernelInterface $kernel, LoggerInterface $logger): Response
    {
        // handle request
        $form = $this->getForm();
        if ($this->handleRequestForm($request, $form)) {
            // first clear application service cache
            $this->getApplication()->clearCache();

            try {
                $options = [
                    'command' => 'cache:clear',
                    '--env' => $kernel->getEnvironment(),
                    '--no-warmup' => true,
                ];

                $input = new ArrayInput($options);
                $output = new BufferedOutput();
                $application = new Application($kernel);
                $application->setCatchExceptions(false);
                $application->setAutoExit(false);
                $result = $application->run($input, $output);

                $context = [
                    'result' => $result,
                    'options' => $options,
                ];
                $message = $this->succesTrans('clear_cache.success');
                $logger->info($message, $context);

                return  $this->redirectToHomePage();
            } catch (\Exception $e) {
                // show error
                $parameters = [
                    'exception' => $e,
                    'failure' => $this->trans('clear_cache.failure'),
                ];

                return $this->render('@Twig/Exception/exception.html.twig', $parameters);
            }
        }

        // display
        return $this->render('admin/clear_cache.html.twig', [
            'size' => SymfonyUtils::getCacheSize($kernel),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Import streets and cities for Switzerland.
     *
     * @Route("/import", name="admin_import")
     * @IsGranted("ROLE_ADMIN")
     */
    public function import(Request $request, SwissPostService $service): Response
    {
        // clear
        if ($this->getApplication()->isDebug()) {
            $this->getApplication()->clearCache();
        }

        // create form
        $helper = $this->createFormHelper();

        // constraints
        $constraints = new File([
            'mimeTypes' => ['application/zip', 'application/x-zip-compressed'],
            'mimeTypesMessage' => $this->trans('import.error.mime_type'),
        ]);

        // fields
        $helper->field('file')
            ->label('import.file')
            ->updateOption('constraints', $constraints)
            ->updateAttribute('accept', 'application/x-zip-compressed')
            ->addFileType();

        // handle request
        $form = $helper->createForm();
        if ($this->handleRequestForm($request, $form)) {
            // import
            $file = $form->getData()['file'];
            $data = $service->setSourceFile($file)->import();

            // display result
            return $this->render('admin/import_result.html.twig', [
                'data' => $data,
            ]);
        }

        // display
        return $this->render('admin/import_file.html.twig', [
            'last_import' => $this->getApplication()->getLastImport(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Display the application parameters.
     *
     * @Route("/parameters", name="admin_parameters")
     * @IsGranted("ROLE_ADMIN")
     */
    public function parameters(Request $request): Response
    {
        // properties
        $service = $this->getApplication();
        $data = $service->getProperties();

        // password options
        foreach (ParametersType::PASSWORD_OPTIONS as $option) {
            $data[$option] = $service->isPropertyBoolean($option);
        }

        // remove unused properties
        unset($data[ApplicationServiceInterface::LAST_UPDATE], $data[ApplicationServiceInterface::LAST_IMPORT]);

        // form
        $form = $this->createForm(ParametersType::class, $data);
        if ($this->handleRequestForm($request, $form)) {
            //save properties
            $data = $form->getData();
            $service->setProperties($data);
            $this->succesTrans('parameters.success');

            return  $this->redirectToHomePage();
        }

        // display
        return $this->render('admin/parameters.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Update calculation totals.
     *
     * @Route("/update", name="admin_update", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function update(Request $request, CalculationRepository $repository, CalculationService $service, LoggerInterface $logger): Response
    {
        // create form helper
        $helper = $this->createUpdateHelper();

        // handle request
        $form = $helper->createForm();
        if ($this->handleRequestForm($request, $form)) {
            $data = $form->getData();
            $includeClosed = (bool) $data['closed'];
            $includeSorted = (bool) $data['sorted'];
            $isSimulated = (bool) $data['simulated'];

            $updated = 0;
            $skipped = 0;
            $sorted = 0;
            $unmodifiable = 0;
            $suspended = $this->disableListeners();

            try {
                /** @var Calculation[] $calculations */
                $calculations = $repository->findAll();
                foreach ($calculations as $calculation) {
                    if ($includeClosed || $calculation->isEditable()) {
                        $changed = false;
                        if ($includeSorted && $calculation->sort()) {
                            $changed = true;
                            ++$sorted;
                        }
                        if ($service->updateTotal($calculation)) {
                            ++$updated;
                        } elseif ($changed) {
                            ++$updated;
                        } else {
                            ++$skipped;
                        }
                    } else {
                        ++$unmodifiable;
                    }
                }

                if ($updated > 0 && !$isSimulated) {
                    $this->getManager()->flush();
                }
            } finally {
                $this->enableListeners($suspended);
            }

            $total = \count($calculations);

            if (!$isSimulated) {
                // update last update
                $this->getApplication()->setProperties([ApplicationServiceInterface::LAST_UPDATE => new \DateTime()]);

                // log results
                $context = [
                    $this->trans('calculation.result.updated') => $updated,
                    $this->trans('calculation.result.sorted') => $sorted,
                    $this->trans('calculation.result.skipped') => $skipped,
                    $this->trans('calculation.result.unmodifiable') => $unmodifiable,
                    $this->trans('calculation.result.total') => $total,
                ];
                $message = $this->trans('calculation.update.title');
                $logger->info($message, $context);
            }

            // display results
            $data = [
                'updated' => $updated,
                'sorted' => $sorted,
                'skipped' => $skipped,
                'unmodifiable' => $unmodifiable,
                'simulated' => $isSimulated,
                'total' => $total,
            ];

            // save values to session
            $this->setSessionValue('closed', $includeClosed);
            $this->setSessionValue('sorted', $includeSorted);
            $this->setSessionValue('simulated', $isSimulated);

            return $this->render('calculation/calculation_result.html.twig', $data);
        }

        // display
        return $this->render('calculation/calculation_update.html.twig', [
            'last_update' => $this->getApplication()->getLastUpdate(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit rights for the user role ('ROLE_USER').
     *
     * @Route("/rights/user", name="admin_rights_user")
     * @IsGranted("ROLE_ADMIN")
     */
    public function userRights(Request $request): Response
    {
        // get values
        $roleName = RoleInterface::ROLE_USER;
        $rights = $this->getApplication()->getUserRights();
        $default = EntityVoter::getRoleUser();
        $property = ApplicationServiceInterface::USER_RIGHTS;

        return $this->editRights($request, $roleName, $rights, $default, $property);
    }

    /**
     * Creates the form helper and add fields for the update calculations.
     */
    private function createUpdateHelper(): FormHelper
    {
        // create form
        $data = [
            'closed' => $this->isSessionBool('closed', false),
            'sorted' => $this->isSessionBool('sorted', true),
            'simulated' => $this->isSessionBool('simulated', false),
        ];
        $helper = $this->createFormHelper('calculation.update.', $data);

        // fields
        $helper->field('closed')
            ->help('calculation.update.closed_help')
            ->updateHelpAttribute('class', 'ml-4 mb-2')
            ->notRequired()
            ->addCheckboxType();

        $helper->field('sorted')
            ->help('calculation.update.sorted_help')
            ->updateHelpAttribute('class', 'ml-4 mb-2')
            ->notRequired()
            ->addCheckboxType();

        $helper->field('simulated')
            ->help('calculation.update.simulated_help')
            ->updateHelpAttribute('class', 'ml-4 mb-2')
            ->updateRowAttribute('class', 'mb-0')
            ->notRequired()
            ->addCheckboxType();

        return $helper;
    }

    /**
     * Disabled doctrine event listeners.
     *
     * @return array an array containing the event names and listerners
     */
    private function disableListeners(): array
    {
        $suspended = [];
        $manager = $this->getEventManager();
        $allListeners = $manager->getListeners();
        foreach ($allListeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof TimestampableListener
                    || $listener instanceof CalculationListener) {
                    $suspended[$event][] = $listener;
                    $manager->removeEventListener($event, $listener);
                }
            }
        }

        return $suspended;
    }

    /**
     * Edit rights.
     *
     * @param Request $request  the request
     * @param string  $roleName the role name
     * @param int[]   $rights   the role rights
     * @param Role    $default  the role with default rights
     * @param string  $property the property name to update
     */
    private function editRights(Request $request, string $roleName, ?array $rights, Role $default, string $property): Response
    {
        // name
        $pos = \strpos($roleName, '_');
        $name = 'user.roles.' . \strtolower(\substr($roleName, $pos + 1));

        // role
        $role = new Role($roleName);
        $role->setName($this->trans($name))
            ->setRights($rights);

        // form
        $form = $this->createForm(RoleRightsType::class, $role);
        if ($this->handleRequestForm($request, $form)) {
            $this->getApplication()->setProperties([
                $property => $role->getRights(),
            ]);
            $this->succesTrans('admin.rights.success', ['%name%' => $role->getName()]);

            return  $this->redirectToHomePage();
        }

        // show form
        return $this->render('admin/role_rights.html.twig', [
            'form' => $form->createView(),
            'default' => $default,
        ]);
    }

    /**
     * Enabled doctrine event listeners.
     *
     * @param array $suspended the event names and listeners to activate
     */
    private function enableListeners(array $suspended): void
    {
        $manager = $this->getEventManager();
        foreach ($suspended as $event => $listeners) {
            foreach ($listeners as $listener) {
                $manager->addEventListener($event, $listener);
            }
        }
    }

    /**
     * Gets the doctrine event manager.
     *
     * @return EventManager the event manager
     */
    private function getEventManager(): EventManager
    {
        return $this->getManager()->getEventManager();
    }
}
