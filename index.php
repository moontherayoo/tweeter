<?php
$baseDir = __DIR__;
$postsDir = $baseDir . '/posts';
$usersDir = $baseDir . '/users';

if (!is_dir($postsDir)) {
    mkdir($postsDir, 0777, true);
}
if (!is_dir($usersDir)) {
    mkdir($usersDir, 0777, true);
}

function load_user($usersDir, $handle) {
    $path = $usersDir . '/' . $handle . '.txt';
    if (!is_file($path)) {
        return [
            'handle' => $handle,
            'display' => $handle,
            'bio' => 'New around here.'
        ];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    return [
        'handle' => $handle,
        'display' => $lines[0] ?? $handle,
        'bio' => $lines[1] ?? ''
    ];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handle = strtolower(trim($_POST['handle'] ?? ''));
    $content = trim($_POST['content'] ?? '');

    if ($handle === '' || !preg_match('/^[a-z0-9_]{2,20}$/', $handle)) {
        $errors[] = 'Choose a handle with 2-20 letters, numbers, or underscores.';
    }
    if ($content === '' || mb_strlen($content) > 200) {
        $errors[] = 'Posts must be between 1 and 200 characters.';
    }

    if (!$errors) {
        $timestamp = date('Ymd-His');
        $readable = date('Y-m-d H:i');
        $filename = $postsDir . '/' . $timestamp . '-' . bin2hex(random_bytes(2)) . '.txt';
        $safeContent = str_replace(["\r", "\n"], ' ', $content);
        $payload = $handle . "\n" . $readable . "\n" . $safeContent . "\n";
        file_put_contents($filename, $payload, LOCK_EX);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$posts = [];
foreach (glob($postsDir . '/*.txt') as $file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $handle = $lines[0] ?? 'guest';
    $date = $lines[1] ?? '';
    $content = $lines[2] ?? '';
    $user = load_user($usersDir, $handle);
    $posts[] = [
        'handle' => $handle,
        'display' => $user['display'],
        'bio' => $user['bio'],
        'date' => $date,
        'content' => $content,
    ];
}

usort($posts, function ($a, $b) {
    return strcmp($b['date'], $a['date']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tweeter</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="topbar">
        <div class="wrap">
            <div class="logo">Tweeter</div>
            <nav class="nav">
                <a href="#">Home</a>
                <a href="#">Profile</a>
                <a href="#">Messages</a>
            </nav>
        </div>
    </header>

    <main class="wrap layout">
        <aside class="sidebar">
            <section class="card">
                <h2>Welcome back</h2>
                <p>Share quick updates with the people who matter. Keep it short, keep it simple.</p>
            </section>
            <section class="card">
                <h2>Trending</h2>
                <ul class="trend-list">
                    <li>#indieweb</li>
                    <li>#retrotech</li>
                    <li>#tweeterlaunch</li>
                </ul>
            </section>
        </aside>

        <section class="timeline">
            <div class="compose card">
                <h2>What's happening?</h2>
                <?php if ($errors): ?>
                    <div class="alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <label>
                        Handle
                        <input type="text" name="handle" placeholder="yourname" maxlength="20" required>
                    </label>
                    <label>
                        Post
                        <textarea name="content" placeholder="Share a quick update" maxlength="200" required></textarea>
                    </label>
                    <button type="submit">Tweet</button>
                </form>
            </div>

            <?php foreach ($posts as $post): ?>
                <article class="tweet card">
                    <div class="avatar">@<?php echo htmlspecialchars($post['handle'][0] ?? 't'); ?></div>
                    <div class="tweet-body">
                        <div class="tweet-head">
                            <strong><?php echo htmlspecialchars($post['display']); ?></strong>
                            <span>@<?php echo htmlspecialchars($post['handle']); ?></span>
                            <time><?php echo htmlspecialchars($post['date']); ?></time>
                        </div>
                        <p><?php echo htmlspecialchars($post['content']); ?></p>
                        <div class="tweet-actions">
                            <button>Reply</button>
                            <button>Retweet</button>
                            <button>Favorite</button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <aside class="sidebar">
            <section class="card profile">
                <h2>Spotlight</h2>
                <h3><?php echo htmlspecialchars(load_user($usersDir, 'onsmrs')['display']); ?></h3>
                <p>@onsmrs</p>
                <p><?php echo htmlspecialchars(load_user($usersDir, 'onsmrs')['bio']); ?></p>
            </section>
            <section class="card">
                <h2>Stats</h2>
                <ul class="stats">
                    <li><strong><?php echo count($posts); ?></strong> tweets</li>
                    <li><strong>128</strong> followers</li>
                    <li><strong>87</strong> following</li>
                </ul>
            </section>
        </aside>
    </main>

    <footer class="footer">
        <div class="wrap">
            <p>Tweeter is an social media project which has the feel of Twitter but modernised. Created by onsmrs.</p>
            <p>© onsmrs Development</p>
        </div>
    </footer>
</body>
</html>
