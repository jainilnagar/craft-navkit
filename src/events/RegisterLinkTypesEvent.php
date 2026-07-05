<?php

namespace jainilnagar\navkit\events;

use yii\base\Event;

/**
 * Fired by the LinkTypes service so plugins can register custom link types.
 *
 * Each entry may be a class name (string), a config array compatible with
 * Craft::createObject(), or an already-instantiated LinkTypeInterface.
 */
class RegisterLinkTypesEvent extends Event
{
    /** @var array<class-string|object|array> */
    public array $types = [];
}
