<?php

namespace Zinrelo\LoyaltyRewards\Framework\HTTP\Client;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\HTTP\Client\Curl as MainCurl;

class Curl extends MainCurl
{
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * Curl construct
     *
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(ProductMetadataInterface $productMetadata)
    {
        $this->productMetadata = $productMetadata;
    }

    /**
     * Parse headers - CURL callback function. Fixed for M230 version
     *
     * @param resource $ch curl handle, not needed
     * @param string $data
     * @return int
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function parseHeaders($ch, $data)
    {
        if ($this->_headerCount == 0) {
            $line = explode(" ", trim($data), 3);
            $version = $this->productMetadata->getVersion();
            if ($version == "2.3.0" && count($line) < 2) {
                $this->doError("Invalid response line returned from server: " . $data);
            } elseif (count($line) < 2) {
                $this->doError("Invalid response line returned from server: " . $data);
            }
            $this->_responseStatus = (int)$line[1];
        } else {
            $name = $value = '';
            $out = explode(": ", trim($data), 2);
            if (count($out) == 2) {
                $name = $out[0];
                $value = $out[1];
            }

            if (strlen($name)) {
                if ("Set-Cookie" == $name) {
                    if (!isset($this->_responseHeaders[$name])) {
                        $this->_responseHeaders[$name] = [];
                    }
                    $this->_responseHeaders[$name][] = $value;
                } else {
                    $this->_responseHeaders[$name] = $value;
                }
            }
        }
        $this->_headerCount++;

        return strlen($data);
    }
}
