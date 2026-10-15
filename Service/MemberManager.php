<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

use Krystal\Application\Model\AbstractService;
use Krystal\Stdlib\VirtualEntity;
use Members\Storage\MySQL\MemberMapper;
use Members\Storage\MySQL\MemberTranslationMapper;

final class MemberManager extends AbstractService
{
    /**
     * Member mapper
     * 
     * @var \Members\Storage\MySQL\MemberMapper
     */
    private $mapper;

    /**
     * Member translation mapper
     * 
     * @var \Members\Storage\MySQL\MemberTranslationMapper
     */
    private $translationMapper;

    /**
     * Session bag component
     * 
     * @var mixed
     */
    private $sessionBag;

    /**
     * State initialization
     * 
     * @param \Members\Storage\MySQL\MemberMapper $mapper
     * @param \Members\Storage\MySQL\MemberTranslationMapper $translationMapper
     * @param mixed $sessionBag
     * @return void
     */
    public function __construct(MemberMapper $mapper, MemberTranslationMapper $translationMapper, $sessionBag)
    {
        $this->mapper = $mapper;
        $this->translationMapper = $translationMapper;
        $this->sessionBag = $sessionBag;
    }

    /**
     * Converts a database row into a Member entity object.
     * Excludes password hash for security.
     *
     * @param array $row
     * @return \Krystal\Stdlib\VirtualEntity
     */
    protected function toEntity(array $row): VirtualEntity
    {
        $member = new VirtualEntity();
        $member->setId((int) $row['id'])
               ->setName($row['name'])
               ->setEmail($row['email'])
               ->setLogin($row['login'])
               ->setPhone(isset($row['phone']) ? $row['phone'] : null)
               ->setCountry(isset($row['country']) ? $row['country'] : null)
               ->setAddress(isset($row['address']) ? $row['address'] : null)
               ->setAvatar(isset($row['avatar']) ? $row['avatar'] : null)
               ->setSubscriber(isset($row['subscriber']) ? $row['subscriber'] : null)
               ->setConfirmed(isset($row['confirmed']) ? $row['confirmed'] : null)
               ->setCreatedAt(isset($row['created_at']) ? $row['created_at'] : null)
               // Translation columns (present only when $withTranslations = true)
               ->setLangId(isset($row['lang_id']) ? (int) $row['lang_id'] : 0)
               ->setTitle(isset($row['title']) ? $row['title'] : null)
               ->setCompany(isset($row['company']) ? $row['company'] : null)
               ->setBio(isset($row['bio']) ? $row['bio'] : null);

        return $member;
    }

    /**
     * Fetches all members with pagination.
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchAll($pageNumber, $itemsPerPage): array
    {
        return $this->prepareResults($this->mapper->fetchAll($pageNumber, $itemsPerPage));
    }

    /**
     * Fetches member's entity by its associated ID
     * 
     * @param string $id
     * @param boolean $withTranslations Whether to fetch translations or not
     * @return \Krystal\Stdlib\VirtualEntity|array|boolean
     */
    public function fetchById($id, $withTranslations = false)
    {
        if ($withTranslations == true) {
            return $this->prepareResults($this->mapper->fetchById($id, true));
        } else {
            return $this->prepareResult($this->mapper->findByPk($id));
        }
    }

    /**
     * Saves a member record and its translations (insert or update).
     * 
     * @param array $input Full POST array with 'member' and 'translation' keys
     * @return bool
     */
    public function save(array $input): bool
    {
        $data = isset($input['member']) ? $input['member'] : $input;
        $translations = isset($input['translation']) ? $input['translation'] : [];

        $data['subscriber'] = !empty($data['subscriber']) ? '1' : '0';
        $data['confirmed']  = !empty($data['confirmed']) ? '1' : '0';

        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        unset($data['password']);

        if (empty($data['id'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $this->mapper->saveEntity($data, $translations);
    }

    /**
     * Updates an existing member profile.
     *
     * @param array $input
     * @return bool
     */
    public function update(array $input): bool
    {
        $id = isset($input['id']) ? $input['id'] : $this->getMember('id');

        if (!$id) {
            return false;
        }

        if (!empty($input['password'])) {
            $input['password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        unset($input['password']);

        return $this->mapper->updateProfile($id, $input);
    }

    /**
     * Deletes a member and their translations by ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteById($id): bool
    {
        return $this->mapper->deleteEntity($id);
    }

    /**
     * Authenticates a member by identifier (email or login) and password.
     *
     * @param string $identifier
     * @param string $password
     * @return bool
     */
    public function login(string $identifier, string $password): bool
    {
        $row = $this->mapper->findByEmail($identifier);

        if (!$row) {
            $row = $this->mapper->findByLogin($identifier);
        }

        if ($row && password_verify($password, $row['password_hash'])) {
            $this->sessionBag->set('member', [
                'id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'login' => $row['login']
            ]);
            return true;
        }

        return false;
    }

    /**
     * Logs out the current member.
     *
     * @return void
     */
    public function logout()
    {
        $this->sessionBag->remove('member');
    }

    /**
     * Checks whether a member is logged in.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->sessionBag->has('member');
    }

    /**
     * Retrieves session data for the logged-in member.
     *
     * @param string|null $key
     * @return mixed
     */
    public function getMember($key = null)
    {
        $member = $this->sessionBag->get('member');

        if ($key === null) {
            return $member;
        }

        return isset($member[$key]) ? $member[$key] : null;
    }

    /**
     * Generates and stores a password recovery key for an email.
     *
     * @param string $email
     * @return string|false
     */
    public function resetKey($email)
    {
        $row = $this->mapper->findByEmail($email);

        if (!$row) {
            return false;
        }

        $key = md5(uniqid(rand(), true));
        $affected = $this->mapper->updateKey($email, $key);

        return $affected ? $key : false;
    }

    /**
     * Resets a member's password using an activation key.
     *
     * @param string $key
     * @return string|false New plain text password or false on failure
     */
    public function resetPassword($key)
    {
        $newPassword = substr(md5(uniqid(rand(), true)), 0, 8);
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $affected = $this->mapper->updatePasswordByKey($key, $hash);

        return $affected ? $newPassword : false;
    }

    /**
     * Confirms a member account by activation key.
     *
     * @param string $key
     * @return bool
     */
    public function confirm($key): bool
    {
        return (bool) $this->mapper->confirm($key);
    }

    /**
     * Checks if an email is available for registration.
     *
     * @param string $email
     * @return bool
     */
    public function isFree($email): bool
    {
        return $this->mapper->isFree($email);
    }

    /**
     * Gets the last inserted record ID.
     *
     * @return int
     */
    public function getLastId()
    {
        return $this->mapper->getLastId();
    }

    /**
     * Gets the paginator instance.
     *
     * @return mixed
     */
    public function getPaginator()
    {
        return $this->mapper->getPaginator();
    }
}