<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Pulchritudinous
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
?>
<?php
/**
 * Worker config test case.
 *
 * @author Anton Samuelsson <samuelsson.anton@gmail.com>
 */
class Pulchritudinous_Queue_Model_Worker_ConfigTest
    extends PHPUnit_Framework_TestCase
{
    /**
     * Test worker configuration.
     */
    public function testGetWorkerConfig()
    {
        $config = Mage::getModel('pulchqueue/worker_config')
            ->getWorkerConfig('test_expected_exception');

        $this->assertInstanceOf(Varien_Object::class, $config);

        $this->assertRegExp('/\d+/', $config->getPriority(), 'Priority needs to be a integer');
        $this->assertNotEmpty($config->getRule(), 'Rule need to be defined');
        $this->assertRegExp('/\d+/', $config->getDeplay(), 'Delay needs to be a integer');
        $this->assertRegExp('/\d+/', $config->getRetries(), 'Retries needs to be a integer');
        $this->assertRegExp('/\d+/', $config->getReschedule(), 'Reschedule needs to be a integer');
    }
}


