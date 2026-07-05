<?php

namespace jainilnagar\navkit\services;

use Craft;
use jainilnagar\navkit\elements\Node;
use jainilnagar\navkit\models\Menu;
use jainilnagar\navkit\Navkit;
use yii\base\Component;

/**
 * Nodes service.
 *
 * Thin helpers around node retrieval. Rendering (the Twig render() one-liner and
 * caching) lives in the Render service. Kept minimal on purpose.
 */
class Nodes extends Component
{
    /**
     * Returns the top-level nodes for a menu (in structure order), for the given
     * site. Descendants are eager-loaded for cheap tree rendering downstream.
     *
     * @return Node[]
     */
    public function getNodesForMenu(Menu|string $menu, ?int $siteId = null): array
    {
        if (is_string($menu)) {
            $resolved = Navkit::getInstance()->menus->getMenuByHandle($menu);
            if ($resolved === null) {
                return [];
            }
            $menu = $resolved;
        }

        $siteId ??= Craft::$app->getSites()->getCurrentSite()->id;

        /** @var Node[] $nodes */
        $nodes = Node::find()
            ->menuId($menu->id)
            ->siteId($siteId)
            ->status(Node::STATUS_ENABLED)
            ->level(1)
            ->with(['descendants'])
            ->all();

        return $nodes;
    }

    public function getNodeById(int $id, ?int $siteId = null): ?Node
    {
        /** @var Node|null $node */
        $node = Node::find()
            ->id($id)
            ->siteId($siteId)
            ->status(null)
            ->one();

        return $node;
    }
}
