<?php

namespace jainilnagar\navkit\elements;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\actions\Delete;
use craft\elements\actions\SetStatus;
use craft\elements\User;
use craft\fieldlayoutelements\TitleField;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\web\CpScreenResponseBehavior;
use jainilnagar\navkit\base\LinkTypeInterface;
use jainilnagar\navkit\elements\db\NodeQuery;
use jainilnagar\navkit\models\Menu;
use jainilnagar\navkit\Navkit;
use jainilnagar\navkit\records\NodeRecord;
use yii\web\Response;

/**
 * Node element.
 *
 * A single item within a Menu's Structure: a title, a status, multi-site
 * content, and a link. The link is described by a {@see LinkTypeInterface}
 * (`type`) plus its stored target (`url` or `linkedElementId`); the URL is
 * resolved live via getLink() so it tracks the target's slug/URI.
 *
 * Per-node custom fields attach via the menu's field layout; the `data`
 * bucket is a general-purpose store for link-type and extension data.
 */
class Node extends Element
{
    public ?int $menuId = null;

    // --- Link columns ---
    public string $type = 'url';
    public ?string $url = null;
    public ?int $linkedElementId = null;
    public ?int $linkedSiteId = null;
    public ?string $target = null;
    public ?string $classes = null;
    public ?string $rel = null;

    private ?array $_data = null;

    private ?Menu $_menu = null;

    /** @var mixed Raw posted element selection, resolved to linkedElementId on save. */
    private mixed $_postedTargetElementIds = null;

    public static function displayName(): string
    {
        return Craft::t('navkit', 'Node');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('navkit', 'node');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('navkit', 'Nodes');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('navkit', 'nodes');
    }

