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

use App\DataTables\LogDataTable;
use App\Entity\Log;
use App\Pdf\PdfResponse;
use App\Report\LogReport;
use App\Repository\LogRepository;
use App\Utils\LogUtils;
use App\Utils\Utils;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The log controler.
 *
 * @author Laurent Muller
 *
 * @Route("/log")
 * @IsGranted("ROLE_ADMIN")
 */
class LogController extends BaseController
{
    /**
     * Logs a Content Security Policy report.
     *
     * @IsGranted("ROLE_USER")
     * @Route("/csp", name="log_csp")
     */
    public function cspViolation(Request $request, LoggerInterface $logger): Response
    {
        $data = \file_get_contents('php://input');
        if ($data = \json_decode($data, true)) {
            $context = \array_filter($data['csp-report'], function ($value) {
                return Utils::isString($value);
            });
            $title = 'CSP Violation';
            if (isset($context['document-uri'])) {
                $title .= ': ' . $context['document-uri'];
            } elseif (isset($context['source-file'])) {
                $title .= ': ' . $context['source-file'];
            }
            $logger->error($title, $context);
        }

        // no content
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete the content of the log file (if any).
     *
     * @Route("/delete", name="log_delete")
     */
    public function delete(Request $request, LoggerInterface $logger): Response
    {
        // check if file exists
        $file = $this->getLogFile();
        if (!\file_exists($file) || 0 === \filesize($file)) {
            $this->infoTrans('logs.show.empty');

            return  $this->redirectToHomePage();
        }

        // handle request
        $form = $this->createFormBuilder()->getForm();
        if ($this->handleRequestForm($request, $form)) {
            if (\file_exists($file)) {
                try {
                    // empty file
                    \file_put_contents($file, '');
                } catch (\Exception $e) {
                    $message = $this->trans('logs.delete.error');
                    $logger->error($message, ['file' => $file]);

                    return $this->render('@Twig/Exception/exception.html.twig', [
                        'message' => $message,
                        'exception' => $e,
                    ]);
                }
            }

            // OK
            $this->succesTrans('logs.delete.success');

            return  $this->redirectToHomePage();
        }

        // display
        return $this->render('log/log_delete.html.twig', [
            'form' => $form->createView(),
            'file' => $file,
        ]);
    }

    /**
     * Export to PDF the content of the log file.
     *
     * @Route("/pdf", name="log_pdf")
     */
    public function pdf(Request $request): PdfResponse
    {
        // filters
        $channelFilters = [];
        if ($filter = $request->get('channels')) {
            $channelFilters = \explode('|', $filter);
        }
        $levelFilters = [];
        if ($filter = $request->get('levels')) {
            $levelFilters = \explode('|', $filter);
        }
        $maxLines = (int) $request->get('limit', 50);

        // read entries
        $entries = $this->getLogEntries($maxLines, $channelFilters, $levelFilters);

        // render report
        $report = new LogReport($this);
        $report->setValues($entries);

        return $this->renderDocument($report);
    }

    /**
     * Display the content of the log file.
     *
     * @Route("/show", name="log_show")
     */
    public function show(Request $request): Response
    {
        $maxLines = (int) $request->get('limit', 50);
        $entries = $this->getLogEntries($maxLines);

        return $this->render('log/log_list.html.twig', $entries);
    }

    /**
     * Show properties of a log entry.
     *
     * @Route("/show/{id}", name="log_show_entry", requirements={"id": "\d+" }, methods={"GET"})
     */
    public function showEntity(Request $request, Log $item): Response
    {
        // parameters
        $parameters = [
            'item' => $item,
        ];

        // render
        return $this->render('log/log_show.html.twig', $parameters);
    }

    /**
     * Display the content of the log file as table.
     *
     * @Route("/table", name="log_table", methods={"GET", "POST"})
     */
    public function table(Request $request, LogDataTable $table, LogRepository $repository): Response
    {
        $results = $table->handleRequest($request);
        if ($table->isCallback()) {
            return $this->json($results);
        }
        // attributes
        $attributes = [
            'edit-action' => \json_encode(false),
        ];

        // parameters
        $parameters = [
            'results' => $results,
            'attributes' => $attributes,
            'columns' => $table->getColumns(),
            'levels' => $repository->getLevels(),
            'channels' => $repository->getChannels(),
        ];

        return $this->render('log/log_table.html.twig', $parameters);
    }

    /**
     * Gets the log entries.
     *
     * @param int   $maxLines       the number of lines to returns
     * @param array $channelFilters the channels to skip
     * @param array $levelFilters   the levels to skip
     */
    private function getLogEntries(int $maxLines = 50, array $channelFilters = [], array $levelFilters = []): array
    {
        // get file name
        $file = $this->getLogFile();

        // load file
        $values = false;
        if (\file_exists($file)) {
            $values = LogUtils::readLog($file, $maxLines, $channelFilters, $levelFilters);
        }
        if (false === $values) {
            $values = [
                'lines' => false,
                'limit' => 0,
            ];
        }
        $values['file'] = $this->getLogFile();

        return $values;
    }

    /**
     * Gets the log file.
     */
    private function getLogFile(): string
    {
        $dir = $this->getParameter('kernel.logs_dir');
        $env = $this->getParameter('kernel.environment');

        return $dir . \DIRECTORY_SEPARATOR . $env . '.log';
    }
}
