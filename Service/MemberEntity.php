<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

use Krystal\Stdlib\VirtualEntity;

final class MemberEntity extends VirtualEntity
{
    /**
     * Returns avatar URL for a given size
     * 
     * @param string $size e.g. "256x256"
     * @return string|null
     */
    public function getAvatarUrl($size = '256x256')
    {
        if (!$this->hasAvatar()) {
            return null;
        }

        $bag = $this->getAvatarBag();

        if (!$bag) {
            return null;
        }

        return $bag->getUrl($size);
    }

    /**
     * Tells whether the member has an avatar
     * 
     * @return boolean
     */
    public function hasAvatar()
    {
        $avatar = $this->getAvatar();

        return $avatar !== null && $avatar !== '';
    }

    /**
     * Returns the member's display name
     *
     * Prefers the name column, falls back to the login, and finally
     * to a numbered placeholder. The placeholder covers legacy rows
     * that have neither name nor login set.
     *
     * @return string
     */
    public function getDisplayName()
    {
        $name = $this->getName();

        if ($name !== null && $name !== '') {
            return (string) $name;
        }

        $login = $this->getLogin();

        if ($login !== null && $login !== '') {
            return (string) $login;
        }

        return '#' . (int) $this->getId();
    }
}