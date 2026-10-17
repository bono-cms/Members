<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Storage;

interface MemberMapperInterface
{
    /**
     * Updates a single translation field for a member in the active language.
     *
     * @param int $memberId
     * @param string $field
     * @param mixed $value
     * @return boolean
     */
    public function updateTranslationField($memberId, $field, $value);

    /**
     * Updates a single translation field for a member in a given language.
     *
     * @param int $memberId
     * @param int $langId
     * @param string $field
     * @param mixed $value
     * @return boolean
     */
    public function updateTranslationFieldByLang($memberId, $langId, $field, $value);

    /**
     * Fetches a single translation row for a member in the active language.
     *
     * @param int $memberId
     * @return array|boolean
     */
    public function fetchTranslation($memberId);

    /**
     * Fetches a single translation row for a member in a given language.
     *
     * @param int $memberId
     * @param int $langId
     * @return array|boolean
     */
    public function fetchTranslationByLang($memberId, $langId);

    /**
     * Fetches a paginated list of confirmed members
     * 
     * @param integer $pageNumber Current page number
     * @param integer $itemsPerPage Per page count
     * @return array
     */
    public function fetchConfirmed($pageNumber, $itemsPerPage);

    /**
     * Fetches a confirmed member by their associated ID
     * 
     * Unconfirmed members are treated as not found. The row returned
     * matches the currently active language.
     * 
     * @param string $id Member ID
     * @return array|boolean Row as array on success, false if not found
     */
    public function findConfirmedById($id);

    /**
     * Updates an activation key by member ID
     * 
     * Unlike updateKey(), this method does not filter by the
     * "confirmed" state. It's used when a member is already
     * authenticated and needs a fresh key to re-receive the
     * confirmation email.
     * 
     * @param string $id Member id
     * @param string $key New activation key
     * @return boolean Depending on success
     */
    public function updateActivationKey($id, $key);

    /**
     * Finds a member by their activation key
     * 
     * @param string $key Activation key
     * @return array|boolean Row as array on success, false if not found
     */
    public function findByActivationKey($key);

    /**
     * Deletes a member by their associated id
     * 
     * @param string $id Member id
     * @return boolean
     */
    public function deleteById($id);

    /**
     * Fetch all members
     * 
     * @param integer $pageNumber Current page number
     * @param integer $itemPerPage Per page count
     * @return array
     */
    public function fetchAll($pageNumber, $itemsPerPage);

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
