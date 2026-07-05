<?php

namespace jainilnagar\navkit\base;

use craft\base\ElementInterface;
use jainilnagar\navkit\elements\Node;

/**
 * Base class for link types. Concrete non-element types (URL, passive) extend
 * this directly; element-backed types extend {@see ElementLinkType}.
 */
abstract class LinkType implements LinkTypeInterface
{
    public static function isAvailable(): bool
    {
        return true;
    }

    public function isElementType(): bool
    {
        return false;
    }

    public function getElement(Node $node): ?ElementInterface
    {
        return null;
    }

    public function getInputHtml(Node $node, bool $static): string
    {
        return '';
    }
}
