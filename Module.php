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
use Members\Service\ProfileChangeService;
use Members\Service\ChatService;
use Members\Service\TagGroupService;
use Members\Service\TagService;
use Members\Service\MemberTagService;
use Cms\AbstractCmsModule;
use Krystal\Image\Tool\ImageManager;

final class Module extends AbstractCmsModule
{
    /**
     * {@inheritDoc}
     */
    public function getServiceProviders()
    {
        $memberMapper = $this->getMapper('/Members/Storage/MySQL/MemberMapper');
        $changeMapper = $this->getMapper('/Members/Storage/MySQL/MemberChangeMapper');
        $avatarManager = $this->createAvatarImageManager();

        $memberManager = new MemberManager(
            $memberMapper,
            $this->getServiceLocator()->get('sessionBag'),
            $avatarManager
        );

        $config = $this->getConfig();
        $moderationConfig = isset($config['moderation']) ? $config['moderation'] : [];

        return [
            'memberManager' => $memberManager,
            'siteService' => new SiteService($memberManager),
            'profileChangeService' => new ProfileChangeService(
                $memberMapper,
                $changeMapper,
                $avatarManager,
                $moderationConfig
            ),
            'chatService' => new ChatService(
                $this->getMapper('/Members/Storage/MySQL/ConversationMapper'),
                $this->getMapper('/Members/Storage/MySQL/ParticipantMapper'),
                $this->getMapper('/Members/Storage/MySQL/MessageMapper'),
                $memberManager
            ),
            'tagGroupService' => new TagGroupService(
                $this->getMapper('/Members/Storage/MySQL/TagGroupMapper')
            ),
            'tagService' => new TagService(
                $this->getMapper('/Members/Storage/MySQL/TagMapper')
            ),
            'memberTagService' => new MemberTagService(
                $this->getMapper('/Members/Storage/MySQL/MemberTagMapper'),
                $this->getMapper('/Members/Storage/MySQL/TagGroupMapper'),
                $this->getMapper('/Members/Storage/MySQL/TagMapper')
            )
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
                    [64, 64],
                    [128, 128],
                    [256, 256]
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