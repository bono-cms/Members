<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members;

use Members\Service\SiteService;
use Members\Service\MemberManager;
use Cms\AbstractCmsModule;
use Krystal\Image\Tool\ImageManager;

final class Module extends AbstractCmsModule
{
    /**
     * {@inheritDoc}
     */
    public function getServiceProviders()
    {
        $memberManager = new MemberManager(
            $this->getMapper('/Members/Storage/MySQL/MemberMapper'),
            $this->getServiceLocator()->get('sessionBag'),
            $this->createAvatarImageManager()
        );

        return [
            'memberManager' => $memberManager,
            'siteService' => new SiteService($memberManager)
        ];
    }

    /**
     * Builds avatar image manager service
     * 
     * @return \Krystal\Image\Tool\ImageManager
     */
    private function createAvatarImageManager()
    {
        $plugins = [
            'thumb' => [
                'quality' => 75,
                'dimensions' => [
                    // Admin grid preview
                    [64, 64],

                    // Site render sizes
                    [128, 128],
                    [256, 256],
                ]
            ]
        ];

        return new ImageManager(
            '/data/uploads/module/members/avatars/',
            $this->appConfig->getRootDir(),
            $this->appConfig->getRootUrl(),
            $plugins
        );
    }
}