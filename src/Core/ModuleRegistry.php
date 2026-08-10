<?php

namespace AMToolkit\Core;

defined('ABSPATH') || exit;

final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    /** @var array<string, string> */
    private array $statuses = [];

    public function __construct(private FeatureFlags $features)
    {
    }

    public function register(ModuleInterface $module): void
    {
        $id = sanitize_key($module->id());

        if ($id === '') {
            throw new \InvalidArgumentException('Module ID cannot be empty.');
        }

        if (isset($this->modules[$id])) {
            throw new \LogicException("Module already registered: {$id}");
        }

        $this->modules[$id] = $module;
        $this->statuses[$id] = 'registered';
    }

    public function bootAll(): void
    {
        $visiting = [];

        foreach (array_keys($this->modules) as $id) {
            $this->bootModule($id, $visiting);
        }
    }

    /** @return array<string, string> */
    public function statuses(): array
    {
        return $this->statuses;
    }

    /** @param array<string, bool> $visiting */
    private function bootModule(string $id, array &$visiting): bool
    {
        if (($this->statuses[$id] ?? '') === 'booted') {
            return true;
        }

        if (str_starts_with($this->statuses[$id] ?? '', 'skipped:')) {
            return false;
        }

        if (isset($visiting[$id])) {
            throw new \LogicException("Circular module dependency detected at: {$id}");
        }

        $module = $this->modules[$id] ?? null;

        if ($module === null) {
            throw new \LogicException("Module dependency is not registered: {$id}");
        }

        if (!$this->features->isEnabled($id)) {
            $this->statuses[$id] = 'skipped:disabled';
            return false;
        }

        if (!$module->isAvailable()) {
            $this->statuses[$id] = 'skipped:unavailable';
            return false;
        }

        $visiting[$id] = true;

        foreach ($module->dependencies() as $dependency) {
            $dependency = sanitize_key($dependency);

            if (!$this->bootModule($dependency, $visiting)) {
                unset($visiting[$id]);
                $this->statuses[$id] = "skipped:dependency:{$dependency}";
                return false;
            }
        }

        unset($visiting[$id]);
        do_action('am_toolkit_before_module_boot', $id, $module);
        $module->boot();
        $this->statuses[$id] = 'booted';
        do_action('am_toolkit_after_module_boot', $id, $module);

        return true;
    }
}
