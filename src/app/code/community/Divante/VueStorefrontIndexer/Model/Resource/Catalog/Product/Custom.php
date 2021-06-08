<?php

class Divante_VueStorefrontIndexer_Model_Resource_Catalog_Product_Custom
{

    /**
     * @var Mage_Core_Model_Resource
     */
    protected $resource;

    /**
     * @var Varien_Db_Adapter_Interface
     */
    protected $connection;

    /**
     * @var array
     */
    protected $products;

    /**
     * @var array
     */
    protected $productIds;

    /**
     * @var array
     */
    protected $customOptionsByProduct = [];

    public function __construct()
    {
        $this->resource = Mage::getSingleton('core/resource');
        $this->connection = $this->resource->getConnection('read');
    }

    public function setProducts(array $products)
    {
        $this->products = $products;
    }

    public function clear()
    {
        $this->products = null;
        $this->customOptionsByProduct = [];
        $this->productIds = null;
    }

    public function loadCustomOptions()
    {
        $this->initOptions();

        return $this->customOptionsByProduct;
    }

    protected function initOptions()
    {
        $productsMap = $this->getProductsMapFromCatalog($this->getProductIds());
        
        foreach ($this->products as $productData) {
            if (!isset($productsMap[$productData['id']])) {
                continue;
            }

            /**
             * @var Mage_Catalog_Model_Product $product
             */
            $product = $productsMap[$productData['id']];

            $options = $product->getProductOptionsCollection();

            if (!$options->count()) {
                continue;
            }

            $productOptions = [];
            /**
             * @var Mage_Catalog_Model_Product_Option $option
             */
            foreach ($options->getItems() as $option) {
                $productOptionValues = [];
                $optionValues = $option->getValuesCollection();
                if ($optionValues->count()) {
                    foreach ($optionValues->getItems() as $value) {
                        $productOptionValues[] = [
                            'option_type_id' => $value->getOptionTypeId(),
                            'title' => $value->getTitle(),
                            'price' => $value->getPrice(),
                            'price_type' => $value->getPriceType(),
                            'sort_order' => $value->getSortOrder()
                        ];
                    }
                }

                $productOptions[] = [
                    "product_sku" => $product->getSku(),
                    "option_id" => (int)$option->getId(),
                    "title" => $option->getTitle(),
                    "type" => $option->getType(),
                    "sort_order" => (int)$option->getSortOrder(),
                    "is_require" => (bool)$option->getIsRequire(),
                    "max_characters" => (int)$option->getMaxCharacters(),
                    "image_size_x" => (int)$option->getImageSizeX(),
                    "image_size_y" => (int)$option->getImageSizeY(),
                    "values" => $productOptionValues 
                ];
            }

            $this->customOptionsByProduct[$product->getId()] = $productOptions;
        }
    }

    private function getProductsMapFromCatalog(array $productIds): array
    {
        $collection = Mage::getResourceModel('catalog/product_collection') 
            ->addAttributeToFilter('entity_id', ['in' => $productIds]);

        return $collection->getItems();
    }

    private function getProductIds(): array
    {
        if (null === $this->productIds) {
            $this->productIds = [];

            foreach ($this->products as $productData) {
                $this->productIds[] = $productData['id'];
            }
        }

        return $this->productIds;
    }
}
