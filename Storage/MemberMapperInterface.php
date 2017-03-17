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
     * Updates a profile
     * 
     * @param string $id Member ID
     * @param array $data Data to be updated
     * @return boolean
     */
    public function updateProfile($id, array $data);

    /**
     * Updates a password
     * 
     * @param string $key Confirmation key
     * @param string $password New password
     * @return boolean
     */
    public function updatePassword($key, $password);

    /**
     * Updates a key
     * 
     * @param string $email
     * @param string $key
     * @return integer
     */
    public function updateKey($email, $key);

    /**
     * Confirms a user by their provided hash
     * 
     * @param string $key
     * @return integer
     */
    public function confirm($key);
}
