<?php
/**
 * @author  Christoph Seebacher <christoph.seebacher@steinbach.at   >
 * @package STBDigital\Zoones\Block
 * @copyright Copyright (c) 2024 Steinbach International GmbH
 * @created 29.05.2024
 */

namespace STBDigital\Zoones\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class Index
 * @package STBDigital\Zoones\Block
 */
class Index extends \Magento\Framework\View\Element\Template
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    const ZOONES_ORDERSTATUS_API_URL = 'zoones_orderstatus/settings/api_url';

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\Element\Template\Context $context)
    {
        $this->scopeConfig = $scopeConfig;
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

        if (array_key_exists("zip", $params) && array_key_exists("reference", $params) ) {
            return $this->getOrderStatusData($params, $lang);
        } else {
            return [
                'valid' => false
            ];
        }
    }

    protected function getOrderStatusData($params, $langCode)
    {
        $langWhitelist = ["de", "en"];
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
            'Accept-Language: ' . (in_array($langCode, $langWhitelist) ? $langCode : "en"),
        ];
        $get_params = '?orderReference=' . $params['reference'] . '&zip=' . $params['zip'];
        $base_url = $this->scopeConfig->getValue(self::ZOONES_ORDERSTATUS_API_URL);
        $request_url = $base_url . $get_params;

        //fetch api data
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $request_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($curl);
        curl_close($curl);

        $result = [];
        $JSON = json_decode($server_output, true);

        if ($JSON) {
            //sort for mixed order response array
            uasort($JSON, function ($a, $b) {
                $a_sort = $a['sort'] ?? PHP_INT_MAX;
                $b_sort = $b['sort'] ?? PHP_INT_MAX;
                return $b_sort <=> $a_sort;
            });

            //build result array
            foreach ($JSON as $item) {
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
                'reference' => $params['reference'],
                'zip' => $params['zip'],
                'currentStatus' => $result[0]['status'],
                'items' => $result,
                'valid' => true
            ];
        }

        return [
            'reference' => $params['reference'],
            'zip' => $params['zip'],
            'items' => [],
            'valid' => true
        ];
    }
}
