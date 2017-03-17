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

final class SiteService implements SiteServiceInterface
{
    /**
     * Member manager service
     * 
     * @var \Members\Service\MemberManagerInterface
     */
    private $memberManager;

    /**
     * State initialization
     * 
     * @param \Members\Service\MemberManagerInterface $memberManager
     * @return void
     */
    public function __construct(MemberManagerInterface $memberManager)
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
