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
use Krystal\Stdlib\ArrayUtils;
use Krystal\Stdlib\VirtualEntity;
use Cms\Service\AbstractManager;

final class MemberManager extends AbstractManager implements MemberManagerInterface
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
     * {@inheritDoc}
     */
    protected function toEntity(array $row)
    {
        $entity = new VirtualEntity();
        $entity->setId($row['id'], VirtualEntity::FILTER_INT)
               ->setName($row['name'], VirtualEntity::FILTER_TAGS)
               ->setEmail($row['email'], VirtualEntity::FILTER_TAGS)
               ->setLogin($row['login'], VirtualEntity::FILTER_TAGS)
               ->setPhone($row['phone'], VirtualEntity::FILTER_TAGS)
               ->setAddress($row['address'])
               ->setSubscriber($row['subscriber'], VirtualEntity::FILTER_BOOL)
               ->setConfirmed($row['confirmed'], VirtualEntity::FILTER_BOOL);

        return $entity;
    }

    /**
     * Fetches member by id
     * 
     * @param string $id Member id
     * @return string
     */
    public function fetchById($id)
    {
        return $this->prepareResult($this->memberMapper->findByPk($id));
    }

    /**
     * Returns prepared paginator instance
     * 
     * @return \Krystal\Paginate\Paginator
     */
    public function getPaginator()
    {
        return $this->memberMapper->getPaginator();
    }

    /**
     * Deletes a member by their associated id
     * 
     * @param string $id Member id
     * @return string
     */
    public function deleteById($id)
    {
        return $this->memberMapper->deleteById($id);
    }

    /**
     * Fetch all members
     * 
     * @param integer $pageNumber Page number
     * @param integer $itemsPerPage Per page count
     * @return array
     */
    public function fetchAll($page, $itemsPerPage)
    {
        return $this->prepareResults($this->memberMapper->fetchAll($page, $itemsPerPage));
    }

    /**
     * Updates a member
     * 
     * @param array $input
     * @return boolean
     */
    public function update(array $input)
    {
        // There's no such field, so this one must be removed if present
        if (isset($input['password_confirm'])) {
            unset($input['password_confirm']);
        }

        // If password is empty, then it should not be updated
        if (empty($input['password'])) {
            unset($input['password']);
        } else {
            $input['password'] = sha1($input['password']);
        }

        $id = $this->getMember('id');

        // Update a profile
        $this->memberMapper->updateProfile($id, $input);

        // Update data in session as well
        $this->sessionBag->set('member', $this->memberMapper->findByPk($id));

        return true;
    }

    /**
     * Logouts a member
     * 
     * @return boolean
     */
    public function logout()
    {
        if ($this->sessionBag->has('member')) {
            $this->sessionBag->remove('member');
            return true;
        } else {
            return false;
        }
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
     * Resets a password
     * 
     * @param string $key
     * @return string|boolean
     */
    public function resetPassword($key)
    {
        $password = uniqid();

        if ($this->memberMapper->updatePassword($key, sha1($password)) != 0) {
            return $password;
        } else {
            return false;
        }
    }

    /**
     * Resets the secure code by email
     * 
     * @param string $email
     * @return string|boolean
     */
    public function resetKey($email)
    {
        // Create very unique key
        $key = md5(uniqid());

        if ($this->memberMapper->updateKey($email, $key) != 0) {
            return $key;
        } else {
            return false;
        }
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
