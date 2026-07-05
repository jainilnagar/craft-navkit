<?php

namespace jainilnagar\navkit\linktypes;

use Craft;
use craft\elements\Entry;
use jainilnagar\navkit\base\ElementLinkType;

class EntryLinkType extends ElementLinkType
{
    public static function id(): string
    {
        return 'entry';
    }

    public static function displayName(): string
    {
        return Craft::t('navkit', 'Entry');
    }

    public static function elementType(): string
    {
        return Entry::class;
    }
}
