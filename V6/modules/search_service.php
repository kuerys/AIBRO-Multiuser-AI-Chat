<?php
// modules/search_service.php (v6.0-6 - 防 then() on false)
namespace Modules;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class SearchService
{
    private LoopInterface $loop;
    private LoggerInterface $logger;
    private Browser $httpClient;
    private $redis;
    private array $keywords = [];
    private int $cacheTtl = 300;
    private string $searxUrl;
    private int $maxResults = 3;

    public function __construct(
        LoopInterface $loop,
        LoggerInterface $logger,
        Browser $httpClient,
        $redis
    ) {
        $this->loop = $loop;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->redis = $redis;

        $this->searxUrl = rtrim($_ENV['SEARXNG_URL'] ?? 'http://127.0.0.1:8888', '/');
        $this->loadKeywords();
    }

    private function loadKeywords(): void
    {
        $file = __DIR__ . '/keywords.txt';
        if (!file_exists($file)) {
            $this->keywords = ['最新', '新聞', '天氣', '股價', '匯率', '比賽', '賽事', '即時'];
            return;
        }

        $content = file_get_contents($file);
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $this->keywords = array_values($lines);
    }

    public function shouldSearch(string $prompt): bool
    {
        $prompt = mb_strtolower($prompt);
        foreach ($this->keywords as $kw) {
            if (mb_strpos($prompt, mb_strtolower($kw)) !== false) {
                return true;
            }
        }
        return false;
    }

    public function search(string $prompt): PromiseInterface
    {
        $cacheKey = 'search:' . md5($prompt);
        $cached = $this->getCache($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $url = $this->searxUrl . '/search?q=' . urlencode($prompt) . '&format=json&categories=general&engines=google,bing,duckduckgo';

        return $this->httpClient->get($url)->then(
            function ($response) use ($prompt, $cacheKey) {
                $body = (string) $response->getBody();
                $data = json_decode($body, true);

                if (!is_array($data) || empty($data['results'])) {
                    $this->logger->info("SearxNG 無結果", ['prompt' => $prompt]);
                    return resolve('');
                }

                $results = array_slice($data['results'], 0, $this->maxResults);
                $summary = "【即時搜尋摘要】\n";
                foreach ($results as $i => $r) {
                    $title = trim($r['title'] ?? '');
                    $url = $r['url'] ?? '';
                    $snippet = trim(strip_tags($r['content'] ?? ''));
                    $snippet = mb_substr($snippet, 0, 120) . (mb_strlen($snippet) > 120 ? '...' : '');
                    $summary .= ($i + 1) . ". {$title}\n   {$snippet}\n   來源: {$url}\n\n";
                }

                $this->setCache($cacheKey, $summary);
                return resolve($summary);
            },
            function ($e) use ($prompt) {
                $this->logger->warning("搜尋失敗", ['prompt' => $prompt, 'error' => $e->getMessage()]);
                return resolve('');
            }
        );
    }

    private function getCache(string $key): PromiseInterface|false
    {
        if (!method_exists($this->redis, 'get')) {
            return false;
        }

        $promise = $this->redis->get($key);
        if ($promise === false) {
            return false;
        }

        return $promise->then(function ($data) {
            return $data !== null ? resolve($data) : false;
        })->otherwise(fn() => false);
    }

    private function setCache(string $key, string $value): void
    {
        if (!method_exists($this->redis, 'set')) return;

        $promise = $this->redis->set($key, $value);
        if ($promise === false) return;

        $promise->then(function () use ($key) {
            if (method_exists($this->redis, 'expire')) {
                $this->redis->expire($key, $this->cacheTtl);
            }
        });
    }
}