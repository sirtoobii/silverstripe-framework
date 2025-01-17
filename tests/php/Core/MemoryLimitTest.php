<?php

namespace SilverStripe\Core\Tests;

use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;

class MemoryLimitTest extends SapphireTest
{
    protected $origMemLimitMax;
    protected $origTimeLimitMax;
    protected $origMemLimit;
    protected $origTimeLimit;

    protected function setUp(): void
    {
        parent::setUp();

        // see http://www.hardened-php.net/suhosin/configuration.html#suhosin.memory_limit
        if (in_array('suhosin', get_loaded_extensions())) {
            $this->markTestSkipped("This test cannot be run with suhosin installed");
        } else {
            $this->origMemLimit = ini_get('memory_limit');
            $this->origTimeLimit = ini_get('max_execution_time');
            $this->origMemLimitMax = Environment::getMemoryLimitMax();
            $this->origTimeLimitMax = Environment::getTimeLimitMax();
            Environment::setMemoryLimitMax(null);
            Environment::setTimeLimitMax(null);
        }
    }

    protected function tearDown(): void
    {
        if (!in_array('suhosin', get_loaded_extensions())) {
            ini_set('memory_limit', $this->origMemLimit);
            set_time_limit($this->origTimeLimit);
            Environment::setMemoryLimitMax($this->origMemLimitMax);
            Environment::setTimeLimitMax($this->origTimeLimitMax);
        }
        parent::tearDown();
    }

    public function testIncreaseMemoryLimitTo()
    {
        // ini_set('memory_limit', '64M');
        // current memory usage in travis is 197M, can't ini_set this down to 64M
        // for recipe-kitchen-sink, it's 284M
        // Using a higher memory limit instead
        ini_set('memory_limit', '330M');
        Environment::setMemoryLimitMax('512M');

        // It can go up
        Environment::increaseMemoryLimitTo('340M');
        $this->assertEquals('340M', ini_get('memory_limit'));

        // But not down
        Environment::increaseMemoryLimitTo('320M');
        $this->assertEquals('340M', ini_get('memory_limit'));

        // Test the different kinds of syntaxes
        Environment::increaseMemoryLimitTo(1024*1024*350);
        $this->assertEquals('350M', ini_get('memory_limit'));

        Environment::increaseMemoryLimitTo('109600K');
        $this->assertEquals('350M', ini_get('memory_limit'));

        // Attempting to increase past max size only sets to max
        Environment::increaseMemoryLimitTo('1G');
        $this->assertEquals('512M', ini_get('memory_limit'));

        // No argument means unlimited (but only if originally allowed)
        if (is_numeric($this->origMemLimitMax) && $this->origMemLimitMax < 0) {
            Environment::setMemoryLimitMax(-1);
            Environment::increaseMemoryLimitTo();
            $this->assertEquals(-1, ini_get('memory_limit'));
        }
    }

    public function testIncreaseTimeLimitTo()
    {
        // Can't change time limit
        if (!set_time_limit(6000)) {
            $this->markTestSkipped("Cannot change time limit");
        }

        // It can go up
        $this->assertTrue(Environment::increaseTimeLimitTo(7000));
        $this->assertEquals(7000, ini_get('max_execution_time'));

        // But not down
        $this->assertTrue(Environment::increaseTimeLimitTo(5000));
        $this->assertEquals(7000, ini_get('max_execution_time'));

        // 0/nothing means infinity
        $this->assertTrue(Environment::increaseTimeLimitTo());
        $this->assertEquals(0, ini_get('max_execution_time'));

        // Can't go down from there
        $this->assertTrue(Environment::increaseTimeLimitTo(10000));
        $this->assertEquals(0, ini_get('max_execution_time'));
    }
}
