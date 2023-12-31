<?php

/*
 * PaymentModeType is give the model for dropdown page in admin configuration setting page.
 */

namespace VnPay\PaymentAll\Model\Config;

class Language implements \Magento\Framework\Option\ArrayInterface
{
	public function toOptionArray()
	{
	    return [
            ['value' => 'TH', 'label' => __('Thai')],
			['value' => 'EN', 'label' => __('English')],
			['value' => 'JP', 'label' => __('Japan')]
        ];
	}
}
