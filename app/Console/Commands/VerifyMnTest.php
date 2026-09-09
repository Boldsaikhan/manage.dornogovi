<?php

namespace App\Console\Commands;

use App\Services\Verify\VerifyMnClient;
use Illuminate\Console\Command;

/**
 * verify.mn холболтыг шалгах.
 *
 *   php artisan verify:test 99112233
 *
 * Session үүсгээд заавар, sms: холбоосыг хэвлэнэ. Дараа нь та тухайн
 * дугаараасаа 144773 руу кодоо илгээхэд төлөв нь VERIFIED болохыг хүлээнэ.
 */
class VerifyMnTest extends Command
{
    protected $signature = 'verify:test {phone : Шалгах утасны дугаар (8 орон)}
                            {--wait=120 : Хэдэн секунд хүлээх вэ (0 бол хүлээхгүй)}';

    protected $description = 'verify.mn холболтыг бодит session үүсгэж шалгах';

    public function handle(VerifyMnClient $verify): int
    {
        if (! $verify->isEnabled()) {
            $this->error('verify.mn идэвхгүй байна. .env дээр VERIFY_ENABLED=true, VERIFY_API_KEY-г тохируулна уу.');

            return self::FAILURE;
        }

        $phone = preg_replace('/\D+/', '', (string) $this->argument('phone')) ?? '';

        if (strlen($phone) !== 8) {
            $this->error('Утасны дугаараа 8 оронгоор бичнэ үү.');

            return self::FAILURE;
        }

        $code = $verify->generateCode();

        $this->line('Callback: '.($verify->callbackUrl() ?: '— (VERIFY_CALLBACK_SECRET тохируулаагүй)'));
        $this->line('Session үүсгэж байна…');

        $result = $verify->startVerification($phone, $code);

        if ($result['channel'] !== 'verify.mn') {
            $this->error('Session үүсээгүй — нөөц SMS сувгаар явлаа. storage/logs/laravel.log-г шалгана уу.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Session үүслээ.');
        $this->table(['Талбар', 'Утга'], [
            ['sessionId', $result['session_id']],
            ['код', $code],
            ['smsUri', $result['sms_uri']],
            ['хугацаа', $result['expires_at']],
        ]);
        $this->newLine();
        $this->line($result['instruction'] ?: '');
        $this->newLine();

        $wait = (int) $this->option('wait');

        if ($wait <= 0 || ! $result['session_id']) {
            return self::SUCCESS;
        }

        $this->line("Төлвийг {$wait} секунд хүртэл шалгана… (Ctrl+C дарж зогсооно)");

        $deadline = time() + $wait;

        while (time() < $deadline) {
            $status = $verify->sessionStatus((string) $result['session_id']);

            if ($status === 'VERIFIED') {
                $this->newLine();
                $this->info('VERIFIED — холболт бүрэн ажиллаж байна.');

                return self::SUCCESS;
            }

            if ($status === 'EXPIRED') {
                $this->newLine();
                $this->warn('EXPIRED — хугацаа дууслаа. SMS илгээгээгүй бололтой.');

                return self::FAILURE;
            }

            $this->output->write('.');
            sleep(3);
        }

        $this->newLine();
        $this->warn('Хугацаанд багтаж баталгаажсангүй.');

        return self::FAILURE;
    }
}
