<?php declare(strict_types=1);

interface Divante_VueStorefrontIndexer_Interfaces_ConfigReaderInterface
{
    public function getParameter(string $path, $store = null): ?string;
}