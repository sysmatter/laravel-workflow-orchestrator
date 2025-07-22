<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator;

abstract class WorkflowConfig implements WorkflowConfigInterface
{
    protected function sequential(array $actions): array
    {
        return array_map(fn ($action, $index) => [
            'class' => $action,
            'index' => $index,
            'group_index' => $index,
            'is_concurrent' => false,
        ], $actions, array_keys($actions));
    }

    protected function concurrent(array $actions): array
    {
        $groupIndex = $this->getNextGroupIndex();

        return array_map(fn ($action, $index) => [
            'class' => $action,
            'index' => $this->getNextIndex() + $index,
            'group_index' => $groupIndex,
            'is_concurrent' => true,
        ], $actions, array_keys($actions));
    }

    protected function both(array $groups): array
    {
        $result = [];
        foreach ($groups as $group) {
            if (is_array($group)) {
                $result = array_merge($result, $this->concurrent($group));
            } else {
                $result = array_merge($result, $this->sequential([$group]));
            }
        }

        return $result;
    }

    private int $currentIndex = 0;

    private int $currentGroupIndex = 0;

    private function getNextIndex(): int
    {
        return $this->currentIndex++;
    }

    private function getNextGroupIndex(): int
    {
        return $this->currentGroupIndex++;
    }
}
