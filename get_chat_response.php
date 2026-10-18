<?php
/**
 * Local FAQ + conversation chatbot endpoint for Barangay 76-A.
 * Works offline without external APIs or Google Colab.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'POST';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$inputRaw = file_get_contents('php://input');
$inputData = json_decode($inputRaw, true);
$userMessage = trim((string) ($inputData['message'] ?? ''));

if ($userMessage === '') {
    echo json_encode([
        'reply' => "I didn’t catch that. Please ask about the Barangay 76-A portal, services, project overview, or common website questions."
    ]);
    exit;
}

function normalizeText(string $text): string
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/', ' ', trim($text));
    return $text;
}

function tokenizeText(string $text): array
{
    return array_values(array_filter(
        array_unique(preg_split('/\s+/', normalizeText($text) ?: '') ?: []),
        static fn ($token) => $token !== ''
    ));
}

function faqSimilarity(string $left, string $right): float
{
    $left = normalizeText($left);
    $right = normalizeText($right);

    if ($left === '' || $right === '') {
        return 0.0;
    }

    $score = 0.0;
    similar_text($left, $right, $score);
    $normalizedSimilarity = $score / 100.0;

    $leftTokens = tokenizeText($left);
    $rightTokens = tokenizeText($right);
    $intersection = array_values(array_intersect($leftTokens, $rightTokens));

    $keywordBoost = 0.0;
    if ($intersection !== []) {
        $keywordBoost = min(
            0.5,
            count($intersection) / max(count($leftTokens), 1) * 0.7 +
            count($intersection) / max(count($rightTokens), 1) * 0.3
        );
    }

    if (str_contains($left, $right) || str_contains($right, $left)) {
        $keywordBoost += 0.35;
    }

    return min(1.0, max(0.0, $normalizedSimilarity * 0.7 + $keywordBoost));
}

function detectKeywordResponse(string $message, array $items): ?string
{
    $normalizedMessage = normalizeText($message);
    $messageTokens = tokenizeText($message);

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $keywords = array_filter(array_map('trim', (array) ($item['keywords'] ?? [])), static fn ($keyword) => $keyword !== '');
        if ($keywords === []) {
            continue;
        }

        $matchedKeywords = 0;
        foreach ($keywords as $keyword) {
            $normalizedKeyword = normalizeText((string) $keyword);
            $keywordTokens = tokenizeText((string) $keyword);

            $isSingleTokenMatch = $normalizedKeyword !== '' && count($keywordTokens) === 1 && (
                $normalizedMessage === $normalizedKeyword ||
                in_array($normalizedKeyword, $messageTokens, true) ||
                str_contains(' ' . $normalizedMessage . ' ', ' ' . $normalizedKeyword . ' ')
            );

            $isPhraseMatch = $normalizedKeyword !== '' && count($keywordTokens) > 1 && (
                str_contains($normalizedMessage, $normalizedKeyword) ||
                count(array_intersect($keywordTokens, $messageTokens)) === count($keywordTokens)
            );

            if ($normalizedKeyword !== '' && $keywordTokens !== [] && ($isSingleTokenMatch || $isPhraseMatch)) {
                $matchedKeywords++;
            }
        }

        if ($matchedKeywords > 0) {
            $response = trim((string) ($item['response'] ?? ''));
            if ($response !== '') {
                return $response;
            }
        }
    }

    return null;
}

function collectKnowledgeEntries(array $faqData): array
{
    $entries = [];

    foreach (['greetings', 'general', 'website', 'faqs'] as $section) {
        $items = $faqData[$section] ?? [];
        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            if ($question !== '' && $answer !== '') {
                $entries[] = ['question' => $question, 'answer' => $answer];
            }
        }
    }

    return $entries;
}

$faqPath = __DIR__ . '/faqs.json';
$faqData = ['greetings' => [], 'general' => [], 'website' => [], 'faqs' => []];

if (is_readable($faqPath)) {
    $decoded = json_decode((string) file_get_contents($faqPath), true);
    if (is_array($decoded)) {
        $faqData = $decoded;
    }
}

$greetingItems = is_array($faqData['greetings'] ?? null) ? $faqData['greetings'] : [];
$generalItems = is_array($faqData['general'] ?? null) ? $faqData['general'] : [];
$knowledgeEntries = collectKnowledgeEntries($faqData);

$greetingResponse = detectKeywordResponse($userMessage, $greetingItems);
if ($greetingResponse !== null) {
    echo json_encode([
        'reply' => $greetingResponse,
        'matched_type' => 'greeting',
        'confidence' => 1.0,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$generalResponse = detectKeywordResponse($userMessage, $generalItems);
if ($generalResponse !== null) {
    echo json_encode([
        'reply' => $generalResponse,
        'matched_type' => 'general',
        'confidence' => 1.0,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$bestMatch = [
    'question' => null,
    'answer' => 'I can help with the Barangay 76-A portal, resident and official access, certificate requests, services, project overview, and system features.',
    'score' => 0.0,
];

foreach ($knowledgeEntries as $entry) {
    $question = trim((string) ($entry['question'] ?? ''));
    $answer = trim((string) ($entry['answer'] ?? ''));

    if ($question === '' || $answer === '') {
        continue;
    }

    $score = faqSimilarity($userMessage, $question);
    if ($score > $bestMatch['score']) {
        $bestMatch = [
            'question' => $question,
            'answer' => $answer,
            'score' => $score,
        ];
    }
}

if ($bestMatch['score'] < 0.22) {
    $bestMatch['answer'] = 'I’m not sure about that yet, but I can still help with the Barangay 76-A portal. Try asking about the resident login, official dashboard, certificate request process, barangay services, contact details, or the project overview.';
}

echo json_encode([
    'reply' => $bestMatch['answer'],
    'matched_type' => $bestMatch['score'] >= 0.22 ? 'faq' : 'fallback',
    'matched_question' => $bestMatch['question'],
    'confidence' => round($bestMatch['score'], 3),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);