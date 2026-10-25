<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

/**
 * Normalizes client payloads into the shape the mappers expect.
 *
 * Kept separate from MemberManager so the rules can be unit
 * tested without standing up a mapper, and so controllers can
 * share them without inheriting the entire member service.
 */
final class InputNormalizer
{
    /**
     * Language manager used to resolve language codes to ids
     *
     * @var \Cms\Service\LanguageManagerInterface
     */
    private $languageManager;

    /**
     * State initialization
     *
     * @param \Cms\Service\LanguageManagerInterface $languageManager
     * @return void
     */
    public function __construct($languageManager)
    {
        $this->languageManager = $languageManager;
    }

    /**
     * Normalizes a translation block posted by a client.
     *
     * Accepts keys by language id ("1") or code ("en"), injects
     * "lang_id" on rows that lack it, and drops rows that cannot
     * be resolved to a valid language.
     *
     * When $onlyLangId is provided, only that language's row is
     * kept. Pass null to keep all languages.
     *
     * @param array $translations
     * @param int|null $onlyLangId
     * @return array<int, array>
     */
    public function normalizeTranslations(array $translations, $onlyLangId = null)
    {
        $normalized = [];

        foreach ($translations as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $langId = is_numeric($key)
                ? (int) $key
                : (int) $this->languageManager->fetchIdByCode((string) $key);

            if ($langId <= 0) {
                continue;
            }

            if ($onlyLangId !== null && $langId !== (int) $onlyLangId) {
                continue;
            }

            if (!isset($row['lang_id'])) {
                $row['lang_id'] = $langId;
            }

            $normalized[$langId] = $row;
        }

        return $normalized;
    }

    /**
     * Resolves the display name from a registration payload.
     *
     * @param array $input
     * @return string|null
     */
    public function resolveRegistrationName(array $input)
    {
        foreach ((array) ($input['data']['translation'] ?? []) as $row) {
            if (is_array($row) && !empty($row['name'])) {
                return (string) $row['name'];
            }
        }

        if (!empty($input['data']['member']['name'])) {
            return (string) $input['data']['member']['name'];
        }

        return null;
    }
}