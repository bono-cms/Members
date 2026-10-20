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
use Krystal\Image\Tool\ImageManagerInterface;
use Members\Storage\MySQL\MemberMapper;

final class MemberManager extends AbstractService
{
    /**
     * Member mapper
     * 
     * @var \Members\Storage\MySQL\MemberMapper
     */
    private $mapper;

    /**
     * Session bag component
     * 
     * @var mixed
     */
    private $sessionBag;

    /**
     * Avatar image manager
     * 
     * @var \Krystal\Image\Tool\ImageManagerInterface
     */
    private $avatarImageManager;

    /**
     * State initialization
     * 
     * @param \Members\Storage\MySQL\MemberMapper $mapper
     * @param mixed $sessionBag
     * @param \Krystal\Image\Tool\ImageManagerInterface $avatarImageManager
     * @return void
     */
    public function __construct(MemberMapper $mapper, $sessionBag, ImageManagerInterface $avatarImageManager)
    {
        $this->mapper = $mapper;
        $this->sessionBag = $sessionBag;
        $this->avatarImageManager = $avatarImageManager;
    }

    /**
     * Converts a database row into a Member entity object
     *
     * @param array $row
     * @return \Krystal\Stdlib\VirtualEntity
     */
    protected function toEntity(array $row): VirtualEntity
    {
        $entity = new MemberEntity();

        $entity->setId((int) $row['id'])
               ->setName(isset($row['name']) ? $row['name'] : null)
               ->setEmail($row['email'])
               ->setLogin($row['login'])
               ->setPhone(isset($row['phone']) ? $row['phone'] : null)
               ->setCountry(isset($row['country']) ? $row['country'] : null)
               ->setAddress(isset($row['address']) ? $row['address'] : null)
               ->setAvatar(isset($row['avatar']) ? $row['avatar'] : null)
               ->setSubscriber(isset($row['subscriber']) ? $row['subscriber'] : null)
               ->setConfirmed(isset($row['confirmed']) ? $row['confirmed'] : null)
               ->setCreatedAt(isset($row['created_at']) ? $row['created_at'] : null)
               ->setLangId(isset($row['lang_id']) ? (int) $row['lang_id'] : 0)
               ->setTitle(isset($row['title']) ? $row['title'] : null)
               ->setCompany(isset($row['company']) ? $row['company'] : null)
               ->setBio(isset($row['bio']) ? $row['bio'] : null);

        $avatarBag = clone $this->avatarImageManager->getImageBag();
        $avatarBag->setId((int) $row['id'])
                  ->setCover(isset($row['avatar']) ? $row['avatar'] : '');

        $entity->setAvatarBag($avatarBag);

        return $entity;
    }

    /**
     * Returns distinct non-empty company names for the directory filter
     *
     * @return array
     */
    public function fetchDistinctCompanies()
    {
        return $this->mapper->fetchDistinctCompanies();
    }

    /**
     * Fetches a paginated list of confirmed members, optionally
     * filtered by country, company, and tags
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @param string|null $country
     * @param string|null $company
     * @param array $tagIds
     * @return array
     */
    public function fetchFiltered($pageNumber, $itemsPerPage, $country = null, $company = null, array $tagIds = [])
    {
        return $this->prepareResults(
            $this->mapper->fetchFiltered($pageNumber, $itemsPerPage, $country, $company, $tagIds)
        );
    }

    /**
     * Fetches a collection of members by their ids as a lookup table
     *
     * Returns an array indexed by member id. Used to attach sender
     * information to a batch of messages without issuing one query
     * per sender, and to give group chats a single place to swap
     * the lookup for a `WHERE id IN (...)` query later.
     *
     * @param array $ids Member ids
     * @return array Lookup table: [id => ['id' => ..., 'name' => ..., ...], ...]
     */
    public function fetchLookupByIds(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return [];
        }

