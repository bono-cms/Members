<?php

/**
 * This file is part of the Bono CMS
 * 
 * Copyright (c) No Global State Lab
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Storage\MySQL;

use Cms\Storage\MySQL\AbstractMapper;
use Members\Storage\MemberMapperInterface;

final class MemberMapper extends AbstractMapper implements MemberMapperInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members');
    }

    /**
     * Deletes a member by their associated id
     * 
     * @param string $id Member id
     * @return boolean
     */
    public function deleteById($id)
    {
        return $this->deleteByPk($id);
    }

    /**
     * Fetch all members
     * 
     * @param integer $pageNumber Current page number
     * @param integer $itemPerPage Per page count
     * @return array
     */
    public function fetchAll($pageNumber, $itemsPerPage)
    {
        return $this->db->select('*')
                        ->from(self::getTableName())
                        ->orderBy($this->getPk())
                        ->desc()
                        ->paginate($pageNumber, $itemsPerPage)
                        ->queryAll();
    }

    /**
     * Finds a member
     * 
     * @param string $email
     * @param string $password
     * @return array
     */
    public function findMember($email, $password)
    {
        return $this->db->select('*')
                        ->from(self::getTableName())
                        ->whereEquals('email', $email)
                        ->andWhereEquals('password', $password)
                        ->andWhereEquals('confirmed', '1')
                        ->query();
    }

    /**
     * Determines whether account is free
     * 
     * @param string $email
     * @return boolean
     */
    public function isFree($email)
    {
        return $this->db->select()
                        ->count('email', 'count')
                        ->from(self::getTableName())
                        ->whereEquals('email', $email)
                        ->query('count') == 0;
    }

    /**
     * Updates a profile
     * 
     * @param string $id Member ID
     * @param array $data Data to be updated
     * @return boolean
     */
    public function updateProfile($id, array $data)
    {
        return $this->db->update(self::getTableName(), $data)
                        ->whereEquals('id', $id)
                        ->execute();
    }

    /**
     * Updates a password
     * 
     * @param string $key Confirmation key
     * @param string $password New password
     * @return boolean
     */
    public function updatePassword($key, $password)
    {
        return $this->db->update(self::getTableName(), array('password' => $password))
                        ->whereEquals('key', $key)
                        ->andWhereEquals('confirmed', '1')
                        ->execute(true);
    }

    /**
     * Updates a key
     * 
     * @param string $email
     * @param string $key
     * @return integer
     */
    public function updateKey($email, $key)
    {
        return $this->db->update(self::getTableName(), array('key' => $key))
                        ->whereEquals('email', $email)
                        ->andWhereEquals('confirmed', '1')
                        ->execute(true);
    }

    /**
     * Confirms a user by their provided hash
     * 
     * @param string $key
     * @return integer
     */
    public function confirm($key)
    {
        return $this->db->update(self::getTableName(), array('confirmed' => '1'))
                        ->whereEquals('key', $key)
                        ->execute(true);
    }
}
