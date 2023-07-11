<?php

namespace VnPay\PaymentAll\Controller\Payment;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use VnPay\PaymentAll\Logger\Logger;

class Response extends Action implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{
    const PATH_CART = 'checkout/cart';
    const PATH_SUCCESS = 'checkout/onepage/success';


    protected $_logger;
    protected $_orderFactory;
    protected $_objCheckoutHelper;
    protected $_configSettings;
    protected $_orderRepository;
    protected $_invoiceService;
    protected $_transaction;
    protected $_invoiceSender;
    protected $_session;
    protected $_orderSender;
    protected $_orderCommentSender;
    protected $_transactionBuilder;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \VnPay\PaymentAll\Helper\Checkout $checkoutHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $configSettings,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Checkout\Model\Session $session,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
    ) {
        parent::__construct($context);
        $this->_logger 				= $logger;
        $this->_orderFactory 		= $orderFactory;
        $this->_objCheckoutHelper 	= $checkoutHelper;
        $this->_configSettings		= $configSettings->getValue('payment/vnpay_payment');
        $this->_orderRepository 	= $orderRepository;
        $this->_invoiceService 		= $invoiceService;
        $this->_transaction 		= $transaction;
        $this->_session				= $session;
        $this->_orderSender			= $orderSender;
        $this->_orderCommentSender	= $orderCommentSender;
        $this->_invoiceSender 		= $this->_objectManager->get('Magento\Sales\Model\Order\Email\Sender\InvoiceSender');
        $this->_transactionBuilder 	= $transactionBuilder;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute() {

        $logger = new Logger("CHECK_RESPONSE_VNPAY");
        // if have data response => success
        if (!empty($_REQUEST)) {
            if( $_REQUEST['vnp_ResponseCode'] == "00"){
                try {
                    $logger->info('REQUEST', [$_REQUEST]);
                    $order_id 	= (int)($_REQUEST['vnp_OrderInfo']);
                    $order 		= $this->getOrderDetailByOrderId($order_id);
                    $payment = $order->getPayment();
                    $payment->setTransactionId($order_id);
                    $payment->setLastTransId($order_id);

                    // Update order state and status.
                    $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $logger->info('order', [$order->getData()]);

                    $invoice = $this->invoice($order);
                    $invoice->setTransactionId($order_id);
                    $this->_eventManager->dispatch(
                        'checkout_ps_onepage_response',
                        ['order_id' => $order_id]
                    );

                    $payment->addTransactionCommentsToOrder(
                        $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE),
                        __(
                            'Amount of %1 has been paid via Payso payment',
                            $order->getBaseCurrency()->formatTxt($order->getBaseGrandTotal())
                        )
                    );

                    $detailData = [
                        \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => [
                            'Order Id' 			=> 	$order_id,
                            'Merchant Id'		=>	isset($_REQUEST['merchantid']) ? $_REQUEST['merchantid']  : '',
                            'Customer Email'	=>	isset($_REQUEST['customeremail']) ? $_REQUEST['customeremail'] : '',
                            'Product Detail'	=>	isset($_REQUEST['productdetail']) ? $_REQUEST['productdetail'] : '',
                            'Total'				=>	isset($_REQUEST['total']) ? $_REQUEST['total'] : '',
                            'Card Type'			=>	isset($_REQUEST['cardtype']) ? $_REQUEST['cardtype'] : '',
                        ]
                    ];

                    $transaction = $this->_transactionBuilder
                        ->setPayment($payment)
                        ->setOrder($order)
                        ->setTransactionId($order_id)
                        ->setAdditionalInformation($detailData)
                        ->setFailSafe(true)
                        ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                    $logger->info('payment', [$order->getPayment()->getData()]);
                    // create invoice
                    if($this->_configSettings['auto_invoice'] == 1) {
                        $logger->info('order auto invoice', [$order->canInvoice()]);
                        $logger->info('$transaction payment', [$transaction->getPaymentId()]);
                        $logger->info('$transaction order', [$transaction->getOrderId()]);
                        if ($order->canInvoice()) {
                            $invoice = $this->_invoiceService->prepareInvoice($order);
                            $invoice->register();
                            $invoice->save();
                            $logger->info('$invoice', [$invoice->getData()]);
                            $logger->info('$transaction', [$transaction->getTransactionId()]);
                            $logger->info('order', [$invoice->getOrder()->getData()]);
                            try {
                                $transactionSave = $this->_transaction->addObject(
                                    $invoice
                                )->addObject(
                                    $invoice->getOrder()
                                );
                                $logger->info('$transactionSave', [$transactionSave]);
                                $transactionSave->save();
                            }catch (Exception $e){
                                $order->save();
                                $transaction->save();
                                $payment->save();
                                $logger->info('catch', [$e]);
                            }
                            $this->_invoiceSender->send($invoice);

                            // send notification code
                            $order->addStatusHistoryComment(
                                __('Notified customer about invoice #%1.', $invoice->getId())
                            )->setIsCustomerNotified(true)->save();
                            $logger->info('create transaction success', ['create transaction success']);

                        }
                    }
                    else {
                        $logger->info('No auto invoice', ['no auto ']);
                        $order->save();
                        $transaction->save();
                        $payment->save();
                    }
                    return $this->_redirect('vnpay/payment/success');
                    // send mail successed
//                $this->_orderSender->send($order);
                }
                catch(Exception $e) {
                    $this->_logger->info('EXCEPTION', ['value' => $e->getMessage()]);
                }
            }else{
                $this->executeCancelAction();
                $this->messageManager->addError('Thanh toán qua VNPAY thất bại. ' . $this->getResponseDescription($_REQUEST['vnp_ResponseCode'] ));
                return $this->resultRedirectFactory->create()->setPath('checkout/onepage/failure');
            }
        }
        // else {
        // 	// if no have data response => cancel
        // 	// send mail canceled

        // 	// 	echo "CANCELED";
        // 	// 	$this->messageManager->addError(__("Payment error"));
        // 	// 	$this->executeCancelAction();
        // 	// 	$this->_eventManager->dispatch('order_cancel_after', ['order' => $order]);
        // 	// 	$strComment = "Order has been cancelled";

        // 	// 	$order->addStatusHistoryComment($strComment)->setEntityName('order')->save();
        // 	// 	$this->_orderCommentSender->send($order, true, $strComment);

        // 	return;

        // }
    }


    /**
     * @param  \Magento\Sales\Model\Order $order
     *
     * @return \Magento\Framework\DataObject
     */
    protected function invoice(\Magento\Sales\Model\Order $order) {
        return $order->getInvoiceCollection()->getLastItem();
    }

    // Get Magento OrderFactory object.
    protected function getOrderFactory() {
        return $this->_orderFactory;
    }

    // Get Magento Order object.
    protected function getOrderDetailByOrderId($orderId) {
        $order = $this->getOrderFactory()->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            return null;
        }
        return $order;
    }

    // Get the checkout object. It is reponsible for hold the current users cart detail's
    protected function getCheckoutHelper() {
        return $this->_objCheckoutHelper;
    }

    // This function is redirect to cart after customer is cancel the payment.
    protected function executeCancelAction() {
        $this->getCheckoutHelper()->cancelCurrentOrder('');
        $this->getCheckoutHelper()->restoreQuote();
    }

    /**
     * @param string $responseCode
     * @return string
     */
    protected function getResponseDescription($responseCode): string
    {
        switch ($responseCode) {
            case "00" :
                $result = "Giao dịch thành công";
                break;
            case "01" :
                $result = "Giao dịch đã tồn tại";
                break;
            case "02" :
                $result = "Merchant không hợp lệ (kiểm tra lại vnp_TmnCode)";
                break;
            case "03" :
                $result = "Dữ liệu gửi sang không đúng định dạng";
                break;
            case "04" :
                $result = "Khởi tạo GD không thành công do Website đang bị tạm khóa";
                break;
            case "05" :
                $result = "Giao dịch không thành công do: Quý khách nhập sai mật khẩu quá số lần quy định. Xin quý khách vui lòng thực hiện lại giao dịch";
                break;
            case "06" :
                $result = "Giao dịch không thành công do Quý khách nhập sai mật khẩu xác thực giao dịch (OTP). Xin quý khách vui lòng thực hiện lại giao dịch.";
                break;
            case "07" :
                $result = "Giao dịch bị nghi ngờ là giao dịch gian lận";
                break;
            case "09" :
                $result = "Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng chưa đăng ký dịch vụ InternetBanking tại ngân hàng.";
                break;
            case "10" :
                $result = "Giao dịch không thành công do: Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần";
                break;
            case "11" :
                $result = "Giao dịch không thành công do: Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại giao dịch.";
                break;
            case "12" :
                $result = "Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng bị khóa.";
                break;
            case "51" :
                $result = "Giao dịch không thành công do: Tài khoản của quý khách không đủ số dư để thực hiện giao dịch.";
                break;
            case "65" :
                $result = "Giao dịch không thành công do: Tài khoản của Quý khách đã vượt quá hạn mức giao dịch trong ngày.";
                break;
            case "08" :
                $result = "Giao dịch không thành công do: Hệ thống Ngân hàng đang bảo trì. Xin quý khách tạm thời không thực hiện giao dịch bằng thẻ/tài khoản của Ngân hàng này.";
                break;
            case "99" :
                $result = "Có lỗi sảy ra trong quá trình thực hiện giao dịch";
                break;
            default :
                $result = "Giao dịch thất bại - Failured";
        }
        return $result;
    }
}
