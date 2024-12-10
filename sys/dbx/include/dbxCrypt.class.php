<?php

class dbxCrypt {
    private $encryptionKey;
    private $iv;

    function __construct($encryptionKey) {
        // Initialization vector for encryption (16 bytes for AES-128, 24 bytes for AES-192, 32 bytes for AES-256)
        $this->iv = openssl_random_pseudo_bytes(16);
        $this->encryptionKey = $encryptionKey;
    }

    function encryptValue($value) {
        $encryptedValue = openssl_encrypt($value, 'aes-256-cbc', $this->encryptionKey, 0, $this->iv);
        // Combine IV and encrypted value for storage (IV should be unique for each encryption)
        return base64_encode($this->iv . $encryptedValue);
    }

    function decryptValue($encryptedValue) {
        // Separate IV and encrypted value
        $data = base64_decode($encryptedValue);
        $iv = substr($data, 0, 16);
        $encryptedData = substr($data, 16);
        // Decrypt using the IV and encryption key
        return openssl_decrypt($encryptedData, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
    }
}

/*
// Example usage
$encryptionKey = "YourEncryptionKey";
$helper = new EncryptionHelper($encryptionKey);

// Encrypt a value
$originalValue = "SensitiveData";
$encryptedValue = $helper->encryptValue($originalValue);
echo "Encrypted Value: " . $encryptedValue . PHP_EOL;

// Decrypt the encrypted value
$decryptedValue = $helper->decryptValue($encryptedValue);
echo "Decrypted Value: " . $decryptedValue . PHP_EOL;
*/


?>