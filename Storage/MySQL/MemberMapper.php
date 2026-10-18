<?php

/**
 * This file is part of the Bono CMS
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
     * {@inheritDoc}
     */
    public static function getTranslationTable()
    {
        return MemberTranslationMapper::getTableName();
    }

    /**
     * Returns shared columns to be selected
     * 
     * @return array
     */
    private function getColumns()
    {
        return [
            self::column('id'),
            self::column('email'),
            self::column('login'),
            self::column('password_hash'),
            self::column('name'),
            self::column('phone'),
            self::column('address'),
            self::column('country'),
            self::column('avatar'),
            self::column('subscriber'),
            self::column('activation_key'),
            self::column('confirmed'),
            self::column('created_at'),
            MemberTranslationMapper::column('lang_id'),
            MemberTranslationMapper::column('title'),
            MemberTranslationMapper::column('company'),
            MemberTranslationMapper::column('bio'),
        ];
    }
    
    /**
     * Updates a single translation field for a member in the active language.
     *
     * @param int $memberId Member ID
     * @param string $field Field name to update
     * @param mixed $value New value
     * @return boolean
     */
    public function updateTranslationField($memberId, $field, $value)
    {
        return $this->updateTranslationFieldByLang($memberId, $this->getLangId(), $field, $value);
    }

    /**
     * Updates a single translation field for a member in a given language.
     *
     * @param int $memberId Member ID
     * @param int $langId Language ID
     * @param string $field Field name to update
     * @param mixed $value New value
     * @return boolean
     */
    public function updateTranslationFieldByLang($memberId, $langId, $field, $value)
    {
        $db = $this->db->update(MemberTranslationMapper::getTableName(), [
            $field => $value
        ])->whereEquals('id', (int) $memberId)
          ->andWhereEquals('lang_id', (int) $langId);

        return $db->execute();
    }

    /**
     * Fetches a single translation row for a member in the active language.
     *
     * @param int $memberId Member ID
     * @return array|boolean
     */
    public function fetchTranslation($memberId)
    {
        return $this->fetchTranslationByLang($memberId, $this->getLangId());
    }

    /**
     * Fetches a single translation row for a member in a given language.
     *
     * @param int $memberId Member ID
     * @param int $langId Language ID
     * @return array|boolean
     */
    public function fetchTranslationByLang($memberId, $langId)
    {
        $db = $this->db->select('*')
                       ->from(MemberTranslationMapper::getTableName())
                       ->whereEquals('id', (int) $memberId)
                       ->andWhereEquals('lang_id', (int) $langId);

        return $db->query();
    }

    /**
     * {@inheritDoc}
     */
    public function fetchConfirmed($pageNumber, $itemsPerPage)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('confirmed', '1')
                       ->orderBy($this->getPk())
                       ->desc()
                       ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * {@inheritDoc}
     */
    public function findConfirmedById($id)
    {
        $db = $this->db->select($this->getColumns())
                        ->from(self::getTableName())
                        // Translation relation
                        ->innerJoin(MemberTranslationMapper::getTableName(), [
                            MemberTranslationMapper::column('id') => self::getRawColumn('id'),
                            MemberTranslationMapper::column('lang_id') => $this->getLangId()
                        ])
                        ->whereEquals(self::column('id'), $id)
                        ->andWhereEquals(self::column('confirmed'), '1');

        return $db->query();
    }

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
    public function updateActivationKey($id, $key)
    {
        $db = $this->db->update(self::getTableName(), ['activation_key' => $key])
                       ->whereEquals('id', $id);

        return $db->execute();
    }

    /**
     * Finds a member by their activation key
     * 
     * @param string $key Activation key
     * @return array|boolean Row as array on success, false if not found
     */
    public function findByActivationKey($key)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('activation_key', $key);

        return $db->query();
    }

    /**
     * Fetches member data by its associated ID
     * 
     * @param string $id Member ID
     * @param boolean $withTranslations Whether to fetch translations or not
     * @return array
     */
    public function fetchById($id, $withTranslations)
    {
        return $this->findEntity($this->getColumns(), $id, $withTranslations);
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
     * @param integer $itemsPerPage Per page count
     * @return array
     */
    public function fetchAll($pageNumber, $itemsPerPage)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->orderBy($this->getPk())
                       ->desc()
                       ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Finds a member by email and verifies password
     * 
     * @param string $email
     * @param string $password
     * @return array|boolean
     */
    public function findMember($email, $password)
    {
        $row = $this->findByEmail($email);

        if ($row && password_verify($password, $row['password_hash'])) {
            return $row;
        }

        return false;
    }

    /**
     * Determines whether the given email is not yet taken
     * 
     * @param string $email
     * @return boolean
     */
    public function isFree($email)
    {
        $db = $this->db->select()
                       ->count('email', 'count')
                       ->from(self::getTableName())
                       ->whereEquals('email', $email);

        return $db->query('count') == 0;
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
        $db = $this->db->update(self::getTableName(), $data)
                       ->whereEquals('id', $id);

        return $db->execute();
    }

    /**
     * Updates a password by confirmation key
     * 
     * @param string $key Confirmation key
     * @param string $password New password hash produced by password_hash()
     * @return boolean
     */
    public function updatePassword($key, $password)
    {
        return (bool) $this->updatePasswordByKey($key, $password);
    }

    /**
     * Finds a member by their email address
     * 
     * @param string $email
     * @return array|boolean Row as array on success, false if not found
     */
    public function findByEmail($email)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('email', $email);

        return $db->query();
    }

    /**
     * Finds a member by their login
     * 
     * @param string $login
     * @return array|boolean Row as array on success, false if not found
     */
    public function findByLogin($login)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('login', $login);

        return $db->query();
    }

    /**
     * Updates a password by activation key
     * 
     * @param string $key Activation key
     * @param string $passwordHash New password hash produced by password_hash()
     * @return integer Number of affected rows
     */
    public function updatePasswordByKey($key, $passwordHash)
    {
        $db = $this->db->update(self::getTableName(), ['password_hash' => $passwordHash])
                       ->whereEquals('activation_key', $key)
                       ->andWhereEquals('confirmed', '1');

        return $db->execute(true);
    }

    /**
     * Updates an activation key by email
     * 
     * @param string $email
     * @param string $key
     * @return integer Number of affected rows
     */
    public function updateKey($email, $key)
    {
        $db = $this->db->update(self::getTableName(), ['activation_key' => $key])
                       ->whereEquals('email', $email)
                       ->andWhereEquals('confirmed', '1');

        return $db->execute(true);
    }

    /**
     * Confirms a member by their provided activation key
     * 
     * @param string $key
     * @return integer Number of affected rows
     */
    public function confirm($key)
    {
        $db = $this->db->update(self::getTableName(), ['confirmed' => '1'])
                       ->whereEquals('activation_key', $key);

        return $db->execute(true);
    }

    /**
     * Sets a new password_changed_at timestamp by member id
     * 
     * @param string $id Member id
     * @param string $datetime
     * @return boolean
     */
    public function touchPasswordChangedAt($id, $datetime)
    {
        $db = $this->db->update(self::getTableName(), ['password_changed_at' => $datetime])
                       ->whereEquals('id', $id);

        return $db->execute();
    }
}