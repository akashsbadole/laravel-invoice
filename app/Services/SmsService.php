<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\MessageLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Gateway-agnostic SMS sender. Every attempt is recorded in message_logs.
 * Drivers: log (default, nothing leaves the server), twilio, http (generic JSON POST).
 */
class SmsService
{
    /**
     * @param  array{customer_id?:int|null,invoice_id?:int|null,created_by?:int|null}  $context
     */
    public function send(string $to, string $message, array $context = []): MessageLog
    {
        $config = $this->resolveConfig();
        $driver = $config['driver'];
        $number = $this->normalize($to, $config['country_code']);

        $log = new MessageLog([
            'channel' => 'sms',
            'driver' => $driver,
            'to' => $number,
            'body' => $message,
            'status' => 'failed',
            'customer_id' => $context['customer_id'] ?? null,
            'invoice_id' => $context['invoice_id'] ?? null,
            'created_by' => $context['created_by'] ?? null,
        ]);

        try {
            if ($number === '+') {
                throw new RuntimeException('No valid phone number.');
            }

            match ($driver) {
                'twilio' => $this->sendTwilio($number, $message, $config['twilio']),
                'http' => $this->sendHttp($number, $message, $config['http']),
                default => Log::info("[SMS:log] to {$number}: {$message}"),
            };

            $log->status = in_array($driver, ['twilio', 'http'], true) ? 'sent' : 'logged';
        } catch (Throwable $e) {
            $log->status = 'failed';
            $log->error = mb_substr($e->getMessage(), 0, 500);
            Log::warning("SMS to {$number} failed: {$e->getMessage()}");
        }

        $log->save();

        return $log;
    }

    /**
     * Toast payload for a send attempt (matches the starter kit's flash toast shape).
     *
     * @return array{type:string,message:string}
     */
    public static function toast(MessageLog $log): array
    {
        return match ($log->status) {
            'sent' => ['type' => 'success', 'message' => __('SMS sent.')],
            'logged' => ['type' => 'info', 'message' => __('SMS was only logged (SMS_DRIVER=log). Configure a gateway to send for real.')],
            default => ['type' => 'error', 'message' => __('SMS failed: :error', ['error' => $log->error ?? 'unknown error'])],
        };
    }

    /**
     * Resolve SMS config: the current tenant's settings win when a tenant
     * context exists, otherwise the global .env configuration applies.
     *
     * @return array{driver: string, country_code: string, twilio: array{sid: ?string, token: ?string, from: ?string}, http: array{url: ?string, token: ?string, to_field: string, message_field: string}}
     */
    protected function resolveConfig(): array
    {
        $tenantId = Tenant::currentId();

        if ($tenantId) {
            $settings = BusinessSetting::query()->where('tenant_id', $tenantId)->first();

            if ($settings) {
                return [
                    'driver' => $settings->sms_driver ?: 'log',
                    'country_code' => $settings->sms_country_code ?: '91',
                    'twilio' => [
                        'sid' => $settings->sms_twilio_sid,
                        'token' => $settings->sms_twilio_token,
                        'from' => $settings->sms_twilio_from,
                    ],
                    'http' => [
                        'url' => $settings->sms_http_url,
                        'token' => $settings->sms_http_token,
                        'to_field' => $settings->sms_http_to_field ?: 'to',
                        'message_field' => $settings->sms_http_message_field ?: 'message',
                    ],
                ];
            }
        }

        return [
            'driver' => (string) config('sms.driver', 'log'),
            'country_code' => (string) config('sms.default_country_code', '91'),
            'twilio' => [
                'sid' => config('sms.twilio.sid'),
                'token' => config('sms.twilio.token'),
                'from' => config('sms.twilio.from'),
            ],
            'http' => [
                'url' => config('sms.http.url'),
                'token' => config('sms.http.token'),
                'to_field' => config('sms.http.to_field', 'to'),
                'message_field' => config('sms.http.message_field', 'message'),
            ],
        ];
    }

    /**
     * Normalise to E.164-ish: digits only, default country code for 10-digit numbers.
     */
    public function normalize(string $number, ?string $countryCode = null): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10) {
            $digits = ($countryCode ?? config('sms.default_country_code', '91')).$digits;
        }

        return '+'.$digits;
    }

    /**
     * @param  array{sid: ?string, token: ?string, from: ?string}  $config
     */
    protected function sendTwilio(string $to, string $message, array $config): void
    {
        $sid = $config['sid'];
        $token = $config['token'];
        $from = $config['from'];

        if (! $sid || ! $token || ! $from) {
            throw new RuntimeException('Twilio is not configured (TWILIO_SID / TWILIO_AUTH_TOKEN / TWILIO_FROM).');
        }

        Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $message,
            ])
            ->throw();
    }

    /**
     * @param  array{url: ?string, token: ?string, to_field: string, message_field: string}  $config
     */
    protected function sendHttp(string $to, string $message, array $config): void
    {
        $url = $config['url'];

        if (! $url) {
            throw new RuntimeException('SMS gateway URL is not configured.');
        }

        $request = Http::acceptJson();

        if ($token = $config['token']) {
            $request = $request->withToken($token);
        }

        $request->post($url, [
            $config['to_field'] => $to,
            $config['message_field'] => $message,
        ])->throw();
    }
}
