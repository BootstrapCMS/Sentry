<?php

/*
 * This file is part of Sentry.
 *
 * (c) Cartalyst LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cartalyst\Sentry\Facades\Native;

use Cartalyst\Sentry\Cookies\CookieInterface;
use Cartalyst\Sentry\Cookies\NativeCookie;
use Cartalyst\Sentry\Facades\ConnectionResolver;
use Cartalyst\Sentry\Facades\Facade;
use Cartalyst\Sentry\Groups\Eloquent\Provider as GroupProvider;
use Cartalyst\Sentry\Groups\ProviderInterface as GroupProviderInterface;
use Cartalyst\Sentry\Hashing\NativeHasher;
use Cartalyst\Sentry\Sentry as BaseSentry;
use Cartalyst\Sentry\Sessions\NativeSession;
use Cartalyst\Sentry\Sessions\SessionInterface;
use Cartalyst\Sentry\Throttling\Eloquent\Provider as ThrottleProvider;
use Cartalyst\Sentry\Throttling\ProviderInterface as ThrottleProviderInterface;
use Cartalyst\Sentry\Users\Eloquent\Provider as UserProvider;
use Cartalyst\Sentry\Users\ProviderInterface as UserProviderInterface;
use Illuminate\Database\Eloquent\Model as Eloquent;
use PDO;

class Sentry extends Facade
{
    /**
     * Creates a Sentry instance.
     *
     * @param \Cartalyst\Sentry\Users\ProviderInterface      $userProvider
     * @param \Cartalyst\Sentry\Groups\ProviderInterface     $groupProvider
     * @param \Cartalyst\Sentry\Throttling\ProviderInterface $throttleProvider
     * @param \Cartalyst\Sentry\Sessions\SessionInterface    $session
     * @param \Cartalyst\Sentry\Cookies\CookieInterface      $cookie
     * @param string                                         $ipAddress
     *
     * @return \Cartalyst\Sentry\Sentry
     */
    public static function createSentry(
        UserProviderInterface $userProvider = null,
        GroupProviderInterface $groupProvider = null,
        ThrottleProviderInterface $throttleProvider = null,
        SessionInterface $session = null,
        CookieInterface $cookie = null,
        $ipAddress = null
    ) {
        $userProvider = $userProvider ?: new UserProvider(new NativeHasher());

        return new BaseSentry(
            $userProvider,
            $groupProvider ?: new GroupProvider(),
            $throttleProvider ?: new ThrottleProvider($userProvider),
            $session ?: new NativeSession(),
            $cookie ?: new NativeCookie(),
            $ipAddress ?: static::guessIpAddress()
        );
    }

    /**
     * Sets up the Eloquent Connection Resolver with the given PDO connection.
     *
     * @param PDO    $pdo
     * @param string $driverName
     * @param string $tablePrefix
     *
     * @return void
     */
    public static function setupDatabaseResolver(PDO $pdo, $driverName = null, $tablePrefix = '')
    {
        // If Eloquent doesn't exist, then we must assume they are using their own providers.
        if (class_exists('Illuminate\Database\Eloquent\Model')) {
            if (is_null($driverName)) {
                $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            }

            Eloquent::setConnectionResolver(new ConnectionResolver($pdo, $driverName, $tablePrefix));
        }
    }

    /**
     * Looks through various server properties in an attempt
     * to guess the client's IP address.
     *
     * @return string $ipAddress
     */
    public static function guessIpAddress()
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'] as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ipAddress) {
                    $ipAddress = trim($ipAddress);

                    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ipAddress;
                    }
                }
            }
        }
    }
}
