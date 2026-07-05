<?php

namespace jainilnagar\navkit;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\ElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\models\FieldLayout;
use craft\services\Elements;
use craft\services\Gql;
use craft\services\ProjectConfig;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use jainilnagar\navkit\elements\Node;
use jainilnagar\navkit\fieldlayoutelements\LinkField;
use jainilnagar\navkit\gql\NavkitGql;
use jainilnagar\navkit\models\Settings;
use jainilnagar\navkit\services\LinkTypes;
use jainilnagar\navkit\services\Menus;
use jainilnagar\navkit\services\Nodes;
use jainilnagar\navkit\services\Render;
use jainilnagar\navkit\variables\NavkitVariable;
use yii\base\Event;

/**
 * Navkit plugin.
 *
 * @property-read Menus $menus
 * @property-read Nodes $nodes
 * @property-read LinkTypes $linkTypes
 * @property-read Render $render
 * @method Settings getSettings()
 */
class Navkit extends BasePlugin
{
    public const CONFIG_MENUS_KEY = 'navkit.menus';

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'menus' => Menus::class,
                'nodes' => Nodes::class,
                'linkTypes' => LinkTypes::class,
                'render' => Render::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Defer most setup until Craft is fully initialized:
        Craft::$app->onInit(function() {
            $this->registerElementTypes();
            $this->registerNativeFields();
            $this->registerProjectConfigListeners();
            $this->registerCpRoutes();
            $this->registerPermissions();
            $this->registerVariables();
            $this->registerRendering();
            $this->registerGraphql();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('navkit/_settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = Craft::t('navkit', 'Navkit');

        $subNav = [];

        if (Craft::$app->getUser()->checkPermission('navkit:manageMenus')) {
            $subNav['menus'] = [
                'label' => Craft::t('navkit', 'Menus'),
                'url' => 'navkit/menus',
            ];
        }

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $subNav['settings'] = [
                'label' => Craft::t('navkit', 'Settings'),
                'url' => 'settings/plugins/navkit',
            ];
        }

        if (!empty($subNav)) {
            $item['subnav'] = $subNav;
        }

        return $item;
    }

    private function registerElementTypes(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = Node::class;
            }
        );
    }

    private function registerNativeFields(): void
    {
        // Force the Link control into every node's editor, regardless of the
        // menu's custom field layout.
        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_NATIVE_FIELDS,
            static function(DefineFieldLayoutFieldsEvent $event) {
                /** @var FieldLayout $fieldLayout */
                $fieldLayout = $event->sender;
                if ($fieldLayout->type === Node::class) {
                    $event->fields[] = [
                        'class' => LinkField::class,
                        'attribute' => 'type',
                        'mandatory' => true,
                    ];
                }
            }
        );
    }

    private function registerProjectConfigListeners(): void
    {
        $menusService = $this->menus;

        Craft::$app->getProjectConfig()
            ->onAdd(self::CONFIG_MENUS_KEY . '.{uid}', [$menusService, 'handleChangedMenu'])
            ->onUpdate(self::CONFIG_MENUS_KEY . '.{uid}', [$menusService, 'handleChangedMenu'])
            ->onRemove(self::CONFIG_MENUS_KEY . '.{uid}', [$menusService, 'handleDeletedMenu']);

        // Rebuild support — lets `project-config/rebuild` re-derive menus from the DB.
        Event::on(
            ProjectConfig::class,
            ProjectConfig::EVENT_REBUILD,
            function(\craft\events\RebuildConfigEvent $event) {
                $event->config[self::CONFIG_MENUS_KEY] = $this->menus->getRebuiltConfig();
            }
        );
    }

    private function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function(RegisterUrlRulesEvent $event) {
                $event->rules['navkit'] = 'navkit/menus/index';

                // Menu (container) management:
                $event->rules['navkit/menus'] = 'navkit/menus/index';
                $event->rules['navkit/menus/new'] = 'navkit/menus/edit';
                $event->rules['navkit/menus/<menuId:\d+>'] = 'navkit/menus/edit';

                // Node element index + edit (native Craft element screens):
                $event->rules['navkit/nodes'] = 'navkit/nodes/index';
                $event->rules['navkit/nodes/<elementId:\d+>'] = 'elements/edit';
            }
        );
    }

    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('navkit', 'Navkit'),
                    'permissions' => [
                        'navkit:manageNodes' => [
                            'label' => Craft::t('navkit', 'Manage menu nodes'),
                        ],
                        'navkit:manageMenus' => [
                            'label' => Craft::t('navkit', 'Manage menus (create, edit, delete)'),
                        ],
                    ],
                ];
            }
        );
    }

    private function registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_DEFINE_BEHAVIORS,
            static function(DefineBehaviorsEvent $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('navkit', NavkitVariable::class);
            }
        );
    }

    private function registerRendering(): void
    {
        // Expose the plugin's front-end templates (overridable via templates/navkit/...).
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['navkit'] = $this->getBasePath() . '/templates';
            }
        );

        // Bust cached menu trees when a node — or an element a node links to — changes.
        $invalidate = fn(ElementEvent $event) => $this->render->handleElementSave($event->element);
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, $invalidate);
        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, $invalidate);
    }

    private function registerGraphql(): void
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            function(RegisterGqlQueriesEvent $event) {
                $event->queries = array_merge($event->queries, NavkitGql::getQueries());
            }
        );
    }
}
