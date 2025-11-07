<?php
// modules/ai_butler.php (v6.0-9 - 修正 generate() 參數 + 防 then() on true/false)
namespace Modules;

use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class AIButler
{
    private SearchService $searchService;
    private AIProvider $aiProvider;
    private TTSService $ttsService;
    private ContextManager $contextManager;
    private LoggerInterface $logger;
    private string $systemPrompt;

    public function __construct(
        SearchService $searchService,
        AIProvider $aiProvider,
        TTSService $ttsService,
        ContextManager $contextManager,
        LoggerInterface $logger,
        string $systemPrompt
    ) {
        $this->searchService = $searchService;
        $this->aiProvider = $aiProvider;
        $this->ttsService = $ttsService;
        $this->contextManager = $contextManager;
        $this->logger = $logger;
        $this->systemPrompt = $systemPrompt;
    }

    public function handle(
        \SplObjectStorage $room,
        string $room_id,
        string $prompt,
        array $context,
        float $temperature,
        string $voice,
        string $reply_to
    ): PromiseInterface {
        $searchPromise = $this->searchService->shouldSearch($prompt)
            ? $this->safeSearch($prompt)
            : resolve('');

        return $searchPromise->then(function ($searchResult) use ($prompt, $context, $temperature, $reply_to, $room_id) {
            $messages = $this->buildMessages($prompt, $context, $searchResult);
            return $this->aiProvider->generate($messages, $temperature); // 正確傳 array
        })->then(function ($aiResponse) use ($room, $room_id, $prompt, $reply_to) {
            if (!$aiResponse || empty($aiResponse['content'] ?? '')) {
                throw new \Exception("AI 回應為空");
            }

            $content = $this->postProcess($aiResponse['content']);
            $aiMsg = [
                'type' => 'message',
                'room_id' => $room_id,
                'sender_id' => 'ai_bro',
                'nickname' => 'AIBRO',
                'content' => $content,
                'is_ai' => true,
                'message_id' => $reply_to ?: uniqid('ai_'),
                'timestamp' => time()
            ];

            return resolve($aiMsg);
        })->otherwise(function ($e) use ($room, $room_id) {
            $this->logger->error("AI 流程失敗", ['error' => $e->getMessage()]);
            return resolve(null);
        });
    }

    private function safeSearch(string $prompt): PromiseInterface
    {
        $result = $this->searchService->search($prompt);
        if ($result === false || $result === true) {
            return resolve('');
        }
        return $result;
    }

    private function buildMessages(string $prompt, array $context, string $searchResult): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt]];

        foreach ($context as $msg) {
            if (isset($msg['role'], $msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        $userMsg = $prompt;
        if ($searchResult) {
            $userMsg .= "\n\n" . $searchResult;
        }
        $messages[] = ['role' => 'user', 'content' => $userMsg];

        return $messages;
    }

    private function postProcess(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        return $content;
    }
}