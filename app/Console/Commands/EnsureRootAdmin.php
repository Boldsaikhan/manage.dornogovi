<?php

namespace App\Console\Commands;

use App\Support\RootAdmin;
use Illuminate\Console\Command;

/**
 * Үндсэн супер админыг сэргээх / нууц үгийг нь шинэчлэх.
 *
 *   php artisan admin:root
 *   php artisan admin:root --password="..."
 */
class EnsureRootAdmin extends Command
{
    protected $signature = 'admin:root {--password= : Шинэ нууц үг (өгөхгүй бол хэвээр үлдэнэ)}';

    protected $description = 'Үндсэн супер админ бүртгэлийг үүсгэх, эрхийг нь баталгаажуулах';

    public function handle(): int
    {
        $password = $this->option('password');

        $user = RootAdmin::ensure($password !== null ? (string) $password : null);

        $this->info(sprintf(
            'Үндсэн супер админ: %s · нэвтрэх нэр %s · и-мэйл %s',
            $user->name,
            $user->phone,
            $user->email,
        ));

        if ($password !== null) {
            $this->info('Нууц үг шинэчлэгдлээ.');
        }

        return self::SUCCESS;
    }
}