        $rows = $this->mapper->fetchByIds($ids);
        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'name' => isset($row['name']) ? $row['name'] : null,
                'login' => isset($row['login']) ? $row['login'] : null,
                'avatar' => isset($row['avatar']) ? $row['avatar'] : null
            ];
        }

        return $result;
    }

    /**
     * Updates a member's password directly by member id
     *
     * Used when a password change is submitted through the profile
     * form. Passwords bypass the moderation pipeline entirely, so
     * they are written to the live row immediately.
     *
     * @param int $id Member id
     * @param string $plain Plain-text password
     * @return boolean
     */
    public function updatePassword($id, $plain)
    {
        return $this->mapper->updateProfile($id, [
            'password_hash' => password_hash($plain, PASSWORD_DEFAULT)
        ]);
    }

    /**
     * Generates a fresh activation key
     *
     * Sole producer of activation keys. Any code path that needs a
     * key (registration, resend) must go through this method so the
     * key format lives in one place.
     *
     * @return string
     */
    public function generateActivationKey()
    {
        return md5(uniqid(rand(), true));
    }

    /**
     * Fetches a paginated list of confirmed members
     * 
     * @param integer $pageNumber Current page number
     * @param integer $itemsPerPage Per page count
     * @return array
     */
    public function fetchConfirmed($pageNumber, $itemsPerPage)
    {
        return $this->prepareResults(
            $this->mapper->fetchConfirmed($pageNumber, $itemsPerPage)
        );
    }

    /**
     * Fetches a public profile of a confirmed member by ID
     * 
     * Unconfirmed members are treated as not found.
     * 
     * @param int $id Member ID
     * @return \Krystal\Stdlib\VirtualEntity|boolean
     */
    public function fetchPublicProfile($id)
    {
        return $this->prepareResult(
            $this->mapper->findConfirmedById($id)
        );
    }

    /**
     * Fetches all members with pagination
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
     * @param boolean $withTranslations
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
     * Fetches a member with the translation for the active language.
     *
     * Falls back to the base row (no translation) when the member has
     * no translation for the current language, so callers always get
     * an entity for a member that exists. In that case name/title/bio
     * are null and getDisplayName() falls back to the login.
     *
     * @param int $id
     * @return \Krystal\Stdlib\VirtualEntity|boolean
     */
    public function fetchByIdForCurrentLang($id)
    {
        $rows = $this->mapper->fetchById($id, true);

        if (!empty($rows)) {
            return $this->prepareResult($rows[0]);
        }

        // Member exists but has no translation for this language
        return $this->prepareResult($this->mapper->findByPk($id));
    }

    /**
     * Prepares raw input data for persistence
     * 
     * @param array $data Raw member data
     * @return array
     */
    private function prepare(array $data)
    {
        $data['subscriber'] = !empty($data['subscriber']) ? '1' : '0';
        $data['confirmed']  = !empty($data['confirmed']) ? '1' : '0';

        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        unset($data['password'], $data['remove_avatar']);

        if (empty($data['id'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $data;
    }

    /**
     * Adds a member
     * 
     * New registrations are unconfirmed and unsubscribed by default.
     * If the caller provides an activation_key, it is used as-is;
     * otherwise the registration flow is expected to have generated
     * one via generateActivationKey() and embedded it in the payload.
     *
     * @param array $input Raw input data
     * @return boolean
     */
    public function add(array $input)
    {
        $data =& $input['data']['member'];
        $file = isset($input['files']['file']) ? $input['files']['file'] : false;

        // Sensible defaults for new registrations. Callers may override
        // these by setting them explicitly before calling add().
        if (!isset($data['confirmed'])) {
            $data['confirmed'] = '0';
        }

        if (!isset($data['subscriber'])) {
            $data['subscriber'] = '0';
        }

        $data = $this->prepare($data);
        $data['avatar'] = $file ? $file->getUniqueName() : '';

        $this->mapper->saveEntity($data, isset($input['data']['translation']) ? $input['data']['translation'] : []);

        $id = $this->getLastId();

        if ($file) {
            $this->avatarImageManager->upload($id, $file);
        }

        return true;
    }

    /**
     * Updates a member
     * 
     * @param array $input Raw input data
     * @return boolean
     */
    public function update(array $input)
    {
        $data =& $input['data']['member'];
        $file = isset($input['files']['file']) ? $input['files']['file'] : false;

        if (!empty($data['remove_avatar']) && !empty($data['avatar'])) {
            $this->avatarImageManager->delete($data['id'], $data['avatar']);
            $data['avatar'] = '';
        } else {
            if ($file) {
                if (!empty($data['avatar'])) {
                    $this->avatarImageManager->delete($data['id'], $data['avatar']);
                }

                $this->avatarImageManager->upload($data['id'], $file);
                $data['avatar'] = $file->getUniqueName();
            }
        }

        $data = $this->prepare($data);

        $this->mapper->saveEntity($data, isset($input['data']['translation']) ? $input['data']['translation'] : []);

        return true;
    }

    /**
     * Deletes a member and their avatar by ID
     * 
     * @param int $id
     * @return bool
     */
    public function deleteById($id): bool
    {
        $row = $this->mapper->findByPk($id);

        if ($row && !empty($row['avatar'])) {
            $this->avatarImageManager->delete($id, $row['avatar']);
        }

        return $this->mapper->deleteEntity($id);
    }

    /**
     * Regenerates a member's activation key and returns the new key
     *
     * Used when a logged-in member needs to re-receive the
     * confirmation email. Key generation lives here so the format is
     * defined in exactly one place.
     *
     * @param int $id Member id
     * @return string New activation key
     */
    public function refreshActivationKey($id)
    {
        $key = $this->generateActivationKey();

        $this->mapper->updateActivationKey($id, $key);

        return $key;
    }

    /**
     * Initiates a password recovery flow for an email
     *
     * Returns the recovery key on success and false when no account
     * matches. Callers must treat both outcomes identically at the
     * response level to prevent account enumeration; this method
     * exists so the anti-enumeration rule is enforced by the service
     * contract rather than by controllers remembering to ignore the
     * false branch.
     *
     * @param string $email
     * @return string|false
     */
    public function initiateRecovery($email)
    {
        return $this->resetKey($email);
    }

    /**
     * Checks whether the currently logged-in member has confirmed their email
     * 
     * Reads from the session, which is populated at login time. Returns
     * false when no member is logged in, or when the account exists but
     * hasn't been confirmed yet.
     * 
     * @return boolean
     */
    public function isConfirmed(): bool
    {
        $member = $this->sessionBag->get('member');

        return !empty($member['confirmed']) && $member['confirmed'] == '1';
    }

    /**
     * Authenticates a member by identifier (email or login) and password
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
            $translation = $this->mapper->fetchTranslation($row['id']);

            $this->sessionBag->set('member', [
                'id' => $row['id'],
                'name' => $translation ? $translation['name'] : null,
                'email' => $row['email'],
                'login' => $row['login'],
                'confirmed' => (bool) $row['confirmed']
            ]);

            return true;
        }

        return false;
    }

    /**
     * Logs out the current member
     * 
     * @return void
     */
    public function logout()
    {
        $this->sessionBag->remove('member');
    }

    /**
     * Checks whether a member is logged in
     * 
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->sessionBag->has('member');
    }

    /**
     * Retrieves session data for the logged-in member
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
     * Generates and stores a password recovery key for an email
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

        $key = $this->generateActivationKey();
        $affected = $this->mapper->updateKey($email, $key);

        return $affected ? $key : false;
    }

    /**
     * Resets a member's password using an activation key
     * 
     * @param string $key
     * @return string|false
     */
    public function resetPassword($key)
    {
        $row = $this->mapper->findByActivationKey($key);

        if (!$row) {
            return false;
        }

        $newPassword = substr(md5(uniqid(rand(), true)), 0, 8);
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $affected = $this->mapper->updatePasswordByKey($key, $hash);

        if (!$affected) {
            return false;
        }

        $this->mapper->touchPasswordChangedAt($row['id'], date('Y-m-d H:i:s'));

        return $newPassword;
    }

    /**
     * Confirms a member account by activation key
     * 
     * @param string $key
     * @return bool
     */
    public function confirm($key): bool
    {
        return (bool) $this->mapper->confirm($key);
    }

    /**
     * Checks if an email is available for registration
     * 
     * @param string $email
     * @return bool
     */
    public function isFree($email): bool
    {
        return $this->mapper->isFree($email);
    }

    /**
     * Gets the last inserted record ID
     * 
     * @return int
     */
    public function getLastId()
    {
        return $this->mapper->getLastId();
    }

    /**
     * Gets the paginator instance
     * 
     * @return mixed
     */
    public function getPaginator()
    {
        return $this->mapper->getPaginator();
    }
}