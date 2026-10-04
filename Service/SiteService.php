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
}
