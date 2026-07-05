<?php

namespace jainilnagar\navkit\models;

use craft\base\Model;

/**
 * Navkit settings.
 *
 * Kept intentionally small. Menu definitions live in their own
 * project-config namespace (navkit.menus.*), not here.
 */
class Settings extends Model
{
    /**
     * @var bool Whether rendered navigation output should be cached by default.
     */
    public bool $enableRenderCache = true;

    /**
     * @var string|null Default CSS class applied to the <ul> wrapper by render().
     */
    public ?string $defaultMenuClass = null;

    public function defineRules(): array
    {
        return [
            [['enableRenderCache'], 'boolean'],
            [['defaultMenuClass'], 'string'],
        ];
    }
}
