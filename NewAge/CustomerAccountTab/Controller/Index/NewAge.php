<?php
/**
*
* Copyright © Magento, Inc. All rights reserved.
* See COPYING.txt for license details.
*/
namespace NewAge\CustomerAccountTab\Controller\Index;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Action;

/**
 * this class is used for showing the form on the customer dashbaord
 */
class NewAge extends Action
{
    /**
    * Show Custom Product Filter
    *
    * @return \Magento\Framework\Controller\ResultInterface
    */
    public function execute()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }
}
