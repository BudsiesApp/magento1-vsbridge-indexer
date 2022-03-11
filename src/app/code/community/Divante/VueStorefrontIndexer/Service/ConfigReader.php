<?php declare(strict_types=1);

class Divante_VueStorefrontIndexer_Service_ConfigReader 
implements Divante_VueStorefrontIndexer_Interfaces_ConfigReaderInterface
{
    public function getParameter(string $path, $store = null): ?string
    {
        return Mage::getStoreConfig($path, $store);
    }
}