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
 * Class for handling adding and receiving jobs from the queue.
 *
 * @author Anton Samuelsson <samuelsson.anton@gmail.com>
 */
class Pulchritudinous_Queue_Model_Queue
{
    /**
     * Add a job to the queue that will be asynchronously handled by a worker.
     *
     * @param  string                  $worker
     * @param  array                   $payload
     * @param  string                  $identity
     * @param  false|integer|Zend_Date $delay
     *
     * @return boolean
     *
     * @throws Mage_Core_Exception
     */
    public function add(string $worker, array $payload = [], $identity = '', $delay = false)
    {
        $configModel    = Mage::getSingleton('pulchqueue/worker_config');
        $config         = $configModel->getWorkerConfig($worker);

        if (!$config) {
            Mage::throwException("Unable to find worker with name {$worker}");
        }

        if (!is_string($identity)) {
            Mage::throwException('Identity needs to be of type string');
        }

        if ($config->getRule() == 'ignore') {
            $hasLabour = $this->getResource()->hasUnprocessedWorkerIdentity(
                $worker,
                $identity
            );

            if ($hasLabour == true) {
                return true;
            }
        } elseif ($config->getRule() == 'replace') {
            $this->getResource()->setStatusOnUnprocessedByWorkerIdentity(
                'replaced',
                $worker,
                $identity
            );
        }

        Mage::getModel('pulchqueue/labour')
            ->setWorker($worker)
            ->setIdentity($identity)
            ->setPriority($config->getPriority())
            ->setPayload(serialize($this->_validateArrayData($payload)))
            ->setStatus('pending')
            ->setExecuteAt($this->_getWhen($config, $delay))
            ->save();

        return true;
    }

    /**
     *
     *
     * @param  Varien_Object           $config
     * @param  false|integer|Zend_Date $delay
     *
     * @return string
     */
    protected function _getWhen($config, $delay = false)
    {
        $time           = time();
        $when           = date('Y-m-d H:i:s', $time + $config->getDelay());

        if ($delay !== false) {
            if (is_numeric($delay)) {
                $when = date('Y-m-d H:i:s', $time + $delay);
            } elseif ($delay instanceof Zend_Date) {
                $when = $delay->toString('Y-m-d H:i:s');
            } elseif (is_numeric($config->getDelay())) {
                $when = date('Y-m-d H:i:s', $time + $config->getDelay());
            }
        }

        return $when;
    }

    /**
     * Validate array data to make sure it does not contain objects.
     *
     * @param  array $data
     *
     * @return array
     */
    protected function _validateArrayData($data)
    {
        $return = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $return[$key] = $this->_validateArrayData($value);

                continue;
            }

            if (is_object($value)) {
                continue;
            }

            $return[$key] = $value;
        }

        return $return;
    }

    /**
     * Receive next job from the queue.
     *
     * @return Varien_Object|false
     */
    public function reserve()
    {
        $configModel        = Mage::getSingleton('pulchqueue/worker_config');
        $running            = [];
        $runningCollection  = $this->getRunning();
        $queueCollection    = Mage::getModel('pulchqueue/labour')
            ->getCollection()
            ->addFieldToFilter('status', ['eq' => 'pending'])
            ->addFieldToFilter('execute_at', ['lteq' => now()])
            ->setOrder('priority', 'ASC')
            ->setOrder('created_at', 'ASC');

        foreach ($runningCollection as $labour) {
            $identity = "{$labour->getWorker()}-{$labour->getIdentity()}";

            $running[$identity] = null;
        }

        foreach ($queueCollection as $labour) {
            $config     = $configModel->getWorkerConfig($labour->getWorker());
            $identity   = "{$labour->getWorker()}-{$labour->getIdentity()}";

            if ($config->getRule() == 'wait') {
                if (isset($running[$identity])) {
                    continue;
                }
            }

            return $labour;
        }

        return false;
    }

    /**
     *
     *
     * @return Varien_Data_Collection|Varien_Object
     */
    public function getRunning()
    {
        $collection = Mage::getModel('pulchqueue/labour')
            ->getCollection()
            ->addFieldToFilter('status', ['eq' => 'running']);

        $transaction    = Mage::getModel('core/resource_transaction');
        $hasUnknown     = false;

        foreach ($collection as $labour) {
            if (!posix_kill($labour->getPid(), 0)) {
                $hasUnknown = true;

                $labour->setFinishedAt(now());
                $labour->setStatus('unknown');

                $transaction->addObject($labour);

                $collection->removeItemByKey($labour->getId());
            }
        }

        if ($hasUnknown == true) {
            $transaction->save();
        }

        return $collection;
    }

    /**
     *
     *
     * @param  Pulchritudinous_Queue_Model_Labour
     *
     * @return Pulchritudinous_Queue_Model_Labour
     */
    public function finish($labour)
    {
        $data = [
            'status'        => 'finished',
            'finished_at'   => now(),
        ];

        $labour->addData($data)->save();

        return $labour;
    }

    /**
     * Reschedule a job to be run at a later time.
     *
     * @param  Pulchritudinous_Queue_Model_Labour $labour
     * @param  false|integer|Zend_Date            $delay
     *
     * @return boolean
     *
     * @throws Mage_Core_Exception
     */
    public function reschedule(Pulchritudinous_Queue_Model_Labour $labour, $delay = false)
    {
        $configModel    = Mage::getSingleton('pulchqueue/worker_config');
        $config         = $configModel->getWorkerConfig($labour->getWorker());

        $labour
            ->setStatus('pending')
            ->setExecuteAt($this->_getWhen($config, $delay))
            ->save();

        return true;
    }
}

