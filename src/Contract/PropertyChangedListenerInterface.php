<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

if (interface_exists('\Doctrine\Persistence\PropertyChangedListener')) {
    interface PropertyChangedListenerBaseInterface extends \Doctrine\Persistence\PropertyChangedListener {}
} else {
    interface PropertyChangedListenerBaseInterface {}
}

/**
 * To manually control change tracking of entities, entities can notify the entity tracker of changes
 * using the exact same mechanism as the Doctrine 'Notify' change tracker.
 */
interface PropertyChangedListenerInterface extends PropertyChangedListenerBaseInterface
{

}
