<?php

/**
 * This file is part of the Bono CMS
 * 
 * Copyright (c) No Global State Lab
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

use Members\Storage\MemberMapperInterface;
use Krystal\Session\SessionBagInterface;

final class MemberManager implements MemberManagerInterface
{
    /**
     * Any compliant member mapper
     * 
     * @var \Members\Storage\MemberMapperInterface
     */
    private $memberMapper;

    /**
     * State initialization
     * 
     * @param \Members\Storage\MemberMapperInterface $memberMapper
     * @param \Krystal\Session\SessionBagInterface $sessionBag
     * @return void
     */
    public function __construct(MemberMapperInterface $memberMapper, SessionBagInterface $sessionBag)
    {
        $this->memberMapper = $memberMapper;
        $this->sessionBag = $sessionBag;
    }

    /**
     * Checks whether member is logged in
     * 
     * @return boolean
     */ 
    public function isLoggedIn()
    {
        return $this->sessionBag->has('member');
    }

    /**
     * Returns member data
     * 
     * @param string $key Optional filtering key
     * @return array|boolean
     */
    public function getMember($key = null)
    {
        if ($this->sessionBag->has('member')) {
            $data = $this->sessionBag->get('member');

            if ($key === null) {
                return $data;
            } else {
                return $data[$key];
            }

        } else {
            return false;
        }
    }

    /**
     * Attempts to login
     * 
     * @param string $email
     * @param string $password
     * @return boolean
     */
    public function login($email, $password)
    {
        $row = $this->findMember($email, $password);

        if (!empty($row)) {
            $this->sessionBag->set('member', $row);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Finds a member
     * 
     * @param string $email
     * @param string $password
     * @return array
     */
    private function findMember($email, $password)
    {
        $password = sha1($password);
        return $this->memberMapper->findMember($email, $password);
    }

    /**
     * Confirms a member by their associated key
     * 
     * @param string $key
     * @return boolean
     */
    public function confirm($key)
    {
        $count = $this->memberMapper->confirm($key);
        return $count !== 0;
    }

    /**
     * Registers a member
     * 
     * @param array $input
     * @return array|boolean False if email already exists, array with meta data on success
     */
    public function register(array $input)
    {
        if ($this->memberMapper->isFree($input['email'])) {
            // Create very unique key
            $key = md5(uniqid());

            unset($input['password_confirm']);

            $input['password'] = sha1($input['password']);
            $input['key'] = $key;
            $input['confirmed'] = '0'; // Not confirmed by default

            return $this->memberMapper->persistRow($input);

        } else {
            return false;
        }
    }
}
