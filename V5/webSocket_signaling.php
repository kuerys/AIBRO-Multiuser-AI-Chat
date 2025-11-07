<?php
// v5.2 修正模型回應
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Dotenv\Dotenv;

// 非同步 Redis
use Clue\React\Redis\Factory;

// Promise 工具
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

// ==================== 載入環境變數 ====================
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

class Chat implements MessageComponentInterface
{
    protected $clients;
    protected $rooms;
    protected $clientInfo;
    protected $aiContext;
    protected $rateLimits;
    protected $searchCache;
    protected $fallbackOrder;
    protected $providers;
    protected $ttsRequests;
    protected $loop;
    protected $httpClient;
    protected $redis;
    protected $appLogger;
    protected $aiLogger;
    protected $errorLogger;

    protected $defaultSystemPrompt = "
你是 AIBRO，台灣風格的溫暖又專業 AI 助理，回答以繁體中文。
保持親切、尊重多元文化；若使用者用方言，可附翻譯補充。
涉及醫療或法律資訊時，加註「參考資訊，請諮詢專業人員」。
若問題缺乏即時資料，再引用本地搜尋摘要。
回覆風格：簡潔、有重點、語氣自然。
**【TTS 語音播報指示】**
1.  如果你的回覆中有**重要的發音、翻譯或關鍵解釋**。
2.  請將需要播報的文本用 **[TTS_START]** 和 **[TTS_END]** 標籤包住。
3.  **只包住關鍵的短句或單字**，不要包住全文。
4.  例如：若要播報「無聊的梗」，請寫：[TTS_START]無聊的梗[TTS_END]。
    ";

