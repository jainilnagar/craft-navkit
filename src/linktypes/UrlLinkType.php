<?php

namespace jainilnagar\navkit\linktypes;

use Craft;
use craft\helpers\App;
use craft\helpers\Cp;
use jainilnagar\navkit\base\LinkType;
use jainilnagar\navkit\elements\Node;

/**
 * A literal URL (absolute, root-relative, mailto:, tel:, or an env/alias).
 */
class UrlLinkType extends LinkType
{
    public static function id(): string
    {
        return 'url';
    }

    public static function displayName(): string
    {
        return Craft::t('navkit', 'URL');
    }

    public function getUrl(Node $node): ?string
    {
        if (!$node->url) {
            return null;
        }
        // Resolves $VAR env references and @aliases; leaves plain URLs untouched.
        return App::parseEnv($node->url);
    }

    public function getInputHtml(Node $node, bool $static): string
    {
        return Cp::textHtml([
            'id' => 'navkit-url',
            'name' => 'url',
            'value' => $node->url,
            'placeholder' => 'https://…',
            'disabled' => $static,
        ]);
    }
}
