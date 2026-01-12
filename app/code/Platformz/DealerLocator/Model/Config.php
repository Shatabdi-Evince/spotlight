<?php
declare(strict_types=1);

namespace Platformz\DealerLocator\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_GOOGLE_API_KEY = 'platformz_dealerlocator/google/api_key';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function getGoogleApiKey(?int $storeId = null): ?string
    {
        // Allow env var override for deployments
        $env = getenv('GOOGLE_GEOCODING_API_KEY');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        $value = $this->scopeConfig->getValue(
            self::XML_PATH_GOOGLE_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // If stored using encrypted backend, attempt to decrypt.
        try {
            $decrypted = $this->encryptor->decrypt($value);
            if (is_string($decrypted) && trim($decrypted) !== '') {
                return trim($decrypted);
            }
        } catch (\Throwable) {
            // ignore and use raw value below
        }

        return $value;
    }
}

