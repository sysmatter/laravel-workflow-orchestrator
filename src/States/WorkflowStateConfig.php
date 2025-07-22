<?php

declare(strict_types=1);

namespace SysMatter\WorkflowOrchestrator\States;

use SysMatter\StatusMachina\Config\AbstractStateConfig;

class WorkflowStateConfig extends AbstractStateConfig
{
    protected string $initialState = 'created';

    public function __construct()
    {
        // Define states
        $this->addStates([
            'created',
            'processing',
            'waiting',
            'paused',
            'completed',
            'failed',
            'cancelled',
        ]);

        // Define transitions
        $this->setTransition(
            'start',
            $this->transition()
                ->from('created')
                ->to('processing')
        );

        $this->setTransition(
            'wait',
            $this->transition()
                ->from('processing')
                ->to('waiting')
        );

        $this->setTransition(
            'resume',
            $this->transition()
                ->from(['waiting', 'paused'])
                ->to('processing')
        );

        $this->setTransition(
            'pause',
            $this->transition()
                ->from(['processing', 'waiting'])
                ->to('paused')
        );

        $this->setTransition(
            'complete',
            $this->transition()
                ->from('processing')
                ->to('completed')
        );

        $this->setTransition(
            'fail',
            $this->transition()
                ->from(['processing', 'waiting'])
                ->to('failed')
        );

        $this->setTransition(
            'retry',
            $this->transition()
                ->from('failed')
                ->to('processing')
        );

        $this->setTransition(
            'cancel',
            $this->transition()
                ->from(['created', 'processing', 'waiting', 'paused'])
                ->to('cancelled')
        );
    }
}
