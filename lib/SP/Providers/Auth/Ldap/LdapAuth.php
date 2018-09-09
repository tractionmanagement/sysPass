<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Providers\Auth\Ldap;

use SP\Core\Events\EventDispatcher;
use SP\DataModel\UserLoginData;
use SP\Providers\Auth\AuthInterface;

/**
 * Class LdapBase
 *
 * @package Auth\Ldap
 */
class LdapAuth implements AuthInterface
{
    /**
     * @var string
     */
    protected $userLogin;
    /**
     * @var LdapAuthData
     */
    protected $ldapAuthData;
    /**
     * @var LdapParams
     */
    protected $ldapParams;
    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;
    /**
     * @var string
     */
    protected $server;
    /**
     * @var Ldap
     */
    private $ldap;

    /**
     * LdapBase constructor.
     *
     * @param Ldap            $ldap
     * @param EventDispatcher $eventDispatcher
     */
    public function __construct(Ldap $ldap, EventDispatcher $eventDispatcher)
    {
        $this->ldap = $ldap;
        $this->eventDispatcher = $eventDispatcher;

        $this->ldapAuthData = new LdapAuthData();
    }

    /**
     * @return LdapAuthData
     */
    public function getLdapAuthData()
    {
        return $this->ldapAuthData;
    }

    /**
     * @return string
     */
    public function getUserLogin()
    {
        return $this->userLogin;
    }

    /**
     * @param string $userLogin
     */
    public function setUserLogin($userLogin)
    {
        $this->userLogin = strtolower($userLogin);
    }

    /**
     * Autentificar al usuario
     *
     * @param UserLoginData $userLoginData Datos del usuario
     *
     * @return bool
     */
    public function authenticate(UserLoginData $userLoginData)
    {
        try {
            $this->ldapAuthData->setAuthGranted($this->isAuthGranted());
            $this->setUserLogin($userLoginData->getLoginUser());

            $ldapConnection = $this->ldap->getLdapConnection();
            $ldapConnection->connect();
            $ldapConnection->bind($this->ldapAuthData->getDn(), $userLoginData->getLoginPass());

            $this->getAttributes($userLoginData->getLoginUser());
        } catch (LdapException $e) {
            processException($e);

            $this->ldapAuthData->setStatusCode($e->getCode());

            return false;
        }

        return true;
    }

    /**
     * Indica si es requerida para acceder a la aplicación
     *
     * @return boolean
     */
    public function isAuthGranted()
    {
        return true;
    }

    /**
     * Obtener los atributos del usuario.
     *
     * @param string $userLogin
     *
     * @return LdapAuthData con los atributos disponibles y sus valores
     * @throws LdapException
     */
    public function getAttributes(string $userLogin)
    {
        $attributes = $this->ldap->getLdapActions()
            ->getAttributes($this->ldap->getUserDnFilter($userLogin));

        if (!empty($attributes['fullname'])) {
            $this->ldapAuthData->setName($attributes['fullname']);
        } else {
            $this->ldapAuthData->setName($attributes['name'] . ' ' . $attributes['sn']);
        }

        $this->ldapAuthData->setDn($attributes['dn']);
        $this->ldapAuthData->setEmail($attributes['mail']);
        $this->ldapAuthData->setExpire($attributes['expire']);

        $groups = is_array($attributes['group']) ? $attributes['group'] : [$attributes['group']];

        $this->ldapAuthData->setInGroup(
            $this->ldap->isUserInGroup(
                $attributes['dn'],
                $groups));

        return $this->ldapAuthData;
    }
}