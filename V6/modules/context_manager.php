<?php
// modules/context_manager.php (v6.0-6 修正版 - 防 then() on false)
namespace Modules;

use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class ContextManager
{
    private \React\EventLoop\LoopInterface $loop;
    private $redis;
    private LoggerInterface $errorLogger;
    private bool $fileFallback = false;
    private string $fallbackDir = 'cache/context';
    private int $maxTokens = 12000;
    private int $contextTtl = 86400;

    public function __construct(
        \React\EventLoop\LoopInterface $loop,
        $redis,
        LoggerInterface $errorLogger
    ) {
        $this->loop = $loop;
        $this->redis = $redis;
        $this->errorLogger = $errorLogger;
    }

    public function enableFileFallback(): void
    {
        $this->fileFallback = true;
        @mkdir($this->fallbackDir, 0755, true);
        $this->errorLogger->info("ContextManager 啟用檔案降級", ['dir' => $this->fallbackDir]);
    }

    public function load(string $room_id): PromiseInterface
    {
        $key = "aibro:context:{$room_id}";

        // 降級模式
        if ($this->fileFallback) {
            $file = "{$this->fallbackDir}/{$room_id}.json";
            $data = file_exists($file) ? @file_get_contents($file) : false;
            $decoded = $data !== false ? json_decode($data, true) : [];
            return resolve(is_array($decoded) ? $decoded : []);
        }

        // Redis 正常模式
        if (!method_exists($this->redis, 'get')) {
            $this->errorLogger->warning("Redis 物件無 get 方法，啟用降級", ['room_id' => $room_id]);
            $this->enableFileFallback();
            return $this->load($room_id);
        }

        $promise = $this->redis->get($key);

        if ($promise === false) {
            $this->errorLogger->warning("Redis get 返回 false，啟用降級", ['room_id' => $room_id]);
            $this->enableFileFallback();
            return $this->load($room_id);
        }

        return $promise->then(function ($data) use ($key, $room_id) {
            if ($data === null) return resolve([]);

            $decoded = json_decode($data, true);
            if (!is_array($decoded)) {
                $this->errorLogger->warning("Context JSON 解析失敗", ['room_id' => $room_id]);
                return resolve([]);
            }

            if (method_exists($this->redis, 'expire')) {
                $this->redis->expire($key, $this->contextTtl);
            }
            return resolve($decoded);
        })->otherwise(function ($e) use ($room_id) {
            $this->errorLogger->warning("Redis 載入失敗，啟用降級", [
                'room_id' => $room_id,
                'error' => $e->getMessage()
            ]);
            $this->enableFileFallback();
            return $this->load($room_id);
        });
    }

    public function save(string $room_id, array $messages): PromiseInterface
    {
        $optimized = $this->optimizeMessages($messages);
        $data = json_encode($optimized, JSON_UNESCAPED_UNICODE);

        if ($data === false) {
            $this->errorLogger->error("Context JSON 編碼失敗", ['room_id' => $room_id]);
            return resolve(false);
        }

        $key = "aibro:context:{$room_id}";

        if ($this->fileFallback) {
            $file = "{$this->fallbackDir}/{$room_id}.json";
            $result = @file_put_contents($file, $data) !== false;
            return resolve($result);
        }

        if (!method_exists($this->redis, 'set')) {
            $this->enableFileFallback();
            return $this->save($room_id, $messages);
        }

        $promise = $this->redis->set($key, $data);

        if ($promise === false) {
            $this->enableFileFallback();
            return $this->save($room_id, $messages);
        }

        return $promise->then(function () use ($key) {
            if (method_exists($this->redis, 'expire')) {
                return $this->redis->expire($key, $this->contextTtl)->then(fn() => resolve(true));
            }
            return resolve(true);
        })->otherwise(function ($e) use ($room_id, $messages) {
            $this->errorLogger->warning("Redis 儲存失敗，啟用降級", [
                'room_id' => $room_id,
                'error' => $e->getMessage()
            ]);
            $this->enableFileFallback();
            return $this->save($room_id, $messages);
        });
    }

    public function clear(string $room_id): PromiseInterface
    {
        $key = "aibro:context:{$room_id}";

        if ($this->fileFallback) {
            $file = "{$this->fallbackDir}/{$room_id}.json";
            @unlink($file);
            return resolve(true);
        }

        if (!method_exists($this->redis, 'del')) {
            return resolve(true);
        }

        return $this->redis->del($key)->then(fn() => resolve(true));
    }

    private function optimizeMessages(array $messages): array
    {
        if (empty($messages)) return [];

        $system = [];
        $chat = [];
        $lastRole = null;

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'unknown';
            $content = trim($msg['content'] ?? '');

            if ($role === 'system') {
                $system[] = $msg;
                continue;
            }

            if ($content === '') continue;

            if ($lastRole === $role && in_array($role, ['user', 'assistant'])) {
                $chat[count($chat) - 1]['content'] .= "\n\n" . $content;
            } else {
                $chat[] = ['role' => $role, 'content' => $content];
            }
            $lastRole = $role;
        }

        $combined = array_merge($system, $chat);
        $tokens = $this->estimateTokens($combined);

        while ($tokens > $this->maxTokens && count($chat) > 2) {
            array_shift($chat);
            $combined = array_merge($system, $chat);
            $tokens = $this->estimateTokens($combined);
        }

        return $combined;
    }

    private function estimateTokens(array $messages): int
    {
        $text = '';
        foreach ($messages as $m) {
            $text .= ($m['content'] ?? '') . ' ';
        }

        $chinese = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text);
        $english = strlen($text) - ($chinese * 3);

        $tokens = ($chinese * 1.0) + ($english / 4) + count($messages) * 3;
        return (int) ceil($tokens);
    }
}