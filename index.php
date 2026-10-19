<?php

declare(strict_types=1);

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;

require __DIR__ . '/vendor/autoload.php';

$requestMethod = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$requestTarget = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$requestPath = parse_url($requestTarget, PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
$traceContextCarrier = [];

foreach (['traceparent', 'tracestate'] as $headerName) {
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
    if (isset($_SERVER[$serverKey]) && is_string($_SERVER[$serverKey])) {
        $traceContextCarrier[$headerName] = $_SERVER[$serverKey];
    }
}

$parentContext = TraceContextPropagator::getInstance()->extract(
    $traceContextCarrier,
    ArrayAccessGetterSetter::getInstance(),
);
$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$requestScheme = $forwardedProto === 'https'
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : 'http';
$serverAddress = parse_url('http://' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
$responseStatus = 200;
$redirectLocation = null;

$requestSpan = Globals::tracerProvider()
    ->getTracer('php-voting-poll')
    ->spanBuilder($requestMethod . ' ' . $requestPath)
    ->setParent($parentContext)
    ->setSpanKind(SpanKind::KIND_SERVER)
    ->setAttribute('http.request.method', $requestMethod)
    ->setAttribute('http.route', '/')
    ->setAttribute('url.scheme', $requestScheme)
    ->setAttribute('url.path', $requestPath)
    ->setAttribute('server.address', is_string($serverAddress) ? $serverAddress : '')
    ->startSpan();
$requestScope = $requestSpan->activate();

session_start();

$poll = [
    'question' => 'Which language would you choose for your next project?',
    'description' => 'Pick the tool you love building with. Your vote is saved privately in this browser session.',
    'options' => [
        'php' => [
            'label' => 'PHP',
            'detail' => 'Powering the web, one request at a time.',
            'tag' => 'Great for the web',
            'class' => 'php',
        ],
        'javascript' => [
            'label' => 'JavaScript',
            'detail' => 'From interactive pages to full-stack apps.',
            'tag' => 'Runs everywhere',
            'class' => 'javascript',
        ],
        'python' => [
            'label' => 'Python',
            'detail' => 'A friendly start with plenty of room to grow.',
            'tag' => 'Easy to explore',
            'class' => 'python',
        ],
        'java' => [
            'label' => 'Java',
            'detail' => 'Reliable, versatile, and built to scale.',
            'tag' => 'Made to scale',
            'class' => 'java',
        ],
    ],
];

if (!isset($_SESSION['votes']) || !is_array($_SESSION['votes'])) {
    $_SESSION['votes'] = array_fill_keys(array_keys($poll['options']), 0);
}

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['show_results'])) {
    $_SESSION['show_results'] = false;
}

$error = '';
$notice = '';
$selectedOption = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $error = 'Your session could not be verified. Please refresh the page and try again.';
        $responseStatus = 403;
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'vote') {
            $selectedOption = $_POST['option'] ?? '';

            if (!is_string($selectedOption) || !array_key_exists($selectedOption, $poll['options'])) {
                $error = 'Choose one of the options before submitting your vote.';
                $responseStatus = 422;
            } else {
                $_SESSION['votes'][$selectedOption]++;
                $_SESSION['last_vote'] = $selectedOption;
                $_SESSION['show_results'] = true;
                $_SESSION['notice'] = 'Thanks for voting! Your session tally has been updated.';
                $redirectLocation = strtok($requestTarget, '?') ?: '/';
            }
        } elseif ($action === 'vote_again') {
            $_SESSION['show_results'] = false;
            unset($_SESSION['notice']);
            $redirectLocation = strtok($requestTarget, '?') ?: '/';
        } else {
            $error = 'That action is not available. Please try again.';
            $responseStatus = 400;
        }
    }
}

if ($redirectLocation !== null) {
    http_response_code(303);
    header('Location: ' . $redirectLocation);
    $requestSpan->setAttribute('http.response.status_code', 303);
    $requestScope->detach();
    $requestSpan->end();
    exit;
}

if (isset($_SESSION['notice']) && is_string($_SESSION['notice'])) {
    $notice = $_SESSION['notice'];
    unset($_SESSION['notice']);
}

