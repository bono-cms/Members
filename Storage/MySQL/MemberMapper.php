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
