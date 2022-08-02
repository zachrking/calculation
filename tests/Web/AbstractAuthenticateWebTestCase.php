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

namespace App\Tests\Web;

use App\Entity\User;
use App\Interfaces\PropertyServiceInterface;
use App\Interfaces\RoleInterface;
use App\Repository\UserRepository;
use App\Service\ApplicationService;
use App\Tests\DatabaseTrait;
use App\Tests\ServiceTrait;
use App\Util\RoleBuilder;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Abstract class for authenticate user.
 */
abstract class AbstractAuthenticateWebTestCase extends WebTestCase
{
    use DatabaseTrait;
    use ServiceTrait;

    final public const ID_ADMIN = 2;
    final public const ID_DISABLE = 4;
    final public const ID_SUPER_ADMIN = 1;
    final public const ID_USER = 3;

    final public const ROLE_ADMIN = RoleInterface::ROLE_ADMIN;
    final public const ROLE_DISABLED = 'ROLE_DISABLED';
    final public const ROLE_FAKE = 'ROLE_FAKE';
    final public const ROLE_SUPER_ADMIN = RoleInterface::ROLE_SUPER_ADMIN;
    final public const ROLE_USER = RoleInterface::ROLE_USER;

    protected ?KernelBrowser $client = null;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        // get rights
        $userRight = RoleBuilder::getRoleUser()->getRights();
        $adminRight = RoleBuilder::getRoleAdmin()->getRights();

        $application = $this->getService(ApplicationService::class);
        $application->setProperties([
            PropertyServiceInterface::P_USER_RIGHTS => $userRight,
            PropertyServiceInterface::P_ADMIN_RIGHTS => $adminRight,
            PropertyServiceInterface::P_QR_CODE => true,
        ]);
    }

    /**
     * Checks the given URL.
     *
     * @param string $url      the URL to be tested
     * @param string $username the username to login
     * @param int    $expected the expected result
     */
    protected function checkResponse(string $url, string $username, int $expected): void
    {
        $response = $this->client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertEquals($expected, $statusCode, "Invalid status code for '{$url}' and '{$username}'.");
    }

    /**
     * @param mixed $value
     */
    protected function doEcho(string $name, $value, bool $newLine = false): void
    {
        $format = "\n%-15s: %s" . ($newLine ? "\n" : '');
        \printf($format, \htmlspecialchars($name), $value);
    }

    /**
     * Loads an user from the database.
     *
     * @param string $username the username to search for
     * @param bool   $verify   true to check if the user is not null
     *
     * @return User|null the user, if found; null otherwise
     */
    protected function loadUser(string $username, bool $verify = true): ?User
    {
        $repository = $this->getService(UserRepository::class);

        /** @var User|null $user */
        $user = $repository->findByUsername($username);

        if ($verify) {
            $this->assertNotNull($user, "The user '$username' is null.");
        }

        return $user;
    }

    /**
     * Login the given user.
     *
     * @param User   $user     the user to login
     * @param string $firewall the firewall name
     */
    protected function loginUser(User $user, string $firewall = 'main'): void
    {
        $this->client->loginUser($user, $firewall);
    }

    /**
     * Login the given user name.
     *
     * @param string $username the user name to login
     * @param bool   $verify   true to check if the user is not null
     * @param string $firewall the firewall name
     */
    protected function loginUserName(string $username, bool $verify = true, string $firewall = 'main'): User
    {
        $user = $this->loadUser($username, $verify);
        $this->loginUser($user, $firewall);

        return $user;
    }
}
