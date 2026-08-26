<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateWebPushVapid extends Command
{
    protected $signature = 'webpush:vapid {--force : Дахин үүсгэх}';

    protected $description = 'Web Push VAPID түлхүүр үүсгэж storage/app/webpush-vapid.json-д хадгална';

    public function handle(): int
    {
        $path = storage_path('app/webpush-vapid.json');

        if (is_file($path) && ! $this->option('force')) {
            $this->warn('Түлхүүр аль хэдийн байна. Дахин үүсгэхийн тулд --force ашиглана.');

            return self::SUCCESS;
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $e) {
            $keys = $this->viaOpenSslCli();
            if (! $keys) {
                $this->error('VAPID үүсгэж чадсангүй: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        file_put_contents($path, json_encode([
            'publicKey' => $keys['publicKey'],
            'privateKey' => $keys['privateKey'],
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        @chmod($path, 0600);

        $this->info('VAPID түлхүүр хадгалагдлаа: storage/app/webpush-vapid.json');
        $this->line('Public key-ийг .env-д VAPID_PUBLIC_KEY=... болгож хуулж болно (эсвэл автоматаар файлыг уншина).');

        return self::SUCCESS;
    }

    /**
     * @return array{publicKey: string, privateKey: string}|null
     */
    private function viaOpenSslCli(): ?array
    {
        $openssl = 'C:\\xampp\\apache\\bin\\openssl.exe';
        if (! is_file($openssl)) {
            $openssl = 'openssl';
        }

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vapid-'.bin2hex(random_bytes(4));
        $privPem = $tmp.'-priv.pem';
        $pubPem = $tmp.'-pub.pem';

        exec(escapeshellarg($openssl).' ecparam -name prime256v1 -genkey -noout -out '.escapeshellarg($privPem).' 2>&1', $o1, $c1);
        if ($c1 !== 0 || ! is_file($privPem)) {
            return null;
        }

        exec(escapeshellarg($openssl).' ec -in '.escapeshellarg($privPem).' -pubout -out '.escapeshellarg($pubPem).' 2>&1', $o2, $c2);
        if ($c2 !== 0 || ! is_file($pubPem)) {
            @unlink($privPem);

            return null;
        }

        $priv = openssl_pkey_get_private((string) file_get_contents($privPem));
        $details = $priv ? openssl_pkey_get_details($priv) : false;
        @unlink($privPem);
        @unlink($pubPem);

        if (! $details || empty($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            return null;
        }

        $publicKey = rtrim(strtr(base64_encode("\x04".$details['ec']['x'].$details['ec']['y']), '+/', '-_'), '=');
        $privateKey = rtrim(strtr(base64_encode($details['ec']['d']), '+/', '-_'), '=');

        return ['publicKey' => $publicKey, 'privateKey' => $privateKey];
    }
}
