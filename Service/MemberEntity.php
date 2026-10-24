<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

use Krystal\Stdlib\VirtualEntity;
use Krystal\Iso\ISO3166\Country;

final class MemberEntity extends VirtualEntity
{
    const AVATAR_THUMB   = '64x64';
    const AVATAR_SMALL   = '256x256';
    const AVATAR_PROFILE = '256x256';

    /**
     * Returns avatar URL for a given size
     *
     * @param string $size e.g. "256x256", or one of the AVATAR_* constants
     * @return string|null
     */
    public function getAvatarUrl($size = self::AVATAR_PROFILE)
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

    /**
     * Returns the human-readable name of the member's country.
     *
     * Resolves the stored ISO 3166-1 alpha-2 code against the
     * framework's country map. Returns null when the member has
     * no country set, or when the stored code is not a known
     * ISO 3166-1 code (e.g. a stale or hand-edited value).
     *
     * @return string|null
     */
    public function getCountryName()
    {
        $code = $this->getCountry();

        if ($code === null || $code === '') {
            return null;
        }

        return (new Country())->getCountryByCode(strtolower((string) $code));
    }
}