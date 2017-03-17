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

interface SiteServiceInterface
{
    /**
     * Checks whether user is logged in
     * 
     * @return boolean
     */
    public function isLoggedIn();

    /**
     * Returns member data
     * 
     * @param string $key
     * @return array
     */
    public function getMember($key = null);
}
