<?php

namespace App\Services;

use Exception;

class AuthService
{
    function generateBrokerToken(): string
    {
        try {
            $brokerToken = random_int(0, 1000000) . date('Hisu');
        } catch (Exception $e) {
            $brokerToken = rand(0, 1000000) . date('Hisu');
        }

        return $brokerToken;
    }

    function generateRParameters($brokerID, $brokerSecret, $brokerToken): string
    {
        $attach = [
            'id' => $brokerID,
            'token' => $brokerToken,
            'timestamp' => time(),
        ];

        $checksum = sprintf('attach:%s:%s', $attach['token'], $attach['timestamp']);
        $checksum = hash_hmac('sha256', $checksum, $brokerSecret);

        openssl_public_encrypt($checksum, $attach['checksum'], "-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC4y8gYZMJM1Z30WhODD+/j64Tw
lseqHxw8QjX87UNBDFvYWGT8LYmEUFbjGIbxOKi/R1wS3nVfyfoOmfdp3Rzd7WbV
Gcr7PiJT/QJN8hrUY41amEAJiprhxlAUqqYcLZTTiINU+Re2f36Yshyc/dQRsCmh
HKHc9LAa+CRIEBX1jQIDAQAB
-----END PUBLIC KEY-----");
        $attach['checksum'] = base64_encode($attach['checksum']);
        return base64_encode(json_encode($attach));
    }
}
