<?php

namespace jainilnagar\navkit\models;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\enums\PropagationMethod;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use jainilnagar\navkit\elements\Node;
use jainilnagar\navkit\records\MenuRecord;

/**
 * Menu model.
 *
 * A Menu is a *definition* (settings) — it is stored in project config and
 * mirrored to the `navkit_menus` table for querying. Its nodes are content and
 * live in the database (as Node elements within this menu's Structure).
 *
 * @mixin FieldLayoutBehavior
 */
class Menu extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $handle = null;
    public ?int $structureId = null;
    public ?int $maxLevels = null;

    /**
     * @var int|null Per-menu node field layout ID.
     */
    public ?int $fieldLayoutId = null;

    /**
     * @var PropagationMethod How node content propagates across sites.
     */
    public PropagationMethod $propagationMethod = PropagationMethod::All;

    /**
     * @var array<string, array{enabledByDefault: bool}> Keyed by site UID.
     */
    public array $siteSettings = [];

    public ?string $uid = null;

    public function defineRules(): array
    {
        return [
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['maxLevels'], 'integer', 'min' => 1],
            [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
            [['handle'], UniqueValidator::class, 'targetClass' => MenuRecord::class],
        ];
    }

    public function behaviors(): array
    {
        return [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Node::class,
                'idAttribute' => 'fieldLayoutId',
            ],
        ];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('navkit/menus/' . $this->id);
    }

    /**
     * Sites this menu is enabled for, as Site models.
     *
     * @return Site[]
     */
    public function getSites(): array
    {
        $sites = [];
        foreach (array_keys($this->siteSettings) as $siteUid) {
            $site = Craft::$app->getSites()->getSiteByUid($siteUid);
            if ($site !== null) {
                $sites[] = $site;
            }
        }
        return $sites;
    }

    /**
     * Serializes this menu for project config. Node content is *not* included —
     * only the definition.
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'handle' => $this->handle,
            'maxLevels' => (int)($this->maxLevels ?? 0) ?: null,
            'propagationMethod' => $this->propagationMethod->value,
            'siteSettings' => $this->siteSettings,
        ];

        $fieldLayout = $this->getFieldLayout();
        if ($fieldLayoutConfig = $fieldLayout->getConfig()) {
            $fieldLayout->uid = $fieldLayout->uid ?: StringHelper::UUID();
            $config['fieldLayouts'] = [
                $fieldLayout->uid => $fieldLayoutConfig,
            ];
        }

        return $config;
    }
}
