<?php

namespace jainilnagar\navkit\linktypes;

use Craft;
use craft\elements\Asset;
use jainilnagar\navkit\base\ElementLinkType;

class AssetLinkType extends ElementLinkType
{
    public static function id(): string
    {
        return 'asset';
    }

    public static function displayName(): string
    {
        return Craft::t('navkit', 'Asset');
    }

    public static function elementType(): string
    {
        return Asset::class;
    }
}
