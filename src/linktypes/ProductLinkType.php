<?php

namespace jainilnagar\navkit\linktypes;

use Craft;
use jainilnagar\navkit\base\ElementLinkType;

/**
 * Links to a Craft Commerce product. Only available when Commerce is installed —
 * the class is referenced by string so this file never hard-depends on Commerce.
 */
class ProductLinkType extends ElementLinkType
{
    public static function id(): string
    {
        return 'product';
    }

    public static function displayName(): string
    {
        return Craft::t('navkit', 'Product');
    }

    public static function elementType(): string
    {
        return 'craft\\commerce\\elements\\Product';
    }
}
