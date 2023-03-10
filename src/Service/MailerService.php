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

namespace App\Service;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Enums\Importance;
use App\Mime\NotificationEmail;
use App\Model\Comment;
use App\Traits\TranslatorAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;
use Twig\Extra\Markdown\MarkdownInterface;

/**
 * Service to send notifications.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class MailerService implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;
    use TranslatorAwareTrait;

    private readonly string $appName;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly UrlGeneratorInterface $generator,
        private readonly MarkdownInterface $markdown,
        private readonly MailerInterface $mailer,
        #[Autowire('%app_name%')]
        string $appName,
        #[Autowire('%app_version%')]
        string $appVersion,
    ) {
        $this->appName = \sprintf('%s v%s', $appName, $appVersion);
    }

    /**
     * Send a comment.
     *
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface if an exception occurs while sending the notification
     */
    public function sendComment(Comment $comment): void
    {
        $notification = $this->createNotification();
        $notification->subject((string) $comment->getSubject())
            ->importance($comment->getImportance())
            ->markdown($this->convert((string) $comment->getMessage()))
            ->action($this->trans('index.title'), $this->getHomeUrl());

        if (null !== $address = $comment->getFromAddress()) {
            $notification->from($address);
        }
        if (null !== $address = $comment->getToAddress()) {
            $notification->to($address);
        }
        foreach ($comment->getAttachments() as $attachment) {
            $notification->attachFromUploadedFile($attachment);
        }
        $this->mailer->send($notification);
    }

    /**
     * Send a notification.
     *
     * @param UploadedFile[] $attachments
     *
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface if an exception occurs while sending the notification
     */
    public function sendNotification(string $fromEmail, User $toUser, string $message, Importance $importance = Importance::LOW, array $attachments = []): void
    {
        $notification = $this->createNotification()
            ->from($fromEmail)
            ->to($toUser->getAddress())
            ->subject($this->trans('user.comment.title'))
            ->markdown($this->convert($message))
            ->importance($importance);
        foreach ($attachments as $attachment) {
            $notification->attachFromUploadedFile($attachment);
        }
        $this->mailer->send($notification);
    }

    private function convert(string $message): string
    {
        return $this->markdown->convert($message);
    }

    private function createNotification(): NotificationEmail
    {
        $email = new NotificationEmail($this->getTranslator());
        $email->updateFooterText($this->appName)
            ->action($this->trans('index.title'), $this->getHomeUrl());

        return $email;
    }

    private function getHomeUrl(): string
    {
        return $this->generator->generate(AbstractController::HOME_PAGE, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
