<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Storage\MySQL;

use Cms\Storage\MySQL\AbstractStorageDropper;

final class Dropper extends AbstractStorageDropper
{
    /**
     * {@inheritDoc}
     */
    protected function getTables()
    {
        return [
            MemberTagMapper::getTableName(),
            TagMapper::getTableName(),
            TagTranslationMapper::getTableName(),
            TagGroupMapper::getTableName(),
            TagGroupTranslationMapper::getTableName(),
            MessageMapper::getTableName(),
            ParticipantMapper::getTableName(),
            ConversationMapper::getTableName(),
            MemberChangeMapper::getTableName(),
            MemberTranslationMapper::getTableName(),
            MemberMapper::getTableName(),
        ];
    }
}