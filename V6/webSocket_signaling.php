<?php
// webSocket_signaling.php (v6.0-7 - 最終版：AI 正常回應 + 前端穩定 + 無警告)
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/vendor/autoload.php';

require __DIR__ . '/modules/logger.php';
require __DIR__ . '/modules/search_service.php';
require __DIR__ . '/modules/ai_provider.php';
require __DIR__ . '/modules/tts_service.php';
require __DIR__ . '/modules/context_manager.php';
require __DIR__ . '/modules/ai_butler.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Psr\Log\NullLogger;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

class Chat implements MessageComponentInterface
{
    protected $clients;
    protected $rooms;
    protected $clientInfo;
    protected $aiContext;
    protected $rateLimits;
    protected $ttsLocks;
    protected $loop;
    protected $httpClient;
    protected $redis;
    protected $appLogger;
    protected $aiLogger;
    protected $errorLogger;
    protected $searchService;
    protected $aiProvider;
    protected $ttsService;
    protected $contextManager;
    protected $aiButler;
    protected $admins = ['admin_123'];

    protected $defaultSystemPrompt = "
你是 AIBRO，台灣風格的溫暖又專業 AI 助理，回答以繁體中文。
保持親切、尊重多元文化；若使用者用方言，可附翻譯補充。
涉及醫療或法律資訊時，加註「參考資訊，請諮詢專業人員」。
若問題缺乏即時資料，再引用本地搜尋摘要。
回覆風格：簡潔、有重點、語氣自然。
**【TTS 語音播報指示】**
1. 如果你的回覆中有**重要的發音、翻譯或關鍵解釋**。
2. 請將需要播報的文本用 **[TTS_START]** 和 **[TTS_END]** 標籤包住。
3. **只包住關鍵的短句或單字**，不要包住全文。
4. 例如：若要播報「無聊的梗」，請寫：[TTS_START]無聊的梗[TTS_END]。
    ";

    public function __construct(\React\EventLoop\LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->httpClient = (new \React\Http\Browser($loop))->withTimeout(10.0);
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->clientInfo = [];
        $this->aiContext = [];
        $this->rateLimits = [];
        $this->ttsLocks = [];

        $this->initRedis();
        $this->initLogs();

        $fallbackOrder = array_map('trim', explode(',', $_ENV['FALLBACK_ORDER'] ?? 'nvidia,groq,xai,openai,google,deepseek,ollama'));
        $providers = $this->buildProviders();

        $this->searchService = new \Modules\SearchService($loop, $this->aiLogger, $this->httpClient, $this->redis);
        $this->aiProvider = new \Modules\AIProvider($loop, $this->aiLogger, $providers, $fallbackOrder, $this->httpClient);
        $this->ttsService = new \Modules\TTSService($loop, $this->aiLogger, $this->httpClient);
        $this->contextManager = new \Modules\ContextManager($loop, $this->redis, $this->errorLogger);
        $this->aiButler = new \Modules\AIButler(
            $this->searchService,
            $this->aiProvider,
            $this->ttsService,
            $this->contextManager,
            $this->aiLogger,
            $this->defaultSystemPrompt
        );
    }

    private function initRedis()
    {
        $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $port = $_ENV['REDIS_PORT'] ?? 6379;

        try {
            $redis = new \Redis();
            if (!$redis->connect($host, (int)$port, 3.0)) throw new \Exception("連接失敗");
            $redis->ping();
            $this->redis = $redis;
            echo "Redis 連線成功 {$host}:{$port}\n";
        } catch (\Exception $e) {
            echo "Redis 連線失敗，啟用檔案降級\n";
            $this->errorLogger = new \Monolog\Logger('error');
            $this->errorLogger->pushHandler(new \Monolog\Handler\RotatingFileHandler('logs/error.log', 7, \Monolog\Logger::WARNING));

            $this->redis = new class {
                private $dir = 'cache/redis';
                public function __construct() { @mkdir($this->dir, 0755, true); }
                public function get($k) { $f = "{$this->dir}/{$k}"; return file_exists($f) ? unserialize(file_get_contents($f)) : null; }
                public function set($k, $v, $opts=[]) { file_put_contents("{$this->dir}/{$k}", serialize($v)); return true; }
                public function del($k) { @unlink("{$this->dir}/{$k}"); return 1; }
                public function incr($k) { $v = ($this->get($k) ?: 0) + 1; $this->set($k, $v); return $v; }
                public function expire($k, $t) { return true; }
                public function ping() { return 'PONG'; }
            };
        }
    }

