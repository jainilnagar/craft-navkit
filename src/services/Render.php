<?php

namespace jainilnagar\navkit\services;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\Template;
use craft\web\View;
use jainilnagar\navkit\elements\Node;
use jainilnagar\navkit\models\Menu;
use jainilnagar\navkit\Navkit;
use Twig\Markup;
use yii\base\Component;
use yii\caching\TagDependency;

/**
 * Front-end rendering for menus.
 *
 * render() resolves a menu into a nested tree (URLs resolved live via each
 * node's link type), computes the active trail against the current request, and
 * renders an overridable Twig template. The resolved tree is cached per
 * menu+site; the active trail is computed per request, so caching stays
 * URL-independent.
 */
class Render extends Component
{
    public function render(Menu|string|null $menu, array $options = []): Markup
    {
        if (is_string($menu)) {
            $menu = Navkit::getInstance()->menus->getMenuByHandle($menu);
        }
        if (!$menu instanceof Menu) {
            return Template::raw('');
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $tree = $this->getTree($menu, $siteId);

        if (empty($tree)) {
            return Template::raw('');
        }

        $trail = $this->_activeIds($tree);

        $template = !empty($options['template']) ? $options['template'] : 'navkit/_nav/menu';

        $html = Craft::$app->getView()->renderTemplate($template, [
            'menu' => $menu,
            'nodes' => $tree,
            'options' => $options,
            'activeIds' => $trail['active'],
            'trailIds' => $trail['trail'],
        ], View::TEMPLATE_MODE_SITE);

        return Template::raw($html);
    }

    // --- Tree + cache --------------------------------------------------------

    public function getTree(Menu $menu, int $siteId): array
    {
        $cacheEnabled = (bool)(Navkit::getInstance()->getSettings()->enableRenderCache ?? true);
        $cache = Craft::$app->getCache();
        $key = "navkit:tree:{$menu->uid}:{$siteId}";

        if ($cacheEnabled) {
            $cached = $cache->get($key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $tree = $this->_resolveTree($menu, $siteId);

        if ($cacheEnabled) {
            $cache->set($key, $tree, null, new TagDependency([
                'tags' => ["navkit:menu:{$menu->id}"],
            ]));
        }

        return $tree;
    }

    private function _resolveTree(Menu $menu, int $siteId): array
    {
        /** @var Node[] $nodes */
        $nodes = Node::find()
            ->menuId($menu->id)
            ->siteId($siteId)
            ->status(null)
            ->all();

        $items = [];
        foreach ($nodes as $node) {
            $items[] = [
                'id' => (int)$node->id,
                'level' => (int)($node->level ?: 1),
                'enabled' => $node->getStatus() === Node::STATUS_ENABLED,
                'title' => (string)$node->title,
                'url' => $node->getLinkUrl(),
                'type' => $node->type,
                'target' => $node->target,
                'newWindow' => $node->getNewWindow(),
                'classes' => $node->classes,
                'rel' => $node->rel,
            ];
        }

        return $this->_prune($this->_buildTree($items));
    }

    /**
     * Turns a flat, structure-ordered list into a nested tree using each item's
     * `level`. Each item gains a `children` array.
     */
    private function _buildTree(array $items): array
    {
        $tree = [];
        $containers = [0 => &$tree];

        foreach ($items as $item) {
            $item['children'] = [];
            $level = max(1, (int)$item['level']);

            if (!isset($containers[$level - 1])) {
                $level = 1;
            }

            $container = &$containers[$level - 1];
            $container[] = $item;
            $lastKey = array_key_last($container);
            $containers[$level] = &$container[$lastKey]['children'];
            unset($container);

            foreach (array_keys($containers) as $l) {
                if ($l > $level) {
                    unset($containers[$l]);
                }
            }
        }

        return $tree;
    }

    // --- Active trail (per request) ------------------------------------------

    /**
     * @return array{active: int[], trail: int[]}
     */
    private function _activeIds(array $tree): array
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return ['active' => [], 'trail' => []];
        }

        $currentUrl = $request->getAbsoluteUrl();
        $active = [];
        $trail = [];

        $walk = function(array $items, array $ancestors) use (&$walk, &$active, &$trail, $currentUrl) {
            foreach ($items as $item) {
                if (!empty($item['url']) && $this->_isCurrent($item['url'], $currentUrl)) {
                    $active[] = $item['id'];
                    foreach ($ancestors as $ancestorId) {
                        $trail[] = $ancestorId;
                    }
                }
                if (!empty($item['children'])) {
                    $walk($item['children'], array_merge($ancestors, [$item['id']]));
                }
            }
        };
        $walk($tree, []);

        $active = array_values(array_unique($active));
        $trail = array_values(array_unique(array_merge($active, $trail)));

        return ['active' => $active, 'trail' => $trail];
    }

    private function _isCurrent(string $nodeUrl, string $currentUrl): bool
    {
        $a = rtrim((string)parse_url($nodeUrl, PHP_URL_PATH), '/');
        $b = rtrim((string)parse_url($currentUrl, PHP_URL_PATH), '/');
        return $a === $b;
    }

    private function _prune(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (empty($item['enabled'])) {
                continue; // disabled node → skip it AND its whole subtree
            }
            if (!empty($item['children'])) {
                $item['children'] = $this->_prune($item['children']);
            }
            unset($item['enabled']); // keep the cached tree shape clean
            $out[] = $item;
        }
        return $out;
    }

    // --- Cache invalidation --------------------------------------------------

    public function invalidateMenu(int $menuId): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), "navkit:menu:{$menuId}");
    }

    public function handleElementSave(ElementInterface $element): void
    {
        if ($element instanceof Node) {
            if ($element->menuId) {
                $this->invalidateMenu((int)$element->menuId);
            }
            return;
        }

        if ($element->getIsDraft() || $element->getIsRevision() || !$element->id) {
            return;
        }

        $menuIds = (new Query())
            ->select(['menuId'])
            ->distinct()
            ->from(['{{%navkit_nodes}}'])
            ->where(['linkedElementId' => $element->id])
            ->column();

        foreach ($menuIds as $menuId) {
            $this->invalidateMenu((int)$menuId);
        }
    }
}