    public static function refHandle(): ?string
    {
        return 'navkitNode';
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function find(): NodeQuery
    {
        return new NodeQuery(static::class);
    }

    // --- Menu / structure ----------------------------------------------------

    public function getMenu(): ?Menu
    {
        if ($this->_menu !== null) {
            return $this->_menu;
        }
        if ($this->menuId === null) {
            return null;
        }
        return $this->_menu = Navkit::getInstance()->menus->getMenuById($this->menuId);
    }

    public function setMenu(Menu $menu): void
    {
        $this->_menu = $menu;
        $this->menuId = $menu->id;
    }

    /**
     * General-purpose extensibility bucket. Decodes a
     * JSON string from the DB, or accepts an array directly.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->_data ?? [];
    }

    public function setData(mixed $value): void
    {
        if (is_string($value)) {
            $value = $value !== '' ? Json::decode($value) : null;
        }
        $this->_data = is_array($value) ? $value : null;
    }

    // --- Linking -------------------------------------------------------------

    public function getLinkType(): ?LinkTypeInterface
    {
        return Navkit::getInstance()->linkTypes->getLinkType($this->type);
    }

    /**
     * The linked element (entry/category/asset/product), or null for URL/passive.
     */
    public function getLinkedElement(): ?ElementInterface
    {
        return $this->getLinkType()?->getElement($this);
    }

    /**
     * The resolved link URL, computed live so it tracks the target's slug/URI.
     */
    public function getLinkUrl(): ?string
    {
        return $this->getLinkType()?->getUrl($this);
    }

    public function getNewWindow(): bool
    {
        return $this->target === '_blank';
    }

    /**
     * Selected target element id(s), for the editor's element-select input.
     *
     * @return int[]
     */
    public function getTargetElementIds(): array
    {
        return $this->linkedElementId ? [$this->linkedElementId] : [];
    }

    public function setTargetElementIds(mixed $value): void
    {
        // The actual mapping to linkedElementId happens in beforeSave(), once
        // $this->type is known (mass-assignment order isn't guaranteed).
        $this->_postedTargetElementIds = $value;
    }

    /**
     * Multi-site: a node is stored in exactly the sites its menu is enabled for.
     */
    public function getSupportedSites(): array
    {
        $menu = $this->getMenu();
        if ($menu === null) {
            return [Craft::$app->getSites()->getPrimarySite()->id];
        }

        $sites = [];
        foreach ($menu->siteSettings as $siteUid => $settings) {
            $site = Craft::$app->getSites()->getSiteByUid($siteUid);
            if ($site === null) {
                continue;
            }
            $sites[] = [
                'siteId' => $site->id,
                'enabledByDefault' => (bool)($settings['enabledByDefault'] ?? true),
            ];
        }

        return $sites ?: [Craft::$app->getSites()->getPrimarySite()->id];
    }

    // --- Field layout -------------------------------------------------------

    public function getFieldLayout(): ?FieldLayout
    {
        $menu = $this->getMenu();

        $fieldLayout = $menu?->getFieldLayout() ?? new FieldLayout([
            'type' => self::class,
        ]);

        $tabs = $fieldLayout->getTabs();

        if (empty($tabs)) {
            $fieldLayout->setTabs([
                new FieldLayoutTab([
                    'layout' => $fieldLayout,
                    'name' => Craft::t('navkit', 'Content'),
                    'elements' => [
                        new TitleField(),
                    ],
                ]),
            ]);
        } else {
            // Ensure the first tab has a Title field.
            $firstTab = $tabs[0];
            $elements = $firstTab->getElements();
            $hasTitle = false;
            foreach ($elements as $el) {
                if ($el instanceof TitleField) {
                    $hasTitle = true;
                    break;
                }
            }
            if (!$hasTitle) {
                array_unshift($elements, new TitleField());
                $firstTab->setElements($elements);
                $fieldLayout->setTabs($tabs);
            }
        }

        return $fieldLayout;
    }

    // --- Index ---------------------------------------------------------------

    protected static function defineSources(?string $context = null): array
    {
        $sources = [];

        foreach (Navkit::getInstance()->menus->getAllMenus() as $menu) {
            $sources[] = [
                'key' => "menu:{$menu->uid}",
                'label' => $menu->name,
                'sites' => array_keys($menu->siteSettings),
                'criteria' => [
                    'menuId' => $menu->id,
                    'siteId' => '*',
                ],
                'structureId' => $menu->structureId,
                'structureEditable' => true,
                'defaultSort' => ['structure', 'asc'],
                'data' => ['handle' => $menu->handle],
            ];
        }

        return $sources;
    }

    protected static function defineActions(?string $source = null): array
    {
        return [
            SetStatus::class,
            Delete::class,
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'type' => Craft::t('navkit', 'Type'),
            'link' => Craft::t('navkit', 'Link'),
            'dateUpdated' => Craft::t('app', 'Date Updated'),
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'type', 'link', 'dateUpdated'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'dateUpdated' => Craft::t('app', 'Date Updated'),
        ];
    }

    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'type':
                $linkType = $this->getLinkType();
                return Html::encode($linkType ? $linkType::displayName() : ucfirst($this->type));
            case 'link':
                $url = $this->getLinkUrl();
                if ($url) {
                    return Html::a(Html::encode($url), $url, ['target' => '_blank', 'rel' => 'noopener']);
                }
                $element = $this->getLinkedElement();
                return $element ? Html::encode($element->getUiLabel()) : '—';
        }

