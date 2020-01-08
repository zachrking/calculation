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

namespace App\Interfaces;

/**
 * Class implementing this interface deals with role names.
 *
 * @author Laurent Muller
 */
interface IRole
{
    /**
     * Gets the role.
     */
    public function getRole(): string;

    /**
     * Tells if this has the admin role.
     */
    public function isAdmin(): bool;

    /**
     * Tells if this has the super admin role.
     */
    public function isSuperAdmin(): bool;
}