    private function initLogs()
    {
        $logsDir = 'logs';
        @mkdir($logsDir, 0755, true);
        $logEnabled = filter_var($_ENV['LOG_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $nullLogger = new NullLogger();

        if ($logEnabled) {
            $this->appLogger = new \Monolog\Logger('app');
            $this->appLogger->pushHandler(new \Monolog\Handler\RotatingFileHandler("{$logsDir}/app.log", 7, \Monolog\Logger::INFO));
            $this->aiLogger = new \Monolog\Logger('ai');
            $this->aiLogger->pushHandler(new \Monolog\Handler\RotatingFileHandler("{$logsDir}/ai.log", 7, \Monolog\Logger::INFO));
        } else {
            $this->appLogger = $this->aiLogger = $nullLogger;
        }

        $this->errorLogger = new \Monolog\Logger('error');
        $this->errorLogger->pushHandler(new \Monolog\Handler\RotatingFileHandler("{$logsDir}/error.log", 7, \Monolog\Logger::WARNING));
    }

    private function buildProviders(): array
    {
        return [
            'xai' => ['key' => $_ENV['XAI_API_KEY'] ?? '', 'model' => $_ENV['XAI_AI_MODEL'] ?? 'grok-3-mini', 'base_url' => 'https://api.x.ai/v1', 'endpoint' => '/chat/completions'],
            'nvidia' => ['key' => $_ENV['NVIDIA_API_KEY'] ?? '', 'model' => $_ENV['NVIDIA_AI_MODEL'] ?? 'openai/gpt-oss-120b', 'base_url' => 'https://integrate.api.nvidia.com/v1', 'endpoint' => '/chat/completions'],
            'groq' => ['key' => $_ENV['GROQ_API_KEY'] ?? '', 'model' => $_ENV['GROQ_AI_MODEL'] ?? 'llama-3.1-70b', 'base_url' => 'https://api.groq.com/openai/v1', 'endpoint' => '/chat/completions'],
            'openai' => ['key' => $_ENV['OPENAI_API_KEY'] ?? '', 'model' => $_ENV['OPENAI_AI_MODEL'] ?? 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1', 'endpoint' => '/chat/completions'],
            'google' => ['key' => $_ENV['GOOGLE_API_KEY'] ?? '', 'model' => $_ENV['GOOGLE_AI_MODEL'] ?? 'gemini-1.5-flash', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models', 'endpoint' => ':generateContent'],
            'deepseek' => ['key' => $_ENV['DEEPSEEK_API_KEY'] ?? '', 'model' => $_ENV['DEEPSEEK_AI_MODEL'] ?? 'deepseek-chat', 'base_url' => 'https://api.deepseek.com/v1', 'endpoint' => '/chat/completions'],
            'ollama' => ['key' => '', 'model' => $_ENV['OLLAMA_AI_MODEL'] ?? 'aibo-tw:12b', 'base_url' => 'http://127.0.0.1:11434', 'endpoint' => '/api/chat']
        ];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $headers = $conn->httpRequest->getHeaders();
        $origin = $headers['Origin'][0] ?? '';
        $remoteIp = $conn->remoteAddress ?? '';
        $devMode = filter_var($_ENV['DEV_MODE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        if (!$devMode && !$this->isOriginAllowed($origin, $remoteIp)) {
            $this->errorLogger->warning("拒絕未授權連線", ['origin' => $origin, 'ip' => $remoteIp]);
            $conn->close();
            return;
        }

        $this->clients->attach($conn);
        $rid = $conn->resourceId;
        $this->clientInfo[$rid] = [
            'id' => uniqid('user_'),
            'nickname' => '訪客_' . rand(10, 99),
            'is_admin' => false,
            'room_id' => 'lobby'
        ];

        $room_id = 'lobby';
        if (!isset($this->rooms[$room_id])) {
            $this->rooms[$room_id] = new \SplObjectStorage;
            $this->aiContext[$room_id] = [];
            $this->contextManager->load($room_id)->then(function ($ctx) use ($room_id) {
                $this->aiContext[$room_id] = $ctx ?? [];
            });
        }

        $room = $this->rooms[$room_id];
        $room->attach($conn);

        $conn->send(json_encode([
            'type' => 'join_status',
            'room_id' => $room_id,
            'user_id' => $this->clientInfo[$rid]['id'],
            'nickname' => $this->clientInfo[$rid]['nickname']
        ], JSON_UNESCAPED_UNICODE));

        $this->broadcastUserList($room, $room_id);
        $this->appLogger->info("使用者加入", ['rid' => $rid, 'room_id' => $room_id, 'nickname' => $this->clientInfo[$rid]['nickname']]);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            if (!$data || !isset($data['type'])) {
                $from->send(json_encode(['type' => 'error', 'message' => '格式錯誤']));
                return;
            }

            $rid = $from->resourceId;
            $room_id = $this->clientInfo[$rid]['room_id'] ?? 'lobby';
            $room = $this->rooms[$room_id] ?? null;
            if (!$room) return;

            $type = $data['type'];

            if ($type === 'join') {
                $nickname = htmlspecialchars(substr(trim($data['nickname'] ?? ''), 0, 50), ENT_QUOTES, 'UTF-8');
                if ($nickname) {
                    $this->clientInfo[$rid]['nickname'] = $nickname;
                }
                $this->broadcastUserList($room, $room_id);
                return;
            }

            if ($type === 'message') {
                $this->handleMessage($from, $data, $room, $room_id);
            }
        } catch (\Exception $e) {
            $this->errorLogger->error("訊息處理錯誤", ['error' => $e->getMessage()]);
        }
    }

    private function handleMessage(ConnectionInterface $from, array $data, \SplObjectStorage $room, string $room_id)
    {
        $content = trim($data['content'] ?? '');
        if (!$content) return;

        $rid = $from->resourceId;
        $sender = $this->clientInfo[$rid];
        $message_id = $data['message_id'] ?? uniqid('msg_');

        $msgData = [
            'type' => 'message',
            'room_id' => $room_id,
            'sender_id' => $sender['id'],
            'nickname' => $sender['nickname'],
            'content' => $content,
            'is_ai' => false,
            'message_id' => $message_id,
            'timestamp' => time()
        ];

        $this->broadcast($room, $msgData);
        $this->saveMessage($room_id, $msgData);

        if (preg_match('/^[@＠]AI\s*/iu', $content)) {
            $prompt = trim(preg_replace('/^[@＠]AI\s*/iu', '', $content));
            if (!$prompt) return;

            $this->aiButler->handle($room, $room_id, $prompt, $this->aiContext[$room_id] ?? [], 1.0, '', $message_id)
                ->then(function ($aiMsg) use ($room, $room_id, $prompt) {
                    if (!$aiMsg || empty($aiMsg['content'])) {
                        $this->broadcast($room, ['type' => 'error', 'room_id' => $room_id, 'message' => 'AI 暫時無法回應']);
                        return;
                    }
                    $this->broadcast($room, $aiMsg);
                    $this->saveMessage($room_id, $aiMsg);
                    $this->aiContext[$room_id][] = ['role' => 'user', 'content' => "@AI {$prompt}"];
                    $this->aiContext[$room_id][] = ['role' => 'assistant', 'content' => $aiMsg['content']];
                    $this->contextManager->save($room_id, $this->aiContext[$room_id]);
                })
                ->otherwise(function ($e) use ($room, $room_id) {
                    $this->errorLogger->error("AI 失敗", ['error' => $e->getMessage()]);
                    $this->broadcast($room, ['type' => 'error', 'room_id' => $room_id, 'message' => 'AI 服務暫時無法使用']);
                });
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $rid = $conn->resourceId;
        if (!isset($this->clientInfo[$rid])) return;

        $room_id = $this->clientInfo[$rid]['room_id'] ?? 'lobby';
        $room = $this->rooms[$room_id] ?? null;
        if ($room && $room->contains($conn)) {
            $room->detach($conn);
            $this->broadcastUserList($room, $room_id);
        }

        unset($this->clientInfo[$rid], $this->rateLimits[$rid], $this->ttsLocks[$rid]);
        $this->clients->detach($conn);
        $this->appLogger->info("連線關閉", ['rid' => $rid]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->errorLogger->error("WebSocket 錯誤", ['error' => $e->getMessage()]);
        $conn->close();
    }

    private function broadcast(\SplObjectStorage $room, $data)
    {
        $json = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
        foreach ($room as $client) {
            $client->send($json);
        }
    }

    private function broadcastUserList(\SplObjectStorage $room, string $room_id)
    {
        $users = [];
        foreach ($room as $client) {
            $info = $this->clientInfo[$client->resourceId] ?? ['nickname' => '?'];
            $users[] = ['nickname' => $info['nickname'] ?? '未知'];
        }
        $this->broadcast($room, [
            'type' => 'user_list',
            'room_id' => $room_id,
            'users' => $users,
            'count' => count($users)
        ]);
    }

    private function saveMessage(string $room_id, array $msg)
    {
        $line = json_encode($msg, JSON_UNESCAPED_UNICODE) . "\n";
        $file = "logs/{$room_id}.log";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private function isOriginAllowed(string $origin, string $ip): bool
    {
        $allowed = array_map('trim', explode(',', $_ENV['ALLOWED_ORIGINS'] ?? ''));
        $parsed = parse_url($origin);
        $host = $parsed['host'] ?? '';
        $isLocal = in_array($origin, ['http://localhost', 'http://127.0.0.1']) || preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin);
        $isPrivate = preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|127\.0\.0\.1)/', $ip) || $ip === '::1';
        $isWhitelisted = in_array($origin, $allowed) || preg_match('/^(.+\.)?star-tw\.ddns\.net$/', $host);
        return $isLocal || $isPrivate || empty($origin) || $isWhitelisted;
    }
}

// ====================== 啟動伺服器 ======================
$loop = \React\EventLoop\Factory::create();
$chatApp = new Chat($loop);
$socket = new \React\Socket\Server('0.0.0.0:8080', $loop);
$server = new IoServer(new HttpServer(new WsServer($chatApp)), $socket, $loop);
echo "WebSocket server running on ws://0.0.0.0:8080 (v6.0-7 - AI 正常 + 前端穩定)\n";
$server->run();