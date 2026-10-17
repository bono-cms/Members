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
 * The payload is self-sufficient: every field carries both its old and
 * new value, captured at submission time. This class never touches the
 * live member row, never produces HTML, and never translates strings.
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
     * State initialization
     *
     * @param array $payload Decoded submission payload
     * @return void
     */
    public function __construct(array $payload = [])
    {
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
     * @return array
     */
    public function baseChanges()
    {
        if (!$this->hasBaseChanges()) {
            return [];
        }

        return $this->buildList($this->payload['base']);
    }

    /**
     * Returns the list of translated-field changes, grouped by language id
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
            $result[(int) $langId] = $this->buildList($fields);
        }

        return $result;
    }

    /**
     * Builds a list of change descriptors from a raw field map
     *
     * Supports both the current payload shape (each value is an
     * ['old' => ..., 'new' => ...] pair) and the legacy shape
     * (each value is a bare new value). Legacy entries are treated
     * as one-sided changes with an empty old value.
     *
     * @param array $fields
     * @return array
     */
    private function buildList(array $fields)
    {
        $result = [];

        foreach ($fields as $field => $pair) {
            if (!is_array($pair) || !array_key_exists('new', $pair)) {
                $pair = ['old' => '', 'new' => $pair];
            }

            $result[] = $this->buildChange($field, $pair['old'], $pair['new']);
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