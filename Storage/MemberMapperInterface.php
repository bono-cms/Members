<?php

/**
 * This file is part of the Bono CMS
 * 
 * Copyright (c) No Global State Lab
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Storage;

interface MemberMapperInterface
{
    /**
     * Finds a member
     * 
     * @param string $email
     * @param string $password
     * @return array
     */
    public function findMember($email, $password);

    /**
     * Determines whether account is free
     * 
     * @param string $email
     * @return boolean
     */
    public function isFree($email);

    /**
     * Confirms a user by their provided hash
     * 
     * @param string $key
     * @return integer
     */
    public function confirm($key);
}
