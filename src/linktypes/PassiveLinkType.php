<?php

namespace jainilnagar\navkit\linktypes;

use Craft;
use jainilnagar\navkit\base\LinkType;
use jainilnagar\navkit\elements\Node;

/**
 * A node that renders as plain text with no href — e.g. a non-clickable parent
 * or a section heading inside a dropdown.
 */
class PassiveLinkType extends LinkType
{
    public static function id(): string
    {
        return 'passive';
    }

    public static function displayName(): string
    {
        return Craft::t('navkit', 'Passive (no link)');
    }

    public function getUrl(Node $node): ?string
    {
        return null;
    }
}
