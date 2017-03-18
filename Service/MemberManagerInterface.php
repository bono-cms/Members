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

interface MemberManagerInterface
{
    /**
     * Returns prepared paginator instance
     * 
     * @return \Krystal\Paginate\Paginator
     */
    public function getPaginator();

    /**
     * Fetch all members
     * 
     * @param integer $pageNumber Page number
     * @param integer $itemsPerPage Per page count
     * @return array
     */
    public function fetchAll($page, $itemsPerPage);

    /**
     * Updates a member
     * 
     * @param array $input
     * @return boolean
     */
    public function update(array $input);

    /**
     * Logouts a member
     * 
     * @return boolean
     */
    public function logout();

    /**
     * Checks whether member is logged in
     * 
     * @return boolean
     */ 
    public function isLoggedIn();

    /**
     * Returns member data
     * 
     * @param string $key Optional filtering key
     * @return array|boolean
     */
    public function getMember($key = null);

    /**
     * Attempts to login
     * 
     * @param string $email
     * @param string $password
     * @return boolean
     */
    public function login($email, $password);

    /**
     * Confirms a member by their associated key
     * 
     * @param string $key
     * @return boolean
     */
    public function confirm($key);

    /**
     * Resets a password
     * 
     * @param string $key
     * @return string|boolean
     */
    public function resetPassword($key);

    /**
     * Resets the secure code by email
     * 
     * @param string $email
     * @return string|boolean
     */
    public function resetKey($email);

    /**
     * Registers a member
     * 
     * @param array $input
     * @return array|boolean False if email already exists, array with meta data on success
     */
    public function register(array $input);
}
