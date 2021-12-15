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

namespace App\Model;

/**
 * Contains information about the customer.
 *
 * @author Laurent Muller
 */
class CustomerInformation
{
    private ?string $address = null;
    private ?string $email = null;
    private ?string $fax = null;
    private ?string $name = null;
    private ?string $phone = null;
    private bool $printAddress = false;
    private ?string $url = null;

    /**
     * Gets the address.
     */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * Gets the e-mail.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Gets the fax number.
     */
    public function getFax(): ?string
    {
        return $this->fax;
    }

    /**
     * Gets the name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Gets the phone number.
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * Gets the url.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Gets if the address is printed.
     */
    public function isPrintAddress(): bool
    {
        return $this->printAddress;
    }

    /**
     * Sets the address.
     */
    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Sets the e-mail.
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Sets the fax number.
     */
    public function setFax(?string $fax): self
    {
        $this->fax = $fax;

        return $this;
    }

    /**
     * Sets the name.
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Sets the phone number.
     */
    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Sets if the address is printed.
     */
    public function setPrintAddress(bool $printAddress): self
    {
        $this->printAddress = $printAddress;

        return $this;
    }

    /**
     * Sets the url.
     */
    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }
}
