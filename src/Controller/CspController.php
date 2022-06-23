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

use App\Mime\CspViolationEmail;
use App\Util\Utils;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Controller to handle CSP violation.
 */
#[AsController]
#[IsGranted('ROLE_USER')]
class CspController extends AbstractController
{
    #[Route(path: '/csp', name: 'log_csp')]
    public function invoke(LoggerInterface $logger, MailerInterface $mailer): Response
    {
        if (false !== $context = $this->getContext()) {
            try {
                $title = $this->trans('notification.csp_title');
                $this->logContext($title, $context, $logger);
                $this->sendNotification($title, $context, $mailer);
            } catch (TransportExceptionInterface $e) {
                $context = Utils::getExceptionContext($e);
                $logger->error($e->getMessage(), $context);
            }
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function explodeOriginalPolicy(string $value): array
    {
        $result = [];
        $policies = \array_filter(\explode(';', $value));
        foreach ($policies as $policy) {
            $entries = \array_filter(\explode(' ', $policy));
            if (\count($entries) > 1) {
                $key = \reset($entries);
                $values = \array_map(static fn (string $entry): string => \trim($entry, "'"), \array_slice($entries, 1));
                \sort($values);
                $result[$key] = \implode("\n", $values);
            }
        }

        return $result;
    }

    private function getActionUrl(): string
    {
        return $this->generateUrl(self::HOME_PAGE, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function getContext(): array|false
    {
        $content = (string) \file_get_contents('php://input');
        /** @psalm-var bool|array{csp-report: string[]} $data */
        $data = \json_decode($content, true);
        if (\is_array($data)) {
            $context = \array_filter($data['csp-report']);
            if (isset($context['original-policy'])) {
                $context['original-policy'] = $this->explodeOriginalPolicy($context['original-policy']);
            }

            return $context;
        }

        return false;
    }

    private function getFooterText(): string
    {
        return $this->trans('notification.footer', ['%name%' => $this->getApplicationName()]);
    }

    private function logContext(string $title, array $context, LoggerInterface $logger): void
    {
        $logger->error($title, $context);
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendNotification(string $title, array $context, MailerInterface $mailer): void
    {
        $notification = new CspViolationEmail($this->getTranslator());
        $notification->importance(NotificationEmail::IMPORTANCE_HIGH)
            ->subject($title)
            ->to($this->getAddressFrom())
            ->from($this->getAddressFrom())
            ->setFooterText($this->getFooterText())
            ->action($this->trans('index.title'), $this->getActionUrl())
            ->context([
                'context' => $context,
            ]);
        $mailer->send($notification);
    }
}