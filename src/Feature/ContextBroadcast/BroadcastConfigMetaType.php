<?php

declare(strict_types=1);

namespace Noem\State\Feature\ContextBroadcast;

use Noem\State\MetaType;

/**
 * MetaType for broadcast configuration storage
 *
 * Stores region-level and property-level broadcast settings
 * for runtime access by the Set chain middleware.
 */
class BroadcastConfigMetaType extends MetaType
{
}
