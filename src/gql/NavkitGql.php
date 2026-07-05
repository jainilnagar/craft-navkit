<?php

namespace jainilnagar\navkit\gql;

use Craft;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use jainilnagar\navkit\Navkit;

/**
 * GraphQL support for Navkit.
 *
 * Exposes a single `navkitMenu(handle)` query that returns a menu as a nested
 * tree of nodes with live-resolved URLs. It reuses the Render service's cached
 * tree, so GraphQL and Twig rendering share the same resolution and caching.
 *
 * The node type is a plain object type (the tree is already resolved to arrays),
 * which keeps the schema small and avoids per-request element hydration.
 */
class NavkitGql
{
    private static ?ObjectType $nodeType = null;

    /**
     * Query definitions, merged into the schema via Gql::EVENT_REGISTER_GQL_QUERIES.
     */
    public static function getQueries(): array
    {
        return [
            'navkitMenu' => [
                'type' => Type::listOf(self::nodeType()),
                'args' => [
                    'handle' => [
                        'name' => 'handle',
                        'type' => Type::nonNull(Type::string()),
                        'description' => 'The handle of the menu to return.',
                    ],
                    'site' => [
                        'name' => 'site',
                        'type' => Type::string(),
                        'description' => 'The handle of the site to resolve nodes for.',
                    ],
                    'siteId' => [
                        'name' => 'siteId',
                        'type' => Type::int(),
                        'description' => 'The ID of the site to resolve nodes for.',
                    ],
                ],
                'description' => 'Returns a Navkit menu as a nested tree of nodes.',
                'resolve' => static function($source, array $arguments): array {
                    return self::resolveMenu($arguments);
                },
            ],
        ];
    }

    private static function resolveMenu(array $arguments): array
    {
        $menu = Navkit::getInstance()->menus->getMenuByHandle($arguments['handle']);
        if ($menu === null) {
            return [];
        }

        $sites = Craft::$app->getSites();
        $siteId = $arguments['siteId']
            ?? (isset($arguments['site']) ? $sites->getSiteByHandle($arguments['site'])?->id : null)
            ?? $sites->getCurrentSite()->id;

        return Navkit::getInstance()->render->getTree($menu, (int)$siteId);
    }

    /**
     * The (self-referential) node object type. Fields map directly to the keys
     * produced by Render's tree builder, so the default array resolver applies.
     */
    private static function nodeType(): ObjectType
    {
        if (self::$nodeType !== null) {
            return self::$nodeType;
        }

        self::$nodeType = new ObjectType([
            'name' => 'NavkitNode',
            'description' => 'A single node within a Navkit menu.',
            'fields' => static fn(): array => [
                'id' => ['type' => Type::int()],
                'title' => ['type' => Type::string()],
                'nodeUrl' => [
                    'type' => Type::string(),
                    'description' => 'The resolved destination URL (null for passive nodes).',
                    'resolve' => static fn(array $node) => $node['url'] ?? null,
                ],
                'nodeType' => [
                    'type' => Type::string(),
                    'description' => 'The link type handle (url, entry, category, asset, product, passive).',
                    'resolve' => static fn(array $node) => $node['type'] ?? null,
                ],
                'target' => ['type' => Type::string()],
                'classes' => ['type' => Type::string()],
                'rel' => ['type' => Type::string()],
                'newWindow' => ['type' => Type::boolean()],
                'level' => ['type' => Type::int()],
                'children' => [
                    'type' => Type::listOf(self::nodeType()),
                    'description' => 'Child nodes.',
                    'resolve' => static fn(array $node) => $node['children'] ?? [],
                ],
            ],
        ]);

        return self::$nodeType;
    }
}
