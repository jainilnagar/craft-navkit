<?php

namespace jainilnagar\navkit\base;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Cp;
use jainilnagar\navkit\elements\Node;

/**
 * Base for link types whose target is a Craft element (entry, category, asset,
 * Commerce product, …). Subclasses only need to declare the element class.
 *
 * The URL is resolved live from the element every time, so renaming/moving the
 * target updates the navigation automatically — the core advantage over a
 * hand-rolled Matrix menu that stores a static URL string.
 */
abstract class ElementLinkType extends LinkType
{
    /** @var array<string, ElementInterface|null> Per-request resolution cache. */
    private static array $_elementCache = [];

    /** Fully-qualified element class this type links to. */
    abstract public static function elementType(): string;

    public static function isAvailable(): bool
    {
        return class_exists(static::elementType());
    }

    public function isElementType(): bool
    {
        return true;
    }

    public function getElement(Node $node): ?ElementInterface
    {
        if (!$node->linkedElementId) {
            return null;
        }

        $siteId = $node->linkedSiteId ?: $node->siteId;
        $key = static::id() . ':' . $node->linkedElementId . ':' . $siteId;

        if (!array_key_exists($key, self::$_elementCache)) {
            /** @var class-string<ElementInterface> $elementType */
            $elementType = static::elementType();
            self::$_elementCache[$key] = $elementType::find()
                ->id($node->linkedElementId)
                ->siteId($siteId)
                ->status(null)
                ->one();
        }

        return self::$_elementCache[$key];
    }

    public function getUrl(Node $node): ?string
    {
        return $this->getElement($node)?->getUrl();
    }

    public function getInputHtml(Node $node, bool $static): string
    {
        return Cp::elementSelectHtml([
            'id' => 'navkit-target-' . static::id(),
            'name' => 'targetElementIds[' . static::id() . ']',
            'elementType' => static::elementType(),
            'elements' => array_filter([$this->getElement($node)]),
            'single' => true,
            'limit' => 1,
            'disabled' => $static,
        ]);
    }
}
