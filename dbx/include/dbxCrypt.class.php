<?php

/**
 * Kapselt die symmetrische AES-256-CBC-Verschlüsselung einzelner Werte.
 */
class dbxCrypt {
    private $encryption_key;
    private $iv;

    function __construct($encryption_key) {
        // Initialization vector for encryption (16 bytes for AES-128, 24 bytes for AES-192, 32 bytes for AES-256)
        $this->iv = openssl_random_pseudo_bytes(16);
        $this->encryption_key = $encryption_key;
    }

    function encrypt_value($value) {
        $encrypted_value = openssl_encrypt($value, 'aes-256-cbc', $this->encryption_key, 0, $this->iv);
        // Combine IV and encrypted value for storage (IV should be unique for each encryption)
        return base64_encode($this->iv . $encrypted_value);
    }

    function decrypt_value($encrypted_value) {
        // Separate IV and encrypted value
        $data = base64_decode($encrypted_value);
        $iv = substr($data, 0, 16);
        $encrypted_data = substr($data, 16);
        // Decrypt using the IV and encryption key
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $this->encryption_key, 0, $iv);
    }
}

/*
// Example usage
$encryption_key = "YourEncryptionKey";
$helper = new EncryptionHelper($encryption_key);

// Encrypt a value
$originalValue = "SensitiveData";
$encryptedValue = $helper->encrypt_value($originalValue);
echo "Encrypted Value: " . $encryptedValue . PHP_EOL;

// Decrypt the encrypted value
$decryptedValue = $helper->decrypt_value($encryptedValue);
echo "Decrypted Value: " . $decryptedValue . PHP_EOL;
*/


?>
