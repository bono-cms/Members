<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

/**
 * Extracts structured change data from a pending submission
 *
 * This class never produces HTML. It turns a submission row (with its
 * decoded payload and the live MemberEntity) into plain arrays that a
 * view can iterate over. Consumers decide how to render each change.
 */
final class ChangesDiff
{
    /**
     * Decoded submission payload
     *
     * @var array
     */
    private $payload = [];

    /**
     * Live member entity, or null when the member was removed
     *
     * @var \Members\Service\MemberEntity|null
     */
    private $live;

    /**
     * State initialization
     *
     * @param \Members\Service\MemberEntity|null $live
     * @param array $payload Decoded submission payload
     * @return void
     */
    public function __construct(MemberEntity $live = null, array $payload = [])
    {
        $this->live = $live;
        $this->payload = $payload;
    }

    /**
     * Determines whether the payload has any base-field changes
     *
     * @return boolean
     */
    public function hasBaseChanges()
    {
        return !empty($this->payload['base']);
    }

    /**
     * Determines whether the payload has any translated-field changes
     *
     * @return boolean
     */
    public function hasTranslationChanges()
    {
        return !empty($this->payload['translations']);
    }

    /**
     * Returns the list of base-field changes
     *
     * Each entry has the shape:
     *   [
     *       'field' => string,
     *       'old' => mixed,
     *       'new' => mixed,
     *       'oldEmpty' => bool,
     *       'newEmpty' => bool,
     *       'unchanged' => bool
     *   ]
     *
     * @return array
     */
    public function baseChanges()
    {
        if (!$this->hasBaseChanges()) {
            return [];
        }

        $result = [];

        foreach ($this->payload['base'] as $field => $newValue) {
            $oldValue = ($this->live !== null && $this->live->has($field))
                ? $this->live->get($field)
                : '';

            $result[] = $this->buildChange($field, $oldValue, $newValue);
        }

        return $result;
    }

    /**
     * Returns the list of translated-field changes, grouped by language id
     *
     * Each entry has the shape:
     *   [
     *       langId => [
     *           [ 'field' => string, 'old' => mixed, 'new' => mixed, ... ],
     *           ...
     *       ],
     *       ...
     *   ]
     *
     * Live translation values are not available here because the
     * submission row does not carry them; only the staged values are
     * present. Consumers that need a full diff must fetch live translations
     * themselves. Use the 'old' key as-is.
     *
     * @return array
     */
    public function translationChanges()
    {
        if (!$this->hasTranslationChanges()) {
            return [];
        }

        $result = [];

        foreach ($this->payload['translations'] as $langId => $fields) {
            $result[(int) $langId] = [];

            foreach ($fields as $field => $newValue) {
                $result[(int) $langId][] = $this->buildChange($field, '', $newValue);
            }
        }

        return $result;
    }

    /**
     * Builds a single change descriptor
     *
     * @param string $field
     * @param mixed $old
     * @param mixed $new
     * @return array
     */
    private function buildChange($field, $old, $new)
    {
        return [
            'field' => $field,
            'old' => $old,
            'new' => $new,
            'oldEmpty' => $this->isEmpty($old),
            'newEmpty' => $this->isEmpty($new),
            'unchanged' => $this->stringify($old) === $this->stringify($new)
        ];
    }

    /**
     * Determines whether a value should be treated as empty for diffing
     *
     * Numeric zero is not considered empty: a change from any value to
     * "0" is a real change that must be shown.
     *
     * @param mixed $value
     * @return boolean
     */
    private function isEmpty($value)
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Normalizes a value for comparison purposes
     *
     * @param mixed $value
     * @return string
     */
    private function stringify($value)
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return is_array($value) ? 'array' : get_class($value);
    }
}