        return parent::attributeHtml($attribute);
    }

    // --- Permissions ---------------------------------------------------------

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        return $user->can('navkit:manageNodes');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        return $user->can('navkit:manageNodes');
    }

    public function canDelete(User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }
        return $user->can('navkit:manageNodes');
    }

    public function canCreateDrafts(User $user): bool
    {
        return false;
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('navkit/nodes/' . $this->id);
    }

    public function getPostEditUrl(): ?string
    {
        $menu = $this->getMenu();
        return UrlHelper::cpUrl('navkit/nodes', $menu ? ['source' => "menu:{$menu->uid}"] : []);
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        $menu = $this->getMenu();
        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => Craft::t('navkit', 'Menus'),
                'url' => UrlHelper::cpUrl('navkit/nodes'),
            ],
            [
                'label' => $menu?->name ?? Craft::t('navkit', 'Node'),
                'url' => UrlHelper::cpUrl('navkit/nodes', ['source' => "menu:{$menu?->uid}"]),
            ],
        ]);
    }

    public function isCurrent(): bool
    {
        $url = $this->getLinkUrl();
        if (!$url) {
            return false;
        }
        $a = rtrim((string)parse_url($url, PHP_URL_PATH), '/');
        $b = rtrim((string)parse_url(Craft::$app->getRequest()->getAbsoluteUrl(), PHP_URL_PATH), '/');
        return $a === $b;
    }

    public function isActive(): bool
    {
        if ($this->isCurrent()) {
            return true;
        }
        foreach ($this->getDescendants()->all() as $descendant) {
            if ($descendant instanceof self && $descendant->isCurrent()) {
                return true;
            }
        }
        return false;
    }

    // --- Persistence ---------------------------------------------------------

    public function beforeSave(bool $isNew): bool
    {
        $linkType = $this->getLinkType();

        // Resolve the posted element selection now that $this->type is settled.
        if ($this->_postedTargetElementIds !== null && $linkType?->isElementType()) {
            $value = $this->_postedTargetElementIds;
            if (is_array($value) && array_key_exists($this->type, $value)) {
                $value = $value[$this->type];
            }
            $id = is_array($value) ? reset($value) : $value;
            $this->linkedElementId = $id ? (int)$id : null;
        }

        // Keep the stored value coherent with the chosen type.
        if (!$linkType || !$linkType->isElementType()) {
            $this->linkedElementId = null;
            $this->linkedSiteId = null;
        } else {
            $this->linkedSiteId = $this->linkedSiteId ?: $this->siteId;
            $this->url = null;
        }

        if ($this->type !== 'url') {
            $this->url = null;
        }

        return parent::beforeSave($isNew);
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $data = [
                'menuId' => $this->menuId,
                'type' => $this->type,
                'url' => $this->url,
                'linkedElementId' => $this->linkedElementId,
                'linkedSiteId' => $this->linkedSiteId,
                'target' => $this->target,
                'classes' => $this->classes,
                'rel' => $this->rel,
                'data' => $this->getData() ? Json::encode($this->getData()) : null,
            ];

            Db::upsert(NodeRecord::tableName(), ['id' => $this->id] + $data, $data);

            // Place the node into its menu's Structure on first save, if it
            // isn't already positioned (e.g. created via `elements/create`).
            $menu = $this->getMenu();
            if ($isNew && $menu?->structureId && !$this->root) {
                Craft::$app->getStructures()->appendToRoot($menu->structureId, $this);
            }
        }

        parent::afterSave($isNew);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['menuId', 'linkedElementId', 'linkedSiteId'], 'integer'];
        $rules[] = [['type'], 'string'];
        $rules[] = [['type'], 'default', 'value' => 'url'];
        $rules[] = [['menuId'], 'required'];
        // Mass-assignable from the editor (elements/save):
        $rules[] = [['type', 'url', 'target', 'classes', 'rel', 'targetElementIds'], 'safe'];
        $rules[] = [['url'], 'required',
            'when' => fn(self $node) => $node->type === 'url',
            'except' => self::SCENARIO_ESSENTIALS,
            'message' => Craft::t('navkit', 'Enter a URL.'),
        ];
        $rules[] = [['linkedElementId'], 'required',
            'when' => fn(self $node) => $this->getLinkType()?->isElementType(),
            'except' => self::SCENARIO_ESSENTIALS,
            'message' => Craft::t('navkit', 'Select a target for this link.'),
        ];
        return $rules;
    }

    public function afterValidate(): void
    {
        if ($this->hasErrors('linkedElementId')) {
            foreach ($this->getErrors('linkedElementId') as $error) {
                $this->addError('targetElementIds', $error);
            }
            $this->clearErrors('linkedElementId');
        }

        parent::afterValidate();
    }
}
