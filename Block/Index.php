<?php

namespace STBDigital\Zoones\Block;

class Index extends \Magento\Framework\View\Element\Template
{
    public function __construct(\Magento\Framework\View\Element\Template\Context $context)
    {
        parent::__construct($context);
    }

    public function getRequestParams()
    {
        return $this->_request->getParams();
    }

    public function getItems()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $getLocale = $objectManager->get('Magento\Framework\Locale\Resolver');
        $haystack = $getLocale->getLocale();
        $lang = strstr($haystack, '_', true);
        $params = $this->getRequestParams();

        if (array_key_exists("zipCode", $params) && array_key_exists("orderNumber", $params) ) {
            return $this->getOrderStatusData($params, $lang);
        } else {
            return [
                'valid' => false
            ];
        }
    }

    protected function getOrderStatusData($params, $langCode)
    {
        $icons = [
            'PENDING' => 'fas fa-conveyor-belt-alt',
            'IN_WORK' => 'fas fa-barcode-read',
            'READY_FOR_DELIVERY' => 'fas fa-cubes',
            'ETD' => 'fas fa-calendar-edit',
            'CTD' => 'fas fa-shipping-timed',
            'ATD' => 'fas fa-shipping-fast',
            'CANCELLED' => 'fas fa-times',
        ];

        $headers = [
            'Accept-Language: ' . $langCode,
        ];

        $get_params = '?orderReference=' . $params['orderNumber'] . '&zip=' . $params['zipCode'];

        //fetch api data
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://zoones.steinbach.at/portal_api/public/orders/status' . $get_params);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($curl);
        curl_close($curl);

        $result = [];
        $JSON = json_decode($server_output, true);

        if ($JSON) {
            //sort for mixed order response array
            uasort($JSON, function ($a, $b) {
                return $a['sort'] - $b['sort'];
            });

            //build result array
            foreach (array_reverse($JSON) as $item) {
                $date = null;

                if (array_key_exists('statusTimestamp', $item)) {
                    $date = date_create($item['statusTimestamp']);
                    $date = date_format($date, 'd.m.Y - H:i');
                }

                $result[] = [
                    'status' => $item['status'],
                    'description' => $item['description'],
                    'iconClass' => $icons[$item['statusCode']],
                    'timeStamp' => $date
                ];
            }

            return [
                'orderNumber' => $params['orderNumber'],
                'zipCode' => $params['zipCode'],
                'currentStatus' => $result[0]['status'],
                'items' => $result,
                'valid' => true
            ];
        }

        return [
            'orderNumber' => $params['orderNumber'],
            'zipCode' => $params['zipCode'],
            'items' => [],
            'valid' => true
        ];
    }
}