    public function __construct(\React\EventLoop\LoopInterface $loop)
    {
        $this->loop = $loop;
        $browser = new \React\Http\Browser($this->loop);
        $this->httpClient = $browser->withTimeout(10.0);  //等待回應

        // ====== 非同步 Redis 初始化（使用 Factory）======
        $redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $redisPort = $_ENV['REDIS_PORT'] ?? 6379;
        $redisUri = "redis://{$redisHost}:{$redisPort}";

        $factory = new Factory($this->loop);
        $this->redis = $factory->createLazyClient($redisUri);

        $this->redis->ping()->then(
            function () use ($redisHost, $redisPort) {
                echo "Successfully connected to Redis at {$redisHost}:{$redisPort}\n";
            },
            function ($e) {
                echo "Redis 連線失敗（仍可繼續運行）: " . $e->getMessage() . "\n";
            }
        );

        // ====== 其餘初始化 ======
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->clientInfo = [];
        $this->aiContext = [];
        $this->rateLimits = [];
        $this->searchCache = [];
        $this->ttsRequests = [];

        $logsDir = 'logs';
        if (!is_dir($logsDir) && !@mkdir($logsDir, 0755, true)) {
            throw new \RuntimeException("無法建立 {$logsDir} 目錄");
        }

        $logEnabled = filter_var($_ENV['LOG_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        if ($logEnabled) {
            $this->appLogger = new Logger('app');
            $this->appLogger->pushHandler(new RotatingFileHandler("{$logsDir}/app.log", 7, Logger::INFO));
            $this->aiLogger = new Logger('ai');
            $this->aiLogger->pushHandler(new RotatingFileHandler("{$logsDir}/ai.log", 7, Logger::INFO));
        } else {
            $this->appLogger = null;
            $this->aiLogger = null;
        }

       $this->errorLogger = new Logger('error');
$this->errorLogger->pushHandler(new RotatingFileHandler("{$logsDir}/error.log", 7, Logger::WARNING));

$this->fallbackOrder = array_map('trim', explode(',', $_ENV['FALLBACK_ORDER'] ?? 'nvidia,groq,aibro,xai,openai,google,deepseek,ollama'));


$this->providers = [
    'aibro' => [
        'key' => '',
        'model' => $_ENV['AIBRO_AI_MODEL'] ?? 'Gemma-3-TAIDE-12b-Chat-Q6_K.gguf',
        'base_url' => 'http://127.0.0.1:8008', 
        'endpoint' => '/v1/chat/completions'
    ],
    'xai' => [
        'key' => $_ENV['XAI_API_KEY'] ?? '',
        'model' => $_ENV['XAI_AI_MODEL'] ?? 'grok-3-mini',
        'base_url' => 'https://api.x.ai/v1',
        'endpoint' => '/chat/completions'
    ],
    'nvidia' => [
        'key' => $_ENV['NVIDIA_API_KEY'] ?? '',
        'model' => $_ENV['NVIDIA_AI_MODEL'] ?? 'openai/gpt-oss-120b',
        'base_url' => 'https://integrate.api.nvidia.com/v1',
        'endpoint' => '/chat/completions'
    ],
    'groq' => [
        'key' => $_ENV['GROQ_API_KEY'] ?? '',
        'model' => $_ENV['GROQ_AI_MODEL'] ?? 'openai/gpt-oss-120b',
        'base_url' => 'https://api.groq.com/openai/v1',
        'endpoint' => '/chat/completions'
    ],
    'openai' => [
        'key' => $_ENV['OPENAI_API_KEY'] ?? '',
        'model' => $_ENV['OPENAI_AI_MODEL'] ?? 'gpt-4o-mini',
        'base_url' => 'https://api.openai.com/v1',
        'endpoint' => '/chat/completions'
    ],
    'google' => [
        'key' => $_ENV['GOOGLE_API_KEY'] ?? '',
        'model' => $_ENV['GOOGLE_AI_MODEL'] ?? 'gemini-1.5-flash',
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
        'endpoint' => ':generateContent'
    ],
    'deepseek' => [
        'key' => $_ENV['DEEPSEEK_API_KEY'] ?? '',
        'model' => $_ENV['DEEPSEEK_AI_MODEL'] ?? 'deepseek-chat',
        'base_url' => 'https://api.deepseek.com/v1',
        'endpoint' => '/chat/completions'
    ],
    'ollama' => [
        'key' => '',
        'model' => $_ENV['OLLAMA_AI_MODEL'] ?? 'aibo-tw:12b',
        'base_url' => 'http://127.0.0.1:11434',
        'endpoint' => '/api/chat'
    ]
];

foreach ($this->fallbackOrder as $p) {
    $p = trim($p);
    if ($p && empty($this->providers[$p]['key']) && !in_array($p, ['aibro', 'ollama'])) {
        $this->errorLogger->warning("提供者 {$p} 缺少 API Key，將跳過");
    }
  }
}
    public function onOpen(ConnectionInterface $conn)
    {
        $headers = $conn->httpRequest->getHeaders();
        $origin = $headers['Origin'][0] ?? '';
        $remoteIp = $conn->remoteAddress ?? '';
        $devMode = filter_var($_ENV['DEV_MODE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        if (!$devMode) {
            $allowed = array_map('trim', explode(',', $_ENV['ALLOWED_ORIGINS'] ?? ''));
            $isLocalhost = in_array($origin, ['http://localhost', 'http://127.0.0.1', 'http://[::1]'])
                           || preg_match('#^http://localhost(:\d+)?$#', $origin)
                           || preg_match('#^http://127\.0\.0\.1(:\d+)?$#', $origin);
            if (!($isLocalhost || $this->isPrivateIp($remoteIp) || empty($origin) || in_array($origin, $allowed))) {
                $this->errorLogger->warning("拒絕未授權連線: Origin={$origin}, IP={$remoteIp}");
                $conn->close();
                return;
            }
        }

        $this->clients->attach($conn);
        $rid = $conn->resourceId;
        $this->clientInfo[$rid] = ['id' => uniqid('user_'), 'nickname' => '訪客_' . rand(10, 99)];
        $this->rateLimits[$rid] = ['count' => 0, 'last_reset' => time()];

        if ($this->appLogger) {
            $this->appLogger->info("新連線: {$rid} | Origin: {$origin} | IP: {$remoteIp}");
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            if (!$data || !isset($data['type']) || !isset($data['room_id'])) {
                $from->send(json_encode(['type' => 'error', 'room_id' => 'unknown', 'message' => '格式錯誤']));
                $this->errorLogger->warning("收到無效的 JSON 訊息", ['msg' => substr($msg, 0, 200)]);
                return;
            }

            $rid = $from->resourceId;
            $room_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['room_id']);
            if (strlen($room_id) < 3) {
                $from->send(json_encode(['type' => 'error', 'room_id' => 'invalid', 'message' => '房間 ID 無效']));
                return;
            }

            if (!isset($this->rooms[$room_id])) {
                $this->rooms[$room_id] = new \SplObjectStorage;
                $this->aiContext[$room_id] = [];
                $this->loadContext($room_id)->then(function ($context) use ($room_id) {
                    $this->aiContext[$room_id] = $context;
                    if ($this->appLogger) {
                        $this->appLogger->info("非同步載入房間 context: {$room_id}, 訊息數: " . count($context));
                    }
                });
                if ($this->appLogger) {
                    $this->appLogger->info("創建新房間: {$room_id}");
                }
            }

            $room = $this->rooms[$room_id];
            $type = $data['type'];

            if ($type === 'join') {
                $room->attach($from);
                $this->clientInfo[$rid]['id'] = $data['user_id'] ?? $this->clientInfo[$rid]['id'];
                $this->clientInfo[$rid]['nickname'] = htmlspecialchars(substr(trim($data['nickname'] ?? ''), 0, 50), ENT_QUOTES, 'UTF-8');
                $this->broadcastEvent($room, $room_id, 'user_joined', ['nickname' => $this->clientInfo[$rid]['nickname']]);
                $this->broadcastUserList($room, $room_id);
                $from->send(json_encode(['type' => 'join_status', 'room_id' => $room_id, 'reconnect' => $room->contains($from), 'context' => $this->aiContext[$room_id]]));
                if ($this->appLogger) {
                    $this->appLogger->info("使用者 {$this->clientInfo[$rid]['nickname']} ({$rid}) 加入房間 {$room_id}");
                }
                return;
            }

            if ($type === 'load_history') {
                $from->send(json_encode(['type' => 'load_history', 'room_id' => $room_id, 'lines' => $this->getHistoryLines($room_id)]));
                return;
            }

            if ($type === 'message') {
                $content = trim($data['content'] ?? '');
                if (!$content) return;

                $sender = $this->clientInfo[$rid];
                $message_id = $data['message_id'] ?? uniqid('msg_');
                $client_sent_at = $data['_client_sent_at'] ?? null;
                $msgData = [
                    'type' => 'message',
                    'room_id' => $room_id,
                    'sender_id' => $data['sender_id'] ?? $sender['id'],
                    'nickname' => $data['nickname'] ?? $sender['nickname'],
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

                    $now = time();
                    if ($this->rateLimits[$rid]['last_reset'] < $now - 60) {
                        $this->rateLimits[$rid] = ['count' => 0, 'last_reset' => $now];
                    }
                    if ($this->rateLimits[$rid]['count'] >= 3) {
                        $this->broadcast($room, ['type' => 'error', 'room_id' => $room_id, 'message' => 'AI 呼叫過於頻繁，請稍候再試']);
                        return;
                    }
                    $this->rateLimits[$rid]['count']++;

                    $context = $data['context'] ?? $this->aiContext[$room_id];
                    $systemPrompt = $data['system_prompt'] ?? $this->defaultSystemPrompt;
                    $temperature = max(0.1, min(2.0, $data['temperature'] ?? 1.0));
                    $this->handleAiRequest($room, $room_id, $prompt, $context, $temperature, $systemPrompt, $message_id, $client_sent_at);
                }
                return;
            }

            if ($type === 'generate_tts') {
                $textToSpeak = trim($data['text'] ?? '');
                $messageId = $data['message_id'] ?? null;
                $requestKey = $messageId . '_' . md5($textToSpeak);
                if (!$textToSpeak || !$messageId || isset($this->ttsRequests[$requestKey])) return;
                $this->ttsRequests[$requestKey] = true;

                $this->callTTSAPIAsync($textToSpeak)->then(
                    function ($audioUrl) use ($from, $room_id, $messageId, $textToSpeak) {
                        $from->send(json_encode(['type' => 'tts_ready', 'room_id' => $room_id, 'message_id'=> $messageId, 'text' => $textToSpeak, 'audio_url' => $audioUrl], JSON_UNESCAPED_UNICODE));
                    }
                )->always(function () use ($requestKey) {
                    unset($this->ttsRequests[$requestKey]);
                });
                return;
            }

            $from->send(json_encode(['type' => 'error', 'room_id' => $room_id, 'message' => "未知類型: {$type}"]));
        } catch (\Exception $e) {
            $this->errorLogger->error("onMessage 錯誤: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $from->send(json_encode(['type' => 'error', 'room_id' => 'unknown', 'message' => '伺服器內部錯誤']));
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $rid = $conn->resourceId;
        $nick = $this->clientInfo[$rid]['nickname'] ?? '未知';
        foreach ($this->rooms as $room_id => $room) {
            if ($room->contains($conn)) {
                $room->detach($conn);
                $this->broadcastEvent($room, $room_id, 'user_left', ['nickname' => $nick]);
                $this->broadcastUserList($room, $room_id);
            }
        }
        unset($this->clientInfo[$rid], $this->rateLimits[$rid]);
        $this->clients->detach($conn);
        if ($this->appLogger) {
            $this->appLogger->info("連線關閉: {$rid} ({$nick})");
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->errorLogger->error("WebSocket 錯誤: " . $e->getMessage());
        $conn->close();
    }

    private function handleAiRequest($room, $room_id, $prompt, $context, $temperature, $systemPrompt, $message_id, $client_sent_at)
    {
        $timings = ['start_ms' => microtime(true) * 1000, 't2_search_ms' => null, 't3_ai_generation_ms' => null, 't4_backend_total_ms' => null];

        $this->searchAsync($prompt, $timings)->then(
            function ($searchResult) use ($room, $room_id, $prompt, $context, $temperature, $systemPrompt, $message_id, $timings) {
                $finalPrompt = $searchResult['finalPrompt'];
                $timings = $searchResult['timings'];
                return $this->callAIAPIAsync($finalPrompt, $context, $temperature, $systemPrompt, $timings)->then(
                    function ($aiResult) use ($room, $room_id, $prompt, $message_id, $timings) {
                        $reply = $aiResult['reply'] ?? '（AI 無回應）';
                        $provider = $aiResult['used_provider'];
                        $timings = $aiResult['timings'];
                        $end_time_ms = microtime(true) * 1000;
                        $timings['t4_backend_total_ms'] = $end_time_ms - $timings['start_ms'];

                        $formatted_timings = [
                            't2_search' => (int)($timings['t2_search_ms'] ?? 0),
                            't3_ai' => (int)($timings['t3_ai_generation_ms'] ?? 0),
                            't4_backend' => (int)($timings['t4_backend_total_ms'] ?? 0),
                        ];
                        $responseTimeInSeconds = round($timings['t4_backend_total_ms'] / 1000, 2);

                        $aiMsg = [
                            'type' => 'message',
                            'room_id' => $room_id,
                            'sender_id' => 'AI_BOT',
                            'nickname' => 'AIBRO 助手',
                            'content' => $reply,
                            'is_ai' => true,
                            'message_id' => 'ai_' . $message_id,
                            'timings_ms' => $formatted_timings,
                            'response_time' => $responseTimeInSeconds,
                            'timestamp' => time()
                        ];

                        $this->broadcast($room, $aiMsg);
                        $this->saveMessage($room_id, $aiMsg);
                        $this->aiContext[$room_id][] = ['role' => 'user', 'content' => "@AI " . $prompt];
                        $this->aiContext[$room_id][] = ['role' => 'assistant', 'content' => $reply];
                        $this->trimContext($room_id);
                        $this->saveContext($room_id);

                        if ($this->aiLogger) {
                            $this->aiLogger->info("AI 回應成功", [
                                'room_id' => $room_id,
                                'provider' => $provider,
                                'reply_length' => mb_strlen($reply),
                                'timings_ms' => $formatted_timings
                            ]);
                        }
                    }
                );
            },
            function ($e) use ($room, $room_id) {
                $this->broadcast($room, ['type' => 'error', 'room_id' => $room_id, 'message' => 'AI 服務暫時無法使用']);
            }
        );
    }

    private function shouldSearch($prompt)
    {
        $hash = md5($prompt);
        $now = time();
        if (isset($this->searchCache[$hash]) && $this->searchCache[$hash]['expires'] > $now) {
            return $this->searchCache[$hash]['result'];
        }
        $need = $this->matchSearchTerms($prompt);
        $this->searchCache[$hash] = ['result' => $need, 'expires' => $now + 300];
        if (count($this->searchCache) > 1000) {
            array_shift($this->searchCache);
        }
        return $need;
    }

    private function matchSearchTerms($p)
    {
        $terms = ['google一下', '告訴我', '幫我找', '幫我查', '找一下', '找找看', '找資料', '推薦給我', '查一下', '查資料', '查查看', '請搜尋', '哪裡有', '怎麼買', '搜尋', '查詢', '搜索', '比較', '評價', '推薦', '如何', '怎麼', '什麼', '誰', '哪裡', '哪個', '幾點', '幾號', '幾歲', '幾時', '多少', '為何', '為什麼', '有沒有', '現在', '時間', '日期', '地點', '地址', '附近', '價格', '便宜', '比價', '折扣', '週年慶', '優惠', '開箱', '規格', '型號', '功能', '票價', '股價', '匯率', '特價', '購物', '電商', '健保', '停車', '公車', '大學', '天氣', '學校', '客運', '小吃', '便當', '捷運', '景點', '油價', '火車', '疫苗', '疫情', '醫院', '藥局', '夜市', '選舉', '高鐵', '餐廳', '飲料', '地震', '颱風', 'Dcard', 'PTT', '上映', '新聞', '新知', '最新', '暖心', '熱門', '懶人包', '政治', '經濟', '體育', '影評', '戲劇', '電影', '節目', '展覽', '演唱會', '直播', '歌手', '歌詞', '專輯', '消息', '空氣', '雨量', '溫度', '作法', '修復', '條例', '定義', '故障', '法規', '安裝', '教學', '意思', '解決', '解釋', '食譜', '副作用', '症狀', '怎用', '架設', '合約', '標案', '吃到飽', '自助餐', '單點', '套餐', '查證', '確認一下'];
        $pattern = '/(' . implode('|', $terms) . ')/iu';
        return (bool)preg_match($pattern, $p);
    }

    private function searchAsync(string $prompt, array $timings): PromiseInterface
    {
        if (!$this->shouldSearch($prompt)) {
            $timings['t2_search_ms'] = 0;
            if ($this->aiLogger) {
                $this->aiLogger->info("無需搜尋，直接使用知識回應", ['prompt' => mb_substr($prompt, 0, 50)]);
            }
            return resolve([
                'finalPrompt' => $prompt,
                'timings' => $timings
            ]);
        }

        if ($this->aiLogger) {
            $this->aiLogger->info("開始搜尋", ['prompt' => mb_substr($prompt, 0, 50), 'search_url' => $_ENV['SEARCH_SERVICE_URL'] ?? 'default']);
        }

        $searchHost = $_ENV['SEARCH_SERVICE_URL'] ?? 'http://127.0.0.1:8888';
        $searchUrl = "{$searchHost}/search?q=" . urlencode($prompt) . '&format=json';
        $search_start_time_ms = microtime(true) * 1000;

        return $this->httpClient->get($searchUrl, ['User-Agent' => 'AIBO-Bot/1.0'])->then(
            function (\Psr\Http\Message\ResponseInterface $response) use ($prompt, &$timings, $search_start_time_ms) {
                $timings['t2_search_ms'] = (microtime(true) * 1000) - $search_start_time_ms;
                $body = (string)$response->getBody();
                $res = json_decode($body, true);
                $searchSummary = '';
                if ($res && isset($res['results']) && !empty($res['results'])) {
                    $resultCount = (int)($_ENV['SEARCH_RESULT_COUNT'] ?? 5);
                    foreach (array_slice($res['results'], 0, $resultCount) as $r) {
                        $title = htmlspecialchars($r['title'] ?? '(無標題)', ENT_QUOTES, 'UTF-8');
                        $url = filter_var($r['url'] ?? '', FILTER_VALIDATE_URL) ? $r['url'] : '';
                        $searchSummary .= "• {$title}" . ($url ? " | 來源：{$url}" : '') . "\n";
                    }
                } else {
                    $searchSummary = "（無相關搜尋結果）\n";
                }

                if (preg_match('/(幾點|現在時間|現在幾點|時間)/iu', $prompt)) {
                    $days = ['日', '一', '二', '三', '四', '五', '六'];
                    $now = date('Y年n月j日 星期') . $days[date('w')] . date('，H:i');
                    $searchSummary = "【即時時間】現在時間是：{$now}\n" . $searchSummary;
                }

                if (mb_strlen($searchSummary) > 800) {
                    $searchSummary = mb_substr($searchSummary, 0, 800) . "\n（摘要已截斷）";
                }

                if ($this->aiLogger) {
                    $this->aiLogger->info("搜尋成功，生成摘要", ['summary_preview' => mb_substr($searchSummary, 0, 80)]);
                }

                return resolve([
                    'finalPrompt' => $prompt . "\n\n【即時搜尋摘要】\n" . $searchSummary,
                    'timings' => $timings
                ]);
            },
            function (\Exception $e) use ($prompt, &$timings, $search_start_time_ms) {
                $timings['t2_search_ms'] = (microtime(true) * 1000) - $search_start_time_ms;
                if ($this->aiLogger) {
                    $this->aiLogger->error("SearxNG 連線失敗: " . $e->getMessage());
                }
                return resolve([
                    'finalPrompt' => $prompt . "\n\n【搜尋服務暫時不穩】請根據現有知識回答。",
                    'timings' => $timings
                ]);
            }
        );
    }

    private function callTTSAPIAsync(string $text): PromiseInterface
    {
        $ttsHost = $_ENV['TTS_SERVICE_URL'] ?? 'http://127.0.0.1:5005';
        $ttsUrl = "{$ttsHost}/speak";
        $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type' => 'application/json', 'Content-Length' => strlen($payload)];

        return $this->httpClient->post($ttsUrl, $headers, $payload)->then(
            function (\Psr\Http\Message\ResponseInterface $response) {
                $json = json_decode((string)$response->getBody(), true);
                if (json_last_error() === JSON_ERROR_NONE && ($json['status'] ?? 'error') === 'ok') {
                    if ($this->aiLogger) $this->aiLogger->info("TTS 語音生成成功", ['file' => $json['file']]);
                    return $json['file'] ?? null;
                }
                if ($this->aiLogger) $this->aiLogger->warning("TTS API 回應格式錯誤或狀態非 ok", ['response' => (string)$response->getBody()]);
                return null;
            },
            function (\Exception $e) {
                if ($this->aiLogger) $this->aiLogger->error("TTS API 請求失敗", ['error' => $e->getMessage()]);
                throw $e;
            }
        );
    }

    private function callAIAPIAsync($prompt, $context, $temperature, $systemPrompt, array $timings): PromiseInterface
{
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    $lastRole = 'system';
    foreach ($context as $m) {
        $r = $m['role'] ?? 'unknown';
        if ($r === 'user' && $lastRole === 'user') {
            $messages[count($messages) - 1]['content'] .= "\n\n" . $m['content'];
        } elseif ($r === 'assistant' && $lastRole === 'system') {
            continue;
        } else {
            $messages[] = $m;
            $lastRole = $r;
        }
    }
    $messages[] = ['role' => 'user', 'content' => $prompt];
    $basePayload = [
        'messages' => $messages,
        'temperature' => $temperature,
        'top_p' => 1,
        'max_tokens' => 4096,
        'stream' => false
    ];

    $deferred = new \React\Promise\Deferred();
    $fallbackOrder = $this->fallbackOrder;

    $tryNextProvider = function () use (&$tryNextProvider, &$fallbackOrder, $basePayload, $deferred, &$timings) {
        if (empty($fallbackOrder)) {
            $deferred->reject(new \Exception('所有 AI 提供者都請求失敗'));
            return;
        }

        $p = trim(array_shift($fallbackOrder));
        $cfg = $this->providers[$p] ?? null;
        if (!$cfg || (empty($cfg['key']) && !in_array($p, ['aibro', 'ollama']))) {
            $tryNextProvider();
            return;
        }

        if ($this->aiLogger) {
            $this->aiLogger->info("嘗試使用 AI Provider: {$p} ({$cfg['model']})");
        }

        $payload = $basePayload;
        $payload['model'] = $cfg['model'];
        $url = ($p === 'google') ? $cfg['base_url'] . '/' . $cfg['model'] . $cfg['endpoint'] : $cfg['base_url'] . $cfg['endpoint'];

        if ($p === 'google') {
            $payload = [
                'contents' => $this->convertMessagesToGoogleFormat($basePayload['messages']),
                'generationConfig' => [
                    'temperature' => $basePayload['temperature'],
                    'topP' => $basePayload['top_p'],
                    'maxOutputTokens' => $basePayload['max_tokens']
                ]
            ];
            $url .= '?key=' . $cfg['key'];
        } elseif ($p === 'ollama') {
            $payload = [
                'model' => $payload['model'],
                'messages' => $payload['messages'],
                'stream' => false,
                'options' => [
                    'temperature' => $payload['temperature'],
                    'top_p' => $payload['top_p'],
                    'num_predict' => $payload['max_tokens']
                ]
            ];
        } elseif ($p === 'aibro') {
            $payload = [
                'model' => $payload['model'],
                'messages' => $payload['messages'],
                'temperature' => $payload['temperature'],
                'max_tokens' => $payload['max_tokens'],
                'stream' => false
            ];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = $this->getHeaders($p, $cfg['key']);
        $headers['Content-Length'] = strlen($body);

        $ai_start_time_ms = microtime(true) * 1000;
        $this->httpClient->post($url, $headers, $body)->then(
            function (\Psr\Http\Message\ResponseInterface $response) use ($p, $cfg, $deferred, &$tryNextProvider, &$timings, $ai_start_time_ms) {
                $body = (string)$response->getBody();
                $json = json_decode($body, true);
                $reply = null;

                if (json_last_error() === JSON_ERROR_NONE) {
                    if ($p === 'google') {
                        $reply = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    } elseif ($p === 'ollama') {
                        $reply = $json['message']['content'] ?? null;
                    } elseif ($p === 'aibro') {
                        $reply = $json['choices'][0]['message']['content'] ?? $json['choices'][0]['text'] ?? null;
                    } else {
                        $reply = $json['choices'][0]['message']['content'] ?? null;
                    }
                }

                if ($reply) {
                    $timings['t3_ai_generation_ms'] = (microtime(true) * 1000) - $ai_start_time_ms;
                    $reply = $this->sanitizeAssistantOutput($reply);
                    if ($this->aiLogger) {
                        $this->aiLogger->info("AI 成功，使用 {$p} ({$cfg['model']})", ['reply_length' => mb_strlen($reply)]);
                    }
                    $deferred->resolve([
                        'reply' => $reply,
                        'used_provider' => $p,
                        'timings' => $timings
                    ]);
                } else {
                    if ($this->aiLogger) {
                        $this->aiLogger->warning("{$p} 回應格式錯誤", ['body_preview' => substr($body, 0, 150)]);
                    }
                    $tryNextProvider();
                }
            },
            function (\Exception $e) use ($p, &$tryNextProvider) {
                if ($this->aiLogger) {
                    $this->aiLogger->warning("{$p} 請求失敗: " . $e->getMessage());
                }
                $tryNextProvider();
            }
        );
    };

    $tryNextProvider();
    return $deferred->promise();
}


    private function broadcast($room, $data)
    {
        foreach ($room as $c) $c->send(is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function broadcastUserList($room, $room_id)
    {
        $users = [];
        foreach ($room as $c) {
            $i = $this->clientInfo[$c->resourceId] ?? ['id' => '?', 'nickname' => '?'];
            $users[] = ['id' => $i['id'], 'nickname' => $i['nickname']];
        }
        $this->broadcast($room, ['type' => 'user_list', 'room_id' => $room_id, 'users' => $users]);
    }

    private function broadcastEvent($room, $room_id, $type, $data)
    {
        $this->broadcast($room, array_merge(['type' => $type, 'room_id' => $room_id], $data));
    }

    private function getHeaders($provider, $key)
    {
        $h = ['Content-Type' => 'application/json'];
        if (!in_array($provider, ['ollama', 'google'])) $h['Authorization'] = 'Bearer ' . $key;
        return $h;
    }

    private function sanitizeAssistantOutput($text)
    {
        if (!is_string($text) || trim($text) === '') return '（AI 思考中…）';
        $text = preg_replace(
            ['/<\|[^>]*\|>/', '/\{[^{}]*"query"[^{}]*\}/', '/^(Attempt to|Search|Call tool|Using tool).*$/mi', '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '/\n{3,}/'],
            ['', '', '', '', "\n\n"],
            $text
        );
        return trim($text) !== '' ? trim($text) : '（AI 回應被過濾，請重新提問）';
    }

    private function trimContext($room_id)
    {
        $msgs = &$this->aiContext[$room_id];
        if (count($msgs) < 5) return;
        $sys = array_filter($msgs, fn($m) => $m['role'] === 'system');
        $chat = array_filter($msgs, fn($m) => $m['role'] !== 'system');
        while ($this->estimateTokens(array_merge($sys, $chat)) > 2000 && count($chat) > 2) {
            array_shift($chat);
        }
        $this->aiContext[$room_id] = array_merge($sys, array_values($chat));
    }

    private function estimateTokens($messages)
    {
        $txt = implode('', array_column($messages, 'content'));
        $div = preg_match('/[\x{4e00}-\x{9fff}]/u', $txt) ? 2.5 : 4;
        return (int)(mb_strlen($txt, 'UTF-8') / $div) + count($messages) * 3;
    }

    private function saveContext($room_id)
    {
        $key = "aibro:context:{$room_id}";
        $data = json_encode($this->aiContext[$room_id] ?? [], JSON_UNESCAPED_UNICODE);
        if ($data === false) {
            $this->errorLogger->error('saveContext JSON 編碼失敗', ['room_id' => $room_id]);
            return;
        }

        $this->redis->set($key, $data)->then(function () use ($key) {
            return $this->redis->expire($key, 86400);
        })->then(null, function ($e) use ($room_id) {
            $this->errorLogger->warning("Redis saveContext 失敗: " . $e->getMessage(), ['room_id' => $room_id]);
        });
    }

    private function loadContext($room_id): PromiseInterface
    {
        $key = "aibro:context:{$room_id}";
        return $this->redis->get($key)->then(function ($data) use ($key) {
            if ($data !== null) {
                $this->redis->expire($key, 86400);
            }
            return $data ? json_decode($data, true) : [];
        })->otherwise(function () {
            return [];
        });
    }

    private function saveMessage($room_id, $msg)
    {
        $line = json_encode($msg, JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents("logs/{$room_id}.log", $line, FILE_APPEND | LOCK_EX);
    }

    private function getHistoryLines($room_id)
    {
        $lines = @file("logs/{$room_id}.log", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $lines ?: [];
    }

    private function isPrivateIp($ip)
    {
        return preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|127\.0\.0\.1)/', $ip) || $ip === '::1';
    }

    private function convertMessagesToGoogleFormat(array $messages): array
    {
        $contents = [];
        $systemPrompt = '';
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt .= $msg['content'] . "\n\n";
                continue;
            }
            if ($systemPrompt && ($msg['role'] === 'user')) {
                $msg['content'] = $systemPrompt . $msg['content'];
                $systemPrompt = '';
            }
            $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
            $lastContent = end($contents);
            if ($lastContent && $lastContent['role'] === $role) {
                $contents[count($contents) - 1]['parts'][0]['text'] .= "\n\n" . $msg['content'];
            } else {
                $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
            }
        }
        return $contents;
    }
}

// ====================== 啟動 ======================
$loop = \React\EventLoop\Factory::create();
$chatApp = new Chat($loop);

$server = new IoServer(
    new HttpServer(new WsServer($chatApp)),
    new \React\Socket\Server('0.0.0.0:8080', $loop),
    $loop
);

echo "WebSocket server running on port 8080 (v5.2 修正模型回應)\n";
$server->run();