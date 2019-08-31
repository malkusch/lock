<?php

namespace malkusch\lock\mutex;

use Eloquent\Liberator\Liberator;
use malkusch\lock\util\PcntlTimeout;
use PHPUnit\Framework\TestCase;

/**
 * @author Willem Stuursma-Ruwen <willem@stuursma.name>
 * @link bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 * @license WTFPL
 * @see CASMutex
 */
class FlockMutexTest extends TestCase
{
    /**
     * @var FlockMutex
     */
    private $mutex;

    /**
     * @var resource
     */
    private $file;

    protected function setUp()
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'flock-');
        $this->mutex = Liberator::liberate(new FlockMutex(fopen($this->file, 'r'), 1));
    }

    protected function tearDown()
    {
        unlink($this->file);

        parent::tearDown();
    }

    /**
     * @dataProvider dpTimeoutableStrategies
     */
    public function testCodeExecutedOutsideLockIsNotThrown(int $strategy)
    {
        $this->mutex->strategy = $strategy;

        $this->assertTrue($this->mutex->synchronized(function (): bool {
            usleep(1.1e6);

            return true;
        }));
    }

    /**
     * @expectedException \malkusch\lock\exception\TimeoutException
     * @expectedExceptionMessage Timeout of 1 seconds exceeded.
     * @dataProvider dpTimeoutableStrategies
     */
    public function testTimeoutOccurs(int $strategy)
    {
        $another_resource = fopen($this->file, 'r');
        flock($another_resource, LOCK_EX);

        $this->mutex->strategy = $strategy;

        try {
            $this->mutex->synchronized(
                function () {
                    $this->fail('Did not expect code to be executed');
                }
            );
        } finally {
            fclose($another_resource);
        }
    }

    public function dpTimeoutableStrategies()
    {
        return [
            [FlockMutex::STRATEGY_PCNTL],
            [FlockMutex::STRATEGY_BUSY],
        ];
    }

    /**
     * @expectedException \malkusch\lock\exception\DeadlineException
     */
    public function testNoTimeoutWaitsForever()
    {
        $another_resource = fopen($this->file, 'r');
        flock($another_resource, LOCK_EX);

        $this->mutex->strategy = FlockMutex::STRATEGY_BLOCK;

        $timebox = new PcntlTimeout(1);
        $timebox->timeBoxed(function () {
            $this->mutex->synchronized(function () {
                $this->fail('Did not expect code execution.');
            });
        });
    }
}
