<?php

namespace VnPay\PaymentAll\Controller\Payment;

use Magento\Framework\App\Action\Action;

class Fail extends Action {

	protected $_logger;
	protected $_resultPageFactory;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Psr\Log\LoggerInterface $logger,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory
	) {
		parent::__construct($context);
		$this->_logger 				= $logger;
		$this->_resultPageFactory 	= $resultPageFactory;
	}

	public function execute() {
		$this->_logger->info('FAIL_REQUEST', ['value' => $_REQUEST]);

		$resultPage = $this->_resultPageFactory->create();
		$resultPage->getConfig()->getTitle()->set(__('Payment Fail'));
		return $resultPage;
	}
}
