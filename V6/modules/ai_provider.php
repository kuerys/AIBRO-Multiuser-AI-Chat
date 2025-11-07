<?php
// modules/ai_provider.php (v6.0-14 - 修復 undefined $providerName)
namespace Modules;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class AIProvider
{
    private LoopInterface $loop;
    private LoggerInterface $logger;
    private Browser $httpClient;
    private array $providers;
    private array $fallbackOrder;

    public function __construct(
        LoopInterface $loop,
        LoggerInterface $logger,
        array $providers,
        array $fallbackOrder,
        Browser $httpClient
    ) {
        $this->loop = $loop;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->providers = $providers;
        $this->fallbackOrder = array_filter($fallbackOrder, fn($p) => !empty($this->providers[$p]['key'] ?? '') || $p === 'ollama');
    }

    public function generate(array $messages, float $temperature = 0.7): PromiseInterface
    {
        if (empty($this->fallbackOrder)) {
            return resolve(['content' => 'AI 服務未設定金鑰']);
        }

        $providerName = array_shift($this->fallbackOrder);
        return $this->callProvider($providerName, $messages, $temperature)
            ->otherwise(function ($e) use ($messages, $temperature, $providerName) {
                $this->logger->warning("AI 提供者失敗，切換後備", [
                    'provider' => $providerName,
                    'error' => $e->getMessage()
                ]);
                if (empty($this->fallbackOrder)) {
                    return resolve(['content' => '所有 AI 服務暫時無法使用']);
                }
                return $this->generate($messages, $temperature);
            });
    }

    private function callProvider(string $name, array $messages, float $temperature): PromiseInterface
    {
        $config = $this->providers[$name] ?? null;
        if (!$config) {
            return resolve(['content' => "AI 提供者 {$name} 未設定"]);
        }

        $this->logger->info("呼叫 AI 提供者", ['provider' => $name, 'model' => $config['model']]);

        $url = $config['base_url'] . $config['endpoint'];
        $headers = ['Content-Type' => 'application/json'];
        if ($config['key']) {
            $headers['Authorization'] = "Bearer {$config['key']}";
        }

        $body = [
            'model' => $config['model'],
            'temperature' => $temperature,
            'max_tokens' => 1024
        ];

        if ($name === 'google') {
            $body['contents'] = array_map(fn($m) => [
                'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]]
            ], $messages);
            $url = str_replace(':generateContent', '/' . $config['model'] . ':generateContent', $url) . '?key=' . $config['key'];
        } elseif ($name === 'ollama') {
            $body['messages'] = $messages;
            $body['stream'] = false;
        } else {
            $body['messages'] = $messages;
        }

        return $this->httpClient->post($url, $headers, json_encode($body))->then(
            function ($response) use ($name) {
                $data = json_decode((string)$response->getBody(), true);
                $content = match ($name) {
                    'google' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
                    'ollama' => $data['message']['content'] ?? '',
                    default => $data['choices'][0]['message']['content'] ?? ''
                };
                return resolve(['content' => trim($content)]);
            },
            fn($e) => reject($e)
        );
    }
}