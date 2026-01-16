<?php

declare(strict_types=1);

namespace Noem\State\Feature\ContextBroadcast;

use Noem\State\Chains;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Params;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use Nette\Schema\Expect;

/**
 * Real-time context change notification feature
 *
 * Emits ContextChange events whenever $this->set() is called,
 * enabling external observers to monitor state machine context mutations.
 *
 * Provides three-level serialization control:
 * - Default: broadcast all changes (no JsonSchemaFeature)
 * - Schema-based: only broadcast schema-defined properties (with JsonSchemaFeature)
 * - Property-level: opt-out individual properties via broadcast: false flag
 * - Region-level: opt-out entire region via context.broadcast: false
 */
#[RequiresFeature(ExtendedState::class)]
class ContextBroadcastFeature implements Feature
{
    #[\Override]
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply()->use(
            function (
                Chains\Set $setChain,
                Chains\Meta $meta,
                Chains\Notification $notificationChain,
                ?EnhanceRegionBuilder $enhanceRegionBuilder,
                ?LoaderChains\Schema $schema
            ) {
                // Extend YAML schema to accept broadcast flags
                $this->extendSchema($schema);

                // Hook into EnhanceRegionBuilder to store broadcast config (for YAML loader)
                if ($enhanceRegionBuilder !== null) {
                    $this->storeBroadcastConfigFromLoader($enhanceRegionBuilder, $meta);
                }

                // Add Set chain middleware to emit ContextChange events
                // Note: For programmatic setup without YAML, the middleware detects schema from JsonSchemaMetaType
                $this->addBroadcastMiddleware($setChain, $meta, $notificationChain);
            }
        );
    }

    /**
     * Extend YAML schema to accept context.broadcast flag
     *
     * Note: Property-level broadcast flags are handled via otherItems() in the schema
     * and parsed in AddBroadcastConfig build step
     */
    private function extendSchema(?LoaderChains\Schema $schema): void
    {
        if ($schema === null) {
            return;  // RegionLoader not loaded
        }

        $schema->link(function (SchemaContext $context, callable $next) {
            $contextSchema = $context->getCustomSchema('context');

            if ($contextSchema !== null) {
                // Add region-level broadcast flag
                $contextSchema = $contextSchema->extend([
                    'broadcast' => Expect::bool(true),  // Default: true
                ]);

                $context->addCustomSchema('context', $contextSchema);
                $context->region = $context->region->extend([
                    'context' => $contextSchema,
                ]);
            }

            return $next($context);
        });
    }

    /**
     * Store broadcast configuration from YAML loader config
     */
    private function storeBroadcastConfigFromLoader(EnhanceRegionBuilder $enhanceRegionBuilder, Chains\Meta $meta): void
    {
        $enhanceRegionBuilder->link(function (Params\BuildParams $context, callable $next) use ($meta) {
            $loaderConfig = $context->config(\Noem\State\Chains\Params\Config\LoaderConfig::class);

            $builder = $next($context);
            assert($builder instanceof RegionBuilder);

            $schemaPresent = $loaderConfig->hasContext('schema');

            // Add build step to store broadcast config
            $builder->addBuildStep(new AddBroadcastConfig(
                regionBroadcast: $loaderConfig->context('broadcast', true),
                schema: $schemaPresent ? $loaderConfig->context('schema') : [],
                schemaPresent: $schemaPresent,
                meta: $meta
            ));

            return $builder;
        });
    }

    /**
     * Add Set chain middleware to emit ContextChange events
     */
    private function addBroadcastMiddleware(
        Chains\Set $setChain,
        Chains\Meta $meta,
        Chains\Notification $notificationChain
    ): void {
        $setChain->link(
            function (Params\Set $set, callable $next) use ($meta, $notificationChain) {
                // Try to get broadcast config (may not exist if config wasn't set via YAML)
                $broadcastConfig = null;
                try {
                    $broadcastConfig = $meta->call(
                        new Params\Meta($set->region, BroadcastConfigMetaType::get())
                    );
                } catch (\Throwable) {
                    // Config doesn't exist from YAML path - fall through to schema detection
                    $broadcastConfig = null;
                }

                // If no YAML config, check for programmatically registered schema
                if ($broadcastConfig === null || !isset($broadcastConfig['enabled'])) {
                    try {
                        $schemaMeta = $meta->call(
                            new Params\Meta($set->region, \Noem\State\Feature\JsonSchema\JsonSchemaMetaType::get())
                        );
                        $schema = $schemaMeta['schema'] ?? null;

                        if ($schema !== null) {
                            // Schema was registered programmatically - create broadcast config on demand
                            $broadcastConfig = ['enabled' => true, 'properties' => []];
                            foreach ($schema as $entry) {
                                $name = $entry['name'];
                                $broadcastConfig['properties'][$name] = $entry['broadcast'] ?? true;
                            }
                        }
                    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                    } catch (\Throwable) {
                        // JsonSchemaFeature not loaded - broadcast all (no filtering)
                    }
                }

                // Default to broadcast all if no config
                $broadcastConfig ??= [];

                // 1. Check region-level opt-out
                if (isset($broadcastConfig['enabled']) && $broadcastConfig['enabled'] === false) {
                    return $next($set);  // Skip broadcast - region-level disabled
                }

                // 2. Check schema gate and property-level opt-out
                if (isset($broadcastConfig['properties'])) {
                    // JsonSchemaFeature loaded - only broadcast schema-defined properties
                    if (!isset($broadcastConfig['properties'][$set->key])) {
                        return $next($set);  // Not in schema, skip
                    }

                    if ($broadcastConfig['properties'][$set->key] === false) {
                        return $next($set);  // Explicitly disabled, skip
                    }
                }

                // 3. Get previous value BEFORE calling next (before storage)
                $contextMeta = $meta->call(
                    new Params\Meta($set->region, ContextMetaType::get())
                );
                $previousValue = $contextMeta[$set->key] ?? null;

                // 4. Perform the actual set (call next middleware)
                $result = $next($set);

                // 5. Emit change event AFTER successful set
                $change = new ContextChange(
                    path: $set->region->path(),
                    key: $set->key,
                    value: $set->value,
                    previousValue: $previousValue,
                    timestamp: microtime(true),
                );

                // 6. Notify all listeners
                $notifyParams = new Params\Notify($set->region, $change);
                $listeners = $notificationChain->call($notifyParams);

                foreach ($listeners as $listener) {
                    $listener($change, $set->region);
                }

                return $result;
            },
            prepend: false  // Run AFTER storage middleware
        );
    }
}
