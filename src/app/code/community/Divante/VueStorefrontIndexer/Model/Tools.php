<?php

use Divante_VueStorefrontIndexer_Api_IndexerInterface as IndexerInterface;
use Divante_VueStorefrontIndexer_Api_Indexer_UpdateInterface as IndexerUpdateInterface;
use Divante_VueStorefrontIndexer_Model_Index_Operations as IndexOperation;
use Divante_VueStorefrontIndexer_Model_Index_ResolveIndex as ResolveIndex;
use Divante_VueStoreFrontIndexer_Model_Event_Delete as DeleteEvent;

/**
 * Class Divante_VueStorefrontIndexer_Model_Tools
 *
 * @package     Divante
 * @category    VueStoreFrontIndexer
 * @author      Agata Firlejczyk <afirlejczyk@divante.pl
 * @copyright   Copyright (C) 2018 Divante Sp. z o.o.
 * @license     See LICENSE_DIVANTE.txt for license details.
 */
class Divante_VueStorefrontIndexer_Model_Tools
{

    /**
     * Skip reindex for partial product/category update
     * @var array
     */
    private $notAllowedTypes = [
        'product_categories',
        'category_grid_per_page',
    ];

    /**
     * @var string
     */
    const MAPPING_CONF_ROOT_NODE = 'global/vsf_indexer/indexer';

    /**
     * @var IndexOperation
     */
    private $indexOperation;

    /**
     * @var ResolveIndex
     */
    private $resolveIndex;

    /**
     * @var deleteEvent
     */
    private $deleteEvent;

    /**
     * Divante_VueStorefrontIndexer_Model_Tools constructor.
     */
    public function __construct()
    {
        $this->indexOperation = Mage::getSingleton('vsf_indexer/index_operations');
        $this->resolveIndex = Mage::getSingleton('vsf_indexer/index_resolveindex');
        $this->deleteEvent = Mage::getSingleton('vsf_indexer/event_delete');
    }

    /**
     * Force full reindex
     *
     * @param null|int $storeId
     */
    public function fullReindex($storeId)
    {
        $mappingConfig = Mage::getConfig()->getNode(self::MAPPING_CONF_ROOT_NODE)->asArray();
        $types = array_keys($mappingConfig);

        /**
         * It will create new index index where data will be pushed, at the
         */
        $index = $this->getIndex($storeId, true);

        foreach ($types as $type) {
            if (!in_array($type, $this->notAllowedTypes)) {
                $this->runFullReindexByType($type, $storeId);
            }
        }

        $this->indexOperation->refreshIndex($index);
        $this->indexOperation->switchIndexer($index->getName(), $index->getIdentifier());
    }

    /**
     * @param      $storeId
     * @param bool $createNew
     *
     * @return Divante_VueStorefrontIndexer_Model_Index_Index
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function getIndex($storeId, $createNew = false)
    {
        $store = Mage::app()->getStore($storeId);

        return $this->resolveIndex->getIndex($store, $createNew);
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function checkIfTypeAvailable($type)
    {
        $mappingConfig = Mage::getConfig()->getNode(self::MAPPING_CONF_ROOT_NODE)->asArray();

        return isset($mappingConfig[$type]);
    }

    /**
     * @param      $type
     * @param int|null $storeId
     *
     * @return Divante_VueStorefrontIndexer_Model_Index_Index
     */
    public function runFullReindexByType($type, $storeId = null)
    {
        $index = $this->getIndex($storeId);

        $mappingConfig = Mage::getConfig()->getNode(self::MAPPING_CONF_ROOT_NODE)->asArray();

        if (isset($mappingConfig[$type])) {
            $config = $mappingConfig[$type];
            $class = $config['class'];
            $model = Mage::getModel($class);

            /** @var IndexerInterface $model */
            if ($model instanceof IndexerInterface) {
                $model->updateDocuments($storeId);
                $this->deleteEvent->execute($type);
            }
        }

        if ($index->isNew()) {
            $this->indexOperation->switchIndexer($index->getName(), $index->getIdentifier());
        }

        return $index;
    }

    /**
     * Reindex data in real time
     */
    public function reindex($storeId = null)
    {
        $mappingConfig = Mage::getConfig()->getNode(self::MAPPING_CONF_ROOT_NODE)->asArray();

        $types = [];

        foreach ($mappingConfig as $config) {
            $class = $config['class'];
            $model = Mage::getModel($class);

            if ($model instanceof IndexerInterface || $model instanceof IndexerUpdateInterface) {
                $type = $model->getTypeName();
                $types[$type] = $model;
            }
        }

        /**
         * @var string $type
         * @var IndexerInterface $model
         */
        foreach ($types as $type => $model) {
            $fullIndexing = $this->runFullIndexing($type);

            if ($fullIndexing) {
                $model->updateDocuments($storeId);
                $this->deleteEvent->execute($type);
            } else {
                $this->runPartialIndexing($model, $storeId);
            }
        }
    }

    /**
     * @param string $entityType
     *
     * @return bool
     */
    protected function runFullIndexing($entityType)
    {
        /** @var Divante_VueStorefrontIndexer_Model_Resource_Event_Collection $collection */
        $collection = Mage::getResourceModel('vsf_indexer/event_collection');
        $collection->addFieldToFilter('entity', $entityType);
        $collection->setPageSize(1);
        $collection->addFieldToFilter('type', 'full');

        $values = $collection->getColumnValues('type');

        if (!empty($values)) {
            return true;
        }

        return false;
    }

    /**
     * @param Divante_VueStorefrontIndexer_Api_IndexerInterface|Divante_VueStorefrontIndexer_Api_Indexer_UpdateInterface $indexerModel
     * @param int|null $storeId
     */
    protected function runPartialIndexing($indexerModel, $storeId = null)
    {
        $type = $indexerModel->getTypeName();

        do {
            $ids = $this->getUpdateEventLists($type, 'delete');

            if (!empty($ids)) {
                $indexerModel->deleteDocuments($storeId, array_values($ids));
                $this->deleteEvent->execute($type, array_keys($ids));
            }
        } while (!empty($ids));

        do {
            $ids = $this->getUpdateEventLists($type);

            if (!empty($ids)) {
                $indexerModel->updateDocuments($storeId, array_values($ids));
                $this->deleteEvent->execute($type, array_keys($ids));
            }
        } while (!empty($ids));
    }

    /**
     * @param string $entityType
     * @param string $eventType
     *
     * @return array
     */
    protected function getUpdateEventLists($entityType, $eventType = 'save')
    {
        $limit = $this->indexOperation->getBatchIndexingSize();
        /** @var Divante_VueStorefrontIndexer_Model_Resource_Event_Collection $collection */
        $collection = Mage::getResourceModel('vsf_indexer/event_collection');
        $collection->addFieldToFilter('entity', $entityType);
        $collection->setPageSize($limit);
        $collection->addFieldToFilter('type', $eventType);
        $collection->setOrder('created_at', 'ASC');
        $events = [];

        /** @var Divante_VueStorefrontIndexer_Model_Event $item */
        foreach ($collection->getItems() as $item) {
            $events[$item->getId()]  = $item->getData('entity_pk');
        }

        return $events;
    }
}
