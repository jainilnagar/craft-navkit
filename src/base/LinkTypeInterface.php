<?php

namespace jainilnagar\navkit\base;

use craft\base\ElementInterface;
use jainilnagar\navkit\elements\Node;

/**
 * Contract implemented by every Navkit link type.
 *
 * A link type knows how to (a) present an input for choosing its target in the
 * node editor, and (b) resolve a stored node into a live URL at render time.
 * Register custom types via Navkit::getInstance()->linkTypes EVENT_REGISTER_LINK_TYPES.
 */
interface LinkTypeInterface
{
    /** Stable handle stored in navkit_nodes.type (e.g. "entry", "url"). */
    public static function id(): string;

    /** Human-readable label for the type selector. */
    public static function displayName(): string;

    /** Whether this type can be used in the current install (e.g. Commerce present). */
    public static function isAvailable(): bool;

    /** Whether the target is a Craft element (vs. a raw URL or no link). */
    public function isElementType(): bool;

    /** The linked element, resolved fresh, or null for non-element / unset links. */
    public function getElement(Node $node): ?ElementInterface;

    /** The resolved URL for this node, computed at call time so it tracks slug changes. */
    public function getUrl(Node $node): ?string;

    /** The target-selection input rendered inside the node editor for this type. */
    public function getInputHtml(Node $node, bool $static): string;
}
