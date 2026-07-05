<?php

namespace jainilnagar\navkit\linktypes;

use Craft;
use craft\elements\Category;
use jainilnagar\navkit\base\ElementLinkType;

class CategoryLinkType extends ElementLinkType
{
    public static function id(): string
    {
        return 'category';
    }

    public static function displayName(): string
    {
        return Craft::t('navkit', 'Category');
    }

    public static function elementType(): string
    {
        return Category::class;
    }
}
