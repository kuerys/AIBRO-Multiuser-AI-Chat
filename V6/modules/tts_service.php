<?php
// modules/tts_service.php (v6.0 - 2025.11 企業級 TTS 模組)
namespace Modules;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use function React\Promise\reject;

/**
 * TTS 語音服務模組
 * - 支援自訂 TTS 服務 (預設 http://127.0.0.1:5005/speak)
 * - Redis 防重複 + 速率限制 (每人 5 次/分)
 * - 語音快取 (TTL 7 天，節省資源)
 * - 語音 URL 簽名 (防盜連)
 * - 自動清理過期快取
 */
class TTSService
{
    private \React\EventLoop\LoopInterface $loop;
    private LoggerInterface $logger;
    private $httpClient;
    private string $ttsUrl;
    private string $cacheDir = 'tts_cache';
    private int $cacheTtl = 604800; // 7 天

    public function __construct(
        \React\EventLoop\LoopInterface $loop,
        LoggerInterface $logger,
        $httpClient
    ) {
        $this->loop = $loop;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->ttsUrl = rtrim($_ENV['TTS_SERVICE_URL'] ?? 'http://127.0.0.1:5005', '/') . '/speak';

        // 確保快取目錄存在
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        // 定期清理過期快取 (每天 02:00)
        $this->scheduleCacheCleanup();
    }

    /**
     * 語音生成主流程
     * @param string $text 原始文字
     * @param string $clientId 用戶識別碼 (resourceId)
     * @return PromiseInterface<string|null> 回傳語音 URL 或 null
     */
    public function speak(string $text, string $clientId): PromiseInterface
    {
        $text = trim($text);
        if (empty($text)) {
            return resolve(null);
        }

        $hash = md5($text);
        $cacheKey = "tts:cache:{$hash}";
        $lockKey = "tts:lock:{$clientId}_{$hash}";
        $rateKey = "tts:rate:{$clientId}";

        return $this->checkRateLimit($rateKey)->then(function () use ($cacheKey, $lockKey, $text, $hash) {
            // 檢查快取
            return $this->getCachedAudioUrl($hash)->then(function ($cachedUrl) use ($lockKey, $text, $hash, $cacheKey) {
                if ($cachedUrl) {
                    $this->logger->debug("TTS 快取命中", ['text' => mb_substr($text, 0, 30), 'hash' => $hash]);
                    return resolve($cachedUrl);
                }

                // 防重複鎖
                return $this->acquireLock($lockKey)->then(function () use ($text, $hash, $cacheKey, $lockKey) {
                    return $this->generateAudio($text)->then(function ($audioPath) use ($hash, $cacheKey, $lockKey) {
                        if (!$audioPath) {
                            $this->releaseLock($lockKey);
                            return resolve(null);
                        }

                        $url = $this->generateSignedUrl($audioPath, $hash);
                        $this->saveCache($cacheKey, $url, $audioPath);
                        $this->releaseLock($lockKey);

                        $this->logger->info("TTS 生成成功", [
                            'text' => mb_substr($text, 0, 50),
                            'hash' => $hash,
                            'audio_path' => $audioPath
                        ]);

                        return resolve($url);
                    });
                })->otherwise(function ($e) use ($lockKey) {
                    $this->releaseLock($lockKey);
                    throw $e;
                });
            });
        });
    }

    /**
     * 速率限制：每人 5 次/分鐘
     */
    private function checkRateLimit(string $rateKey): PromiseInterface
    {
        return $this->redis->incr($rateKey)->then(function ($count) use ($rateKey) {
            if ($count == 1) {
                $this->redis->expire($rateKey, 60);
            }
            if ($count > 5) {
                $this->logger->warning("TTS 速率限制觸發", ['key' => $rateKey, 'count' => $count]);
                return reject(new \Exception("TTS 請求過於頻繁，請稍候 1 分鐘"));
            }
            return resolve();
        });
    }

    /**
     * 取得快取語音 URL
     */
    private function getCachedAudioUrl(string $hash): PromiseInterface
    {
        $cacheFile = "{$this->cacheDir}/{$hash}.json";
        if (!file_exists($cacheFile)) {
            return resolve(null);
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        if (!$data || $data['expires'] < time()) {
            @unlink($cacheFile);
            return resolve(null);
        }

        return resolve($data['url']);
    }

    /**
     * 呼叫外部 TTS API
     */
    private function generateAudio(string $text): PromiseInterface
    {
        $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($payload)
        ];

        $this->logger->debug("呼叫 TTS API", ['text' => mb_substr($text, 0, 50), 'url' => $this->ttsUrl]);

        return $this->httpClient->post($this->ttsUrl, $headers, $payload)->then(
            function (ResponseInterface $response) {
                $body = (string) $response->getBody();
                $json = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE || ($json['status'] ?? 'error') !== 'ok') {
                    $this->logger->warning("TTS API 回應錯誤", ['response' => $body]);
                    return resolve(null);
                }

                $file = $json['file'] ?? null;
                if (!$file || !file_exists($file)) {
                    $this->logger->warning("TTS 音檔不存在", ['file' => $file]);
                    return resolve(null);
                }

                return resolve($file);
            },
            function ($e) {
                $this->logger->error("TTS API 請求失敗", ['error' => $e->getMessage()]);
                return resolve(null);
            }
        );
    }

    /**
     * 生成簽名 URL (防盜連，7 天有效)
     */
    private function generateSignedUrl(string $audioPath, string $hash): string
    {
        $filename = basename($audioPath);
        $publicPath = "tts_cache/{$hash}.mp3";
        $target = __DIR__ . '/../' . $publicPath;

        // 複製到公開目錄
        @copy($audioPath, $target);

        $expires = time() + $this->cacheTtl;
        $token = hash_hmac('sha256', "{$publicPath}|{$expires}", $_ENV['TTS_SECRET'] ?? 'default-secret');
        return "/{$publicPath}?token={$token}&expires={$expires}";
    }

    /**
     * 儲存快取 meta
     */
    private function saveCache(string $cacheKey, string $url, string $audioPath): void
    {
        $meta = [
            'url' => $url,
            'expires' => time() + $this->cacheTtl,
            'generated_at' => time(),
            'original_file' => $audioPath
        ];

        $cacheFile = "{$this->cacheDir}/" . str_replace('tts:cache:', '', $cacheKey) . '.json';
        @file_put_contents($cacheFile, json_encode($meta, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 取得鎖 (防重複生成)
     */
    private function acquireLock(string $lockKey): PromiseInterface
    {
        return $this->redis->set($lockKey, '1', ['nx', 'ex' => 30])->then(function ($result) use ($lockKey) {
            if ($result === null) {
                return reject(new \Exception("語音生成中，請稍候"));
            }
            return resolve();
        });
    }

    private function releaseLock(string $lockKey): void
    {
        $this->redis->del($lockKey);
    }

    /**
     * 定期清理過期快取
     */
    private function scheduleCacheCleanup(): void
    {
        $this->loop->addPeriodicTimer(86400, function () {
            $now = time();
            $dir = realpath($this->cacheDir);
            if (!$dir) return;

            foreach (glob("{$dir}/*.json") as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && $data['expires'] < $now) {
                    @unlink($file);
                    $audioFile = str_replace('.json', '.mp3', $file);
                    @unlink($audioFile);
                }
            }

            $this->logger->info("TTS 快取清理完成", ['dir' => $dir]);
        });
    }
}