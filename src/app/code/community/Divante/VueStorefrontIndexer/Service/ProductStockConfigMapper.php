<?php

declare(strict_types=1);

class Divante_VueStorefrontIndexer_Service_ProductStockConfigMapper
{
    private Divante_VueStorefrontIndexer_Interfaces_ConfigReaderInterface $configReader;

    private Mage_CatalogInventory_Helper_Minsaleqty $minSaleQtyHelper;

    private array $stockConfigFieldsMapping = [
        'use_config_backorders'       => 'backorders',
        'use_config_enable_qty_inc'   => 'enable_qty_increments',
        'use_config_manage_stock'     => 'manage_stock',
        'use_config_max_sale_qty'     => 'max_sale_qty',
        'use_config_min_qty'          => 'min_qty',
        'use_config_min_sale_qty'     => 'min_sale_qty',
        'use_config_notify_stock_qty' => 'notify_stock_qty',
    ];

    private array $stockConfig = [];

    public function __construct(
        Divante_VueStorefrontIndexer_Interfaces_ConfigReaderInterface $configReader,
        Mage_CatalogInventory_Helper_Minsaleqty $minSaleQtyHelper
    ) {
        $this->configReader = $configReader;
        $this->minSaleQtyHelper = $minSaleQtyHelper;

        $this->loadStockConfig();
    }

    public function processStockData(array $stockData): array
    {
        // Use base config values for product stock
        foreach ($stockData as $key => $value) {
            if (isset($this->stockConfigFieldsMapping[$key]) && $value === '1') {
                $productStockField = $this->stockConfigFieldsMapping[$key];

                $stockData[$productStockField] = $this->stockConfig[$key];
            }
        }

        return $stockData;
    }

    private function loadStockConfig(): void
    {
        foreach ($this->stockConfigFieldsMapping as $stockFieldName => $configFieldName) {
            switch ($configFieldName) {
                case 'min_sale_qty':
                    $configValue = $this->minSaleQtyHelper->getConfigValue(
                        Mage_Customer_Model_Group::NOT_LOGGED_IN_ID
                    );

                    // Set default value if config is empty
                    $configValue = $configValue ?: '1';
                    break;
                default:
                    $configValue = $this->configReader->getParameter('cataloginventory/item_options/' . $configFieldName);
                    break;
            }

            $this->stockConfig[$stockFieldName] = $configValue;
        }
    }
}
