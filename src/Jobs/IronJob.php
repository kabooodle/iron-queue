<?php

namespace Collective\IronQueue\Jobs;

use Collective\IronQueue\IronQueue;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Arr;

class IronJob extends Job implements JobContract
{
    /**
     * The Iron queue instance.
     *
     * @var \Collective\IronQueue\IronQueue
     */
    protected $iron;

    /**
     * The IronMQ message instance.
     *
     * @var object
     */
    protected $job;

    /**
     * Indicates if the message was a push message.
     *
     * @var bool
     */
    protected $pushed = false;

    /**
     * Create a new job instance.
     *
     * @param \Illuminate\Container\Container $container
     * @param \Collective\IronQueue\IronQueue $iron
     * @param object                          $job
     * @param bool                            $pushed
     */
    public function __construct(Container $container, IronQueue $iron, $job, $pushed = false)
    {
        $this->job = $job;
        $this->iron = $iron;
        $this->pushed = $pushed;
        $this->container = $container;
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->body;
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        if (isset($this->job->pushed)) {
            return;
        }

        $this->iron->deleteMessage($this->getQueue(), $this->job->id, $this->job->reservation_id);
    }

    /**
     * Release the job back into the queue.
     *
     * @param int $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        if (!$this->pushed) {
            $this->delete();
        }

        $this->recreateJob($delay);
    }

    /**
     * Release a pushed job back onto the queue.
     *
     * @param int $delay
     *
     * @return void
     */
    protected function recreateJob($delay)
    {
        $payload = json_decode($this->job->body, true);

        Arr::set($payload, 'attempts', Arr::get($payload, 'attempts', 1) + 1);

        $this->iron->recreate(json_encode($payload), $this->getQueue(), $delay);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return Arr::get(json_decode($this->job->body, true), 'attempts', 1);
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job->id;
    }

    /**
     * Get the underlying Iron queue instance.
     *
     * @return \Collective\IronQueue\IronQueue
     */
    public function getIron()
    {
        return $this->iron;
    }

    /**
     * Get the underlying IronMQ job.
     *
     * @return mixed
     */
    public function getIronJob()
    {
        return $this->job;
    }

    /**
     * Get the name of the queue the job belongs to.
     *
     * @return string
     */
    public function getQueue()
    {
        return Arr::get(json_decode($this->job->body, true), 'queue');
    }
}
