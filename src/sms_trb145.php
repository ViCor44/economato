<?php
/**
 * Cliente mínimo para envio de SMS através do modem Teltonika TRB145
 * (RutOS 7.x) usando a REST API oficial:
 *
 *   POST /api/login                       → obtém token JWT
 *   POST /api/messages/actions/send       → envia SMS (Bearer token)
 *
 * Uso:
 *   $cfg    = require __DIR__ . '/../config/sms.php';
 *   $client = new Trb145SmsClient($cfg);
 *   $client->sendSms('+351912345678', 'Mensagem de teste');
 */

declare(strict_types=1);

final class Trb145SmsClient
{
    /** @var array<string,mixed> */
    private array $cfg;

    private ?string $token = null;

    /** @param array<string,mixed> $cfg */
    public function __construct(array $cfg)
    {
        $required = ['host', 'user', 'password', 'modem_id'];
        foreach ($required as $k) {
            if (empty($cfg[$k])) {
                throw new RuntimeException("Trb145SmsClient: configuração '{$k}' em falta.");
            }
        }
        $this->cfg = $cfg;
    }

    /**
     * Normaliza um número de telefone para formato E.164.
     * - Remove espaços, hífens, parêntesis, pontos.
     * - Se começar por '00', substitui por '+'.
     * - Se não tiver '+' nem indicativo, aplica o country_code.
     * Devolve null se o resultado não for válido (+ seguido de 9-15 dígitos).
     */
    public static function normalizeNumber(string $raw, string $countryCode = '+351'): ?string
    {
        $clean = preg_replace('/[\s\-().]/', '', $raw) ?? '';
        if ($clean === '') {
            return null;
        }
        if (str_starts_with($clean, '00')) {
            $clean = '+' . substr($clean, 2);
        }

        $cc = preg_replace('/\D/', '', $countryCode) ?? '';

        if (str_starts_with($clean, '+')) {
            $n = $clean;
        } else {
            $digits = preg_replace('/\D/', '', $clean) ?? '';
            if ($digits === '') {
                return null;
            }
            // Se já vier com indicativo mas sem '+', só adiciona '+'
            if ($cc !== '' && str_starts_with($digits, $cc)) {
                $n = '+' . $digits;
            } else {
                $n = '+' . $cc . $digits;
            }
        }

        if (!preg_match('/^\+\d{9,15}$/', $n)) {
            return null;
        }
        return $n;
    }

    /**
     * Envia uma SMS. Devolve true se aceite pelo modem, false caso contrário.
     * A mensagem de erro fica em $errorOut (por referência).
     */
    public function sendSms(string $number, string $message, ?string &$errorOut = null): bool
    {
        try {
            if ($this->token === null) {
                $this->login();
            }

            $payload = [
                'data' => [
                    'modem'   => (string)$this->cfg['modem_id'],
                    'number'  => $number,
                    'message' => $message,
                ],
            ];

            [$status, $body] = $this->request(
                'POST',
                '/api/messages/actions/send',
                $payload,
                ['Authorization: Bearer ' . $this->token]
            );

            // Se o token expirou, tentar re-login uma vez.
            if ($status === 401) {
                $this->token = null;
                $this->login();
                [$status, $body] = $this->request(
                    'POST',
                    '/api/messages/actions/send',
                    $payload,
                    ['Authorization: Bearer ' . $this->token]
                );
            }

            $json = json_decode($body, true);
            if ($status >= 200 && $status < 300 && is_array($json) && !empty($json['success'])) {
                return true;
            }

            $errorOut = sprintf(
                'HTTP %d — %s',
                $status,
                is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : $body
            );
            return false;

        } catch (Throwable $e) {
            $errorOut = $e->getMessage();
            return false;
        }
    }

    /** Autentica no modem e guarda o token em memória. */
    private function login(): void
    {
        [$status, $body] = $this->request('POST', '/api/login', [
            'username' => (string)$this->cfg['user'],
            'password' => (string)$this->cfg['password'],
        ]);

        $json = json_decode($body, true);
        if ($status < 200 || $status >= 300 || !is_array($json) || empty($json['success'])) {
            throw new RuntimeException(
                'Login TRB145 falhou (HTTP ' . $status . '): ' .
                (is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : $body)
            );
        }

        // RutOS 7.x devolve token em data.token
        $token = $json['data']['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Login TRB145: token não retornado.');
        }
        $this->token = $token;
    }

    /**
     * @param array<string,mixed>|null $body
     * @param list<string>             $extraHeaders
     * @return array{0:int,1:string}
     */
    private function request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
    {
        $url = sprintf('%s://%s%s', $this->cfg['scheme'], $this->cfg['host'], $path);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init falhou.');
        }

        $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => (int)$this->cfg['timeout'],
            CURLOPT_CONNECTTIMEOUT => (int)$this->cfg['timeout'],
            CURLOPT_SSL_VERIFYPEER => (bool)$this->cfg['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => $this->cfg['verify_ssl'] ? 2 : 0,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Erro cURL: ' . $err);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, (string)$response];
    }
}