$showResults = $_SESSION['show_results'] === true;
$votes = $_SESSION['votes'];
$totalVotes = array_sum($votes);
$lastVote = $_SESSION['last_vote'] ?? '';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f7f2">
    <meta name="description" content="A little session-based poll for choosing your next favorite programming language.">
    <title>Pick Your Stack — a tiny voting app</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <a class="brand" href="index.php" aria-label="Pick Your Stack home">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M7 8.5 13 14l-6 5.5M16 21h9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <span>little poll<span class="brand-period">.</span></span>
            </a>
            <a class="header-link" href="#poll">Make your pick <span aria-hidden="true">↘</span></a>
        </header>

        <main>
            <section class="hero" aria-labelledby="page-title">
                <div class="hero-copy">
                    <p class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> A tiny question for curious builders</p>
                    <h1 id="page-title">Every great project<br>starts with a <span>pick.</span></h1>
                    <p class="hero-description">No big debate. Just your gut feeling, a few good options, and a little friendly tally.</p>
                </div>
                <div class="hero-sticker" aria-hidden="true">
                    <span class="sticker-spark">✳</span>
                    <span>made for<br>the makers</span>
                    <span class="sticker-arrow">↘</span>
                </div>
            </section>

            <section class="app-layout" id="poll" aria-label="Programming language poll">
                <div class="poll-card">
                    <div class="card-topline">
                        <span class="poll-label"><span class="label-icon" aria-hidden="true">✳</span> THE COMMUNITY QUESTION</span>
                        <span class="session-badge"><span aria-hidden="true"></span> SESSION POLL</span>
                    </div>

                    <?php if ($showResults): ?>
                        <div class="poll-content results-content">
                            <div class="section-number">01 <span>/ YOUR RESULTS</span></div>
                            <h2><?= escape($poll['question']) ?></h2>
                            <p class="poll-description">Here’s how your votes add up in this session. They’re private to this browser.</p>

                            <?php if ($notice !== ''): ?>
                                <p class="notice-message" role="status"><span aria-hidden="true">✓</span> <?= escape($notice) ?></p>
                            <?php endif; ?>

                            <div class="results-list" aria-label="Vote results">
                                <?php foreach ($poll['options'] as $key => $option): ?>
                                    <?php
                                    $count = (int) ($votes[$key] ?? 0);
                                    $percentage = $totalVotes > 0 ? (int) round(($count / $totalVotes) * 100) : 0;
                                    $isLastVote = $lastVote === $key;
                                    ?>
                                    <div class="result-row option-<?= escape($option['class']) ?><?= $isLastVote ? ' is-last-vote' : '' ?>">
                                        <div class="result-heading">
                                            <div class="result-name">
                                                <span class="option-dot" aria-hidden="true"></span>
                                                <span><?= escape($option['label']) ?></span>
                                                <?php if ($isLastVote): ?><span class="your-pick">YOUR PICK</span><?php endif; ?>
                                            </div>
                                            <span class="result-number"><?= $count ?> <span><?= $count === 1 ? 'vote' : 'votes' ?></span></span>
                                        </div>
                                        <div class="progress-track" role="progressbar" aria-label="<?= escape($option['label']) ?> votes" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percentage ?>">
                                            <span class="progress-fill" style="width: <?= $percentage ?>%"></span>
                                        </div>
                                        <div class="result-foot"><span><?= escape($option['detail']) ?></span><span><?= $percentage ?>%</span></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="results-actions">
                                <p><strong><?= $totalVotes ?></strong> <?= $totalVotes === 1 ? 'vote' : 'votes' ?> in your session</p>
                                <form method="post" action="<?= escape($_SERVER['PHP_SELF']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="vote_again">
                                    <button class="text-button" type="submit">Cast another vote <span aria-hidden="true">↗</span></button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="poll-content">
                            <div class="section-number">01 <span>/ THE QUESTION</span></div>
                            <h2><?= escape($poll['question']) ?></h2>
                            <p class="poll-description"><?= escape($poll['description']) ?></p>

                            <?php if ($error !== ''): ?>
                                <p class="error-message" role="alert"><?= escape($error) ?></p>
                            <?php endif; ?>

                            <form method="post" action="<?= escape($_SERVER['PHP_SELF']) ?>" class="vote-form">
                                <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="vote">
                                <fieldset>
                                    <legend class="sr-only">Choose your favorite programming language</legend>
                                    <div class="options-list">
                                        <?php foreach ($poll['options'] as $key => $option): ?>
                                            <label class="option-card option-<?= escape($option['class']) ?>">
                                                <input type="radio" name="option" value="<?= escape($key) ?>" <?= $selectedOption === $key ? 'checked' : '' ?> required>
                                                <span class="custom-radio" aria-hidden="true"></span>
                                                <span class="option-copy">
                                                    <span class="option-name"><?= escape($option['label']) ?></span>
                                                    <span class="option-detail"><?= escape($option['detail']) ?></span>
                                                </span>
                                                <span class="option-tag"><?= escape($option['tag']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                                <div class="form-footer">
                                    <span class="privacy-note"><span aria-hidden="true">♧</span> Just for this session</span>
                                    <button class="vote-button" type="submit">Cast my vote <span aria-hidden="true">↗</span></button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="side-column" aria-label="About this poll">
                    <div class="side-card note-card">
                        <span class="note-icon" aria-hidden="true">✳</span>
                        <p class="side-kicker">A NOTE FROM US</p>
                        <h3>There’s no wrong answer.</h3>
                        <p>Every language has its moment. Pick the one you’d be excited to open your editor for.</p>
                        <span class="handwritten">Trust your instinct <span aria-hidden="true">↗</span></span>
                    </div>
                    <div class="side-card stats-card">
                        <div class="stats-top"><span class="stats-icon" aria-hidden="true">◎</span><span>QUICK FACTS</span></div>
                        <div class="stat-line"><span>Options to choose from</span><strong><?= count($poll['options']) ?></strong></div>
                        <div class="stat-line"><span>Votes in your session</span><strong><?= $totalVotes ?></strong></div>
                        <p class="local-note"><span aria-hidden="true">⌁</span> Your votes stay on this session. No account, no database.</p>
                    </div>
                </aside>
            </section>
        </main>

        <footer class="site-footer">
            <span>MADE FOR THE LOVE OF MAKING THINGS</span>
            <span>ONE QUESTION. MANY GOOD ANSWERS. <span aria-hidden="true">✳</span></span>
        </footer>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();
http_response_code($responseStatus);
$requestSpan->setAttribute('http.response.status_code', $responseStatus);
$requestScope->detach();
$requestSpan->end();
echo $html;
