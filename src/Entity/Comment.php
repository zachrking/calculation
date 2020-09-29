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

namespace App\Entity;

use SimpleHtmlToText\Parser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represent a comment to send.
 *
 * @author Laurent Muller
 */
class Comment
{
    /**
     * The attachments.
     *
     * @var UploadedFile[]
     *
     * @Assert\Count(max=3)
     * @Assert\All(@Assert\File(maxSize="10485760"))
     */
    private $attachments;

    /**
     * The from address.
     *
     * @var Address
     *
     * @Assert\NotNull
     */
    private $fromAddress;

    /**
     * The mail type.
     *
     * @var bool
     */
    private $mail;

    /**
     * The message.
     *
     * @var string
     *
     * @Assert\NotNull
     */
    private $message;

    /**
     * The subject.
     *
     * @var string
     *
     * @Assert\NotNull
     */
    private $subject;

    /**
     * The to address.
     *
     * @var Address
     *
     * @Assert\NotNull
     */
    private $toAddress;

    /**
     * Constructor.
     *
     * @param bool $mail true if e-mail, false if comment
     */
    public function __construct(bool $mail)
    {
        $this->mail = $mail;
    }

    /**
     * Gets the file attachments.
     *
     * @return \Symfony\Component\HttpFoundation\File\UploadedFile[]
     */
    public function getAttachments(): array
    {
        return $this->attachments ?? [];
    }

    /**
     * Gets the "from" e-mail and name (if any).
     */
    public function getFrom(): ?string
    {
        return null !== $this->fromAddress ? $this->fromAddress->toString() : null;
    }

    /**
     * Gets the "from" address.
     */
    public function getFromAddress(): ?Address
    {
        return $this->fromAddress;
    }

    /**
     * Gets the message.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Gets the subject.
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * Gets the "to" e-mail and name (if any).
     */
    public function getTo(): ?string
    {
        return null !== $this->toAddress ? $this->toAddress->toString() : null;
    }

    /**
     * Gets the "to" address.
     */
    public function getToAddress(): ?Address
    {
        return $this->toAddress;
    }

    /**
     * Returns if this is an e-mail or a comment.
     *
     * @return bool true if e-mail, false if comment
     */
    public function isMail(): bool
    {
        return $this->mail;
    }

    /**
     * Sends this message using the given mailer.
     *
     * @param MailerInterface $mailer the mailer service
     *
     * @throws TransportExceptionInterface if the email can not be send
     */
    public function send(MailerInterface $mailer): void
    {
        $email = new Email();
        $email->addFrom($this->fromAddress)
            ->addTo($this->toAddress)
            ->subject($this->subject)
            ->text($this->getTextMessage())
            ->html($this->getHtmlMessage());

        // add attachments
        foreach ($this->getAttachments() as $attachment) {
            $this->addAttachment($email, $attachment);
        }

        // send
        $mailer->send($email);
    }

    /**
     * Sets the file attachments.
     *
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile[] $attachments
     */
    public function setAttachments(?array $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * Sets the "from" address.
     */
    public function setFromAddress(Address $fromAddress): self
    {
        $this->fromAddress = $fromAddress;

        return $this;
    }

    /**
     * Sets the "from" user.
     */
    public function setFromUser(User $user): self
    {
        return $this->setFromAddress($user->getAddress());
    }

    /**
     * Sets the message.
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Sets the subject.
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Sets the "to" address.
     */
    public function setToAddress(Address $toAddress): self
    {
        $this->toAddress = $toAddress;

        return $this;
    }

    /**
     * Sets the "to" user.
     */
    public function setToUser(User $user): self
    {
        return $this->setToAddress($user->getAddress());
    }

    /**
     * Adds the given uploaded file as attachment to the given email.
     *
     * @param Email        $email the email to attach file for
     * @param UploadedFile $file  the file to attach
     *
     * @return Email the email parameter
     */
    private function addAttachment(Email $email, ?UploadedFile $file): Email
    {
        if ($file && $file->isValid()) {
            $path = $file->getPathname();
            $name = $file->getClientOriginalName();
            $type = $file->getClientMimeType();

            return $email->attachFromPath($path, $name, $type);
        }

        return $email;
    }

    /**
     * Remove empty lines for the given message.
     *
     * @return string the cleaned message
     */
    private function getHtmlMessage(): string
    {
        $lines = \preg_split('/\r\n|\r|\n/', $this->message);
        $result = \array_filter($lines, function (string $line) {
            return !empty($line) && 0 !== \strcasecmp('<p>&nbsp;</p>', $line);
        });

        return \implode('', $result);
    }

    /**
     * Convert the given message as plain text.
     *
     * @return string the cleaned message
     */
    private function getTextMessage(): string
    {
        $parser = new Parser();
        $message = $this->message;

        return $parser->parseString($message);
    }
}
