<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\NatsDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Swow\Coroutine;
use Swow\Sync\WaitGroup;

class NatsQueueConsumerProcess extends AbstractProcess
{
    private object $endpoint;
    private string $subject;
    private string $queueName;

    private bool $disableSpatieValidation = false;
    private NatsDriver $driver;

    public function __construct(
        object $endpoint,
        string $subject,
        string $queueName,
        bool $disableSpatieValidation
    )
    {
        $this->endpoint = $endpoint;
        $this->subject = $subject;
        $this->queueName = $queueName;
        $this->disableSpatieValidation = $disableSpatieValidation;
    }

    protected function run(): bool
    {
        $group = new WaitGroup();
        $group->add();
        Co::define($this->name . '_ConsumerProcess' . $this->subject . '_' . Str::random())
            ->charge(function (WaitGroup $group) {
                try {
                    $driver = new NatsDriver($this->subject . '_' . Str::random());
                    $driver->subscribeWithEndpoint(
                        $this->subject,
                        $this->endpoint,
                        $this->queueName,
                        $this->disableSpatieValidation
                    );
                } catch (\Throwable $e) {
                    Log::critical('Error while processing queue consumer', ['error' => $e->getMessage()]);
                    $group->done();
                }
            })->args($group)
            ->runWithClonedDiContainer();
        $group->wait();
        return true;
    }
}
