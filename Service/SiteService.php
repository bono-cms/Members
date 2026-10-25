<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

final class SiteService
{
    /**
     * Member manager service
     *
     * @var \Members\Service\MemberManager
     */
    private $memberManager;

    /**
     * State initialization
     *
     * @param \Members\Service\MemberManager $memberManager
     * @return void
     */
    public function __construct(MemberManager $memberManager)
    {
        $this->memberManager = $memberManager;
    }

    /**
     * Checks whether the currently logged-in member has confirmed their email
     *
     * A shortcut to the same check on the member manager. Available in
     * templates via the global $member variable, so themes can gate
     * features on the confirmation state without reaching for services.
     *
     * @return boolean
     */
    public function isConfirmed()
    {
        return $this->memberManager->isConfirmed();
    }

    /**
     * Checks whether user is logged in
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
        return $this->memberManager->isLoggedIn();
    }

    /**
     * Returns member data
     *
     * @param string $key
     * @return array
     */
    public function getMember($key = null)
    {
        return $this->memberManager->getMember($key);
    }

    /**
     * Fetches a member by id with the translation for the current
     * language attached.
     *
     * Falls back to the base row (name/title/bio = null) when the
     * member has no translation for the active language, so callers
     * always get an entity for a member that exists. Use
     * MemberEntity::getDisplayName() to render a name — it falls
     * back to email, then to a placeholder.
     *
     * @param int $id Member id
     * @return \Members\Service\MemberEntity|boolean
     */
    public function fetchMemberById($id)
    {
        return $this->memberManager->fetchByIdForCurrentLang((int) $id);
    }
}