<?php

use Divante_VueStorefrontIndexer_Api_DatasourceInterface as DataSourceInterface;

class Divante_VueStorefrontIndexer_Model_Indexer_Datasource_Product_Custom implements DataSourceInterface
{
    /**
     * @var Divante_VueStorefrontIndexer_Model_Resource_Catalog_Product_Custom
     */
    protected $resourceModel;

    public function __construct()
    {
        $this->resourceModel = Mage::getResourceModel('vsf_indexer/catalog_product_custom');
    }

    public function addData(array $indexData, $storeId)
    {
        $this->resourceModel->clear();
        $this->resourceModel->setProducts($indexData);
        $productCustomOptions = $this->resourceModel->loadCustomOptions();

        foreach ($productCustomOptions as $productId => $customOptions) {
            $indexData[$productId]['custom_options'] = [];

            foreach ($customOptions as $option) {
                $indexData[$productId]['custom_options'][] = $option;
            }
        }

        $this->resourceModel->clear();
        $productCustomOptions = null;

        return $indexData;
    }
}
