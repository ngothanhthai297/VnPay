<?php

namespace VnPay\PaymentAll\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;

class VnPayRequest extends AbstractHelper
{
    private $objConfigSettings;
    private $objConfigSettingsVnpay;
    private $objStoreManagerInterface;
    protected $formKey;
    protected $_logger;

    function __construct(
        ScopeConfigInterface $configSettings,
        StoreManagerInterface $storeManagerInterface,
        FormKey $formKey,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->objConfigSettingsVnpay = $configSettings;
        $this->objStoreManagerInterface = $storeManagerInterface;
        $this->formKey = $formKey;
        $this->_logger = $logger;
    }

    // Declare the Form array to hold the form request.
    private $arrayPaysoFormFields = array(
        "merchantid"     => "",
        "refno"          => "",
        "customeremail"  => "",
        "productdetail"  => "",
        "total"          => "",
        "lang"			 => "",
        "cc"             => ""
    );

    public function getFormKey() {
        return $this->formKey->getFormKey();
    }

    private function generatePaysoCommonFormFields($parameter)
    {
        $vnPayHashCode = $this->getVnPayHashCode();

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $this->getVnPayTerminalCode(),
            "vnp_Amount" => round($parameter['amount'] * 100, 0),
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $this->rmVisitorIp(),
            "vnp_Locale" => 'vn',
            "vnp_OrderInfo" => $parameter['order_id'],
            "vnp_OrderType" => 'other',
            "vnp_ReturnUrl" => $this->getResponseReturnUrl(),
            "vnp_TxnRef" => $parameter['order_id'],
        );

        if (isset($vnp_BankCode) && $vnp_BankCode != "") {
            $inputData['vnp_BankCode'] = $vnp_BankCode;
        }
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $this->getVnPayUrl() . "?" . $query;
        if (isset($vnPayHashCode)) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnPayHashCode);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }
        return $vnp_Url;
    }

    // Get the merchant website return URL.
    function getResponseReturnUrl() {
        $baseUrl = $this->objStoreManagerInterface->getStore()->getBaseUrl();
        return $baseUrl . 'vnpay/payment/response';
    }

    // Get the merchant website return URL.
    function getSuccessReturnUrl() {
        $baseUrl = $this->objStoreManagerInterface->getStore()->getBaseUrl();
        return $baseUrl . 'vnpay/payment/success';
    }

    // This function is used to genereate the request for make payment to payment getaway.
    public function psConstructRequest($parameter, $isLoggedIn) {

        $html = '<form name="psForm" action="' . $this->generatePaysoCommonFormFields($parameter) . '" method="POST">';
        $html .= '</form>';

        $html .= '<script type="text/javascript">';
        $html .= 'document.psForm.submit()';
        $html .= '</script>';
        $this->_logger->info('DATA_REQUEST', ['value' => $this->arrayPaysoFormFields]);
        return $html;

        // return $this->arrayPaysoFormFields;

    }

    protected function rmVisitorIp()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $a */
        $remoteAddress = $om->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
        return $remoteAddress->getRemoteAddress();
    }

    private function getVnPayUrl(): string
    {
        return $this->objConfigSettingsVnpay->getValue("payment/vnpay_payment/vnp_url") ?? "";
    }

    private function getVnPayTerminalCode(): string
    {
        return $this->objConfigSettingsVnpay->getValue("payment/vnpay_payment/vnp_tmncode") ?? "";
    }

    private function getVnPayHashCode(): string
    {
        return $this->objConfigSettingsVnpay->getValue("payment/vnpay_payment/vnp_hashsecret") ?? "";
    }
}
