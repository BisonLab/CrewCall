<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

class StateHandler
{
    private array $state_handlers = [];
    private array $core_handlers = [];

    public function __construct(
        #[AutowireLocator('app.statehandler')] private ServiceLocator $locator
    ) {
        foreach ($this->locator->getProvidedServices() as $shclass) {
            $handler = $this->locator->get($shclass);
            $handle_class = $handler->getStateHandleClass();
            if (preg_match('/^App/', $shclass)) {
                $this->core_handlers[$handle_class] ?? [];
                // Yes, only one statehandler per class in core.
                $this->core_handlers[$handle_class] = $handler;
            } else {
                $this->state_handlers[$handle_class] ?? [];
                $this->state_handlers[$handle_class][] = $handler;
            }
        }
        // In case of a custom handler not wanting core to run:
        foreach ($this->state_handlers as $shclass => $sh) {
            if ($sh->ignoreCore() && isset($this->core_handlers[$shclass]))
                    unset($this->core_handlers[$shclass]);
        }
    }

    public function handleStateChange($entity, $from, $to)
    {
        if ($from == $to)
            return;
        foreach ($this->getHandlersFor($entity) as $handler)
            $handler->handle($entity, $from, $to);
        return;
    }

    public function getHandlersFor($entity): array
    {
        $handlers = [];
        $entity_class = \Doctrine\Common\Util\ClassUtils::getClass($entity);
        if (isset($this->state_handlers[$entity_class]))
            $handlers = $this->state_handlers[$entity_class];
        if (isset($this->core_handlers[$entity_class]))
            $handlers[] = $this->core_handlers[$entity_class];
        return $handlers;
    }
}
