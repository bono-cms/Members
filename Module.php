<?php

/**
 * This file is part of the Bono CMS
 * 
 * Copyright (c) No Global State Lab
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members;

use Members\Service\SiteService;
use Members\Service\MemberManager;
use Cms\AbstractCmsModule;

final class Module extends AbstractCmsModule
{
    /**
     * {@inheritDoc}
     */
    public function getServiceProviders()
    {
        $memberManager = new MemberManager(
            $this->getMapper('/Members/Storage/MySQL/MemberMapper'), 
            $this->getServiceLocator()->get('sessionBag')
        );

        return array(
            'memberManager' => $memberManager,
            'siteService' => new SiteService($memberManager)
        );
    }
}
