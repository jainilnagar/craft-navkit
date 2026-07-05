<?php

namespace jainilnagar\navkit\services;

use Craft;
use jainilnagar\navkit\base\LinkTypeInterface;
use jainilnagar\navkit\events\RegisterLinkTypesEvent;
use jainilnagar\navkit\linktypes\AssetLinkType;
use jainilnagar\navkit\linktypes\CategoryLinkType;
use jainilnagar\navkit\linktypes\EntryLinkType;
use jainilnagar\navkit\linktypes\PassiveLinkType;
use jainilnagar\navkit\linktypes\ProductLinkType;
use jainilnagar\navkit\linktypes\UrlLinkType;
use yii\base\Component;

/**
 * Registry of link types.
 *
 * Built-ins are provided here; third parties add or remove types by listening
 * for EVENT_REGISTER_LINK_TYPES. Types whose isAvailable() returns false (e.g.
 * the Commerce product type with Commerce uninstalled) are filtered out.
 */
class LinkTypes extends Component
{
    public const EVENT_REGISTER_LINK_TYPES = 'registerLinkTypes';

    /** @var LinkTypeInterface[]|null Keyed by id. */
    private ?array $_types = null;

    /**
     * @return LinkTypeInterface[] Keyed by id, in display order.
     */
    public function getAllLinkTypes(): array
    {
        if ($this->_types !== null) {
            return $this->_types;
        }

        $event = new RegisterLinkTypesEvent([
            'types' => [
                UrlLinkType::class,
                EntryLinkType::class,
                CategoryLinkType::class,
                AssetLinkType::class,
                ProductLinkType::class,
                PassiveLinkType::class,
            ],
        ]);
        $this->trigger(self::EVENT_REGISTER_LINK_TYPES, $event);

        $this->_types = [];
        foreach ($event->types as $type) {
            /** @var LinkTypeInterface $instance */
            $instance = is_object($type) ? $type : Craft::createObject($type);
            if ($instance::isAvailable()) {
                $this->_types[$instance::id()] = $instance;
            }
        }

        return $this->_types;
    }

    public function getLinkType(?string $id): ?LinkTypeInterface
    {
        if (!$id) {
            return null;
        }
        return $this->getAllLinkTypes()[$id] ?? null;
    }
}
