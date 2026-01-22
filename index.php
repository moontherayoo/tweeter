<?php
session_start();

$baseDir = __DIR__;
$postsDir = $baseDir . '/posts';
$usersDir = $baseDir . '/users';
$imgsDir = $baseDir . '/imgs';

foreach ([$postsDir, $usersDir, $imgsDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function read_user($usersDir, $handle) {
    $path = $usersDir . '/' . $handle . '.txt';
    if (!is_file($path)) {
        return null;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    return [
        'handle' => $handle,
        'display' => $lines[0] ?? $handle,
        'bio' => $lines[1] ?? '',
        'password' => $lines[2] ?? '',
        'avatar' => $lines[3] ?? '',
        'verified' => ($lines[4] ?? '0') === '1',
        'admin' => ($lines[5] ?? '0') === '1',
    ];
}

function write_user($usersDir, $user) {
    $path = $usersDir . '/' . $user['handle'] . '.txt';
    $lines = [
        $user['display'],
        $user['bio'],
        $user['password'],
        $user['avatar'],
        $user['verified'] ? '1' : '0',
        $user['admin'] ? '1' : '0',
    ];
    file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX);
}

function domain_handle($handle) {
    return 'domain.example/@' . $handle;
}

$errors = [];
$messages = [];
$currentUser = null;
if (isset($_SESSION['handle'])) {
    $currentUser = read_user($usersDir, $_SESSION['handle']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $handle = strtolower(trim($_POST['handle'] ?? ''));
        $password = $_POST['password'] ?? '';
        $display = trim($_POST['display'] ?? '');

        if ($handle === '' || !preg_match('/^[a-z0-9_]{2,20}$/', $handle)) {
            $errors[] = 'Choose a handle with 2-20 letters, numbers, or underscores.';
        }
        if (is_file($usersDir . '/' . $handle . '.txt')) {
            $errors[] = 'That handle is already registered.';
        }
        if (mb_strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($display === '') {
            $display = $handle;
        }

        $avatarName = '';
        if (!empty($_FILES['avatar']['name'])) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
            $type = $_FILES['avatar']['type'] ?? '';
            if (!isset($allowed[$type])) {
                $errors[] = 'Avatar must be a JPG, PNG, or GIF.';
            } else {
                $ext = $allowed[$type];
                $avatarName = $handle . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $imgsDir . '/' . $avatarName;
                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    $errors[] = 'Could not save the avatar image.';
                }
            }
        }

        if (!$errors) {
            $newUser = [
                'handle' => $handle,
                'display' => $display,
                'bio' => 'New on Tweeter.',
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'avatar' => $avatarName,
                'verified' => false,
                'admin' => false,
            ];
            write_user($usersDir, $newUser);
            $_SESSION['handle'] = $handle;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    if ($action === 'login') {
        $handle = strtolower(trim($_POST['handle'] ?? ''));
        $password = $_POST['password'] ?? '';
        $user = read_user($usersDir, $handle);
        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid handle or password.';
        } else {
            $_SESSION['handle'] = $handle;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    if ($action === 'logout') {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'post') {
        if (!$currentUser) {
            $errors[] = 'Please register or log in to post.';
        } else {
            $content = trim($_POST['content'] ?? '');
            if ($content === '' || mb_strlen($content) > 200) {
                $errors[] = 'Posts must be between 1 and 200 characters.';
            }
            if (!$errors) {
                $timestamp = date('Ymd-His');
                $readable = date('Y-m-d H:i');
                $filename = $postsDir . '/' . $timestamp . '-' . bin2hex(random_bytes(2)) . '.txt';
                $safeContent = str_replace(["\r", "\n"], ' ', $content);
                $payload = $currentUser['handle'] . "\n" . $readable . "\n" . $safeContent . "\n" . "0\n0\n";
                file_put_contents($filename, $payload, LOCK_EX);
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }

    if (in_array($action, ['like', 'retweet'], true)) {
        if (!$currentUser) {
            $errors[] = 'Please register or log in to interact.';
        } else {
            $postId = basename($_POST['post_id'] ?? '');
            $postPath = $postsDir . '/' . $postId;
            if (is_file($postPath)) {
                $lines = file($postPath, FILE_IGNORE_NEW_LINES);
                $lines[0] = $lines[0] ?? 'guest';
                $lines[1] = $lines[1] ?? '';
                $lines[2] = $lines[2] ?? '';
                $lines[3] = $lines[3] ?? '0';
                $lines[4] = $lines[4] ?? '0';

                if ($action === 'like') {
                    $lines[3] = (string) ((int) $lines[3] + 1);
                } else {
                    $lines[4] = (string) ((int) $lines[4] + 1);
                }
                file_put_contents($postPath, implode("\n", $lines) . "\n", LOCK_EX);
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }

    if ($action === 'verify' && $currentUser && $currentUser['admin']) {
        $target = strtolower(trim($_POST['target'] ?? ''));
        $targetUser = read_user($usersDir, $target);
        if ($targetUser) {
            $targetUser['verified'] = !$targetUser['verified'];
            write_user($usersDir, $targetUser);
            $messages[] = 'Updated verification for ' . htmlspecialchars($target);
        }
    }
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
    $likes = (int) ($lines[3] ?? 0);
    $retweets = (int) ($lines[4] ?? 0);
    $user = read_user($usersDir, $handle) ?? [
        'handle' => $handle,
        'display' => $handle,
        'bio' => 'New around here.',
        'avatar' => '',
        'verified' => false,
    ];
    $posts[] = [
        'id' => basename($file),
        'handle' => $handle,
        'display' => $user['display'],
        'bio' => $user['bio'],
        'avatar' => $user['avatar'],
        'verified' => $user['verified'],
        'date' => $date,
        'content' => $content,
        'likes' => $likes,
        'retweets' => $retweets,
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

$allUsers = [];
foreach (glob($usersDir . '/*.txt') as $file) {
    $handle = basename($file, '.txt');
    $user = read_user($usersDir, $handle);
    if ($user) {
        $allUsers[] = $user;
    }
}
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
                <?php if ($currentUser): ?>
                    <p class="status">Signed in as <strong><?php echo htmlspecialchars(domain_handle($currentUser['handle'])); ?></strong></p>
                    <form method="post">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="ghost">Sign out</button>
                    </form>
                <?php else: ?>
                    <p class="status">Sign in to post, like, or retweet.</p>
                <?php endif; ?>
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
                <?php if ($messages): ?>
                    <div class="notice">
                        <?php foreach ($messages as $message): ?>
                            <p><?php echo htmlspecialchars($message); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="action" value="post">
                <form method="post">
                    <label>
                        Handle
                        <input type="text" name="handle" placeholder="yourname" maxlength="20" required>
                    </label>
                    <label>
                        Post
                        <textarea name="content" placeholder="Share a quick update" maxlength="200" required></textarea>
                    </label>
                    <button type="submit" <?php echo $currentUser ? '' : 'disabled'; ?>>Tweet</button>
                    <button type="submit">Tweet</button>
                </form>
            </div>

            <?php foreach ($posts as $post): ?>
                <article class="tweet card">
                    <div class="avatar">
                        <?php if ($post['avatar']): ?>
                            <img src="imgs/<?php echo htmlspecialchars($post['avatar']); ?>" alt="Profile">
                        <?php else: ?>
                            @<?php echo htmlspecialchars($post['handle'][0] ?? 't'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="tweet-body">
                        <div class="tweet-head">
                            <strong><?php echo htmlspecialchars($post['display']); ?></strong>
                            <?php if ($post['verified']): ?>
                                <span class="check">✔</span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars(domain_handle($post['handle'])); ?></span>
                    <div class="avatar">@<?php echo htmlspecialchars($post['handle'][0] ?? 't'); ?></div>
                    <div class="tweet-body">
                        <div class="tweet-head">
                            <strong><?php echo htmlspecialchars($post['display']); ?></strong>
                            <span>@<?php echo htmlspecialchars($post['handle']); ?></span>
                            <time><?php echo htmlspecialchars($post['date']); ?></time>
                        </div>
                        <p><?php echo htmlspecialchars($post['content']); ?></p>
                        <div class="tweet-actions">
                            <form method="post">
                                <input type="hidden" name="action" value="like">
                                <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id']); ?>">
                                <button type="submit" <?php echo $currentUser ? '' : 'disabled'; ?>>Like (<?php echo $post['likes']; ?>)</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="action" value="retweet">
                                <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id']); ?>">
                                <button type="submit" <?php echo $currentUser ? '' : 'disabled'; ?>>Retweet (<?php echo $post['retweets']; ?>)</button>
                            </form>
                        </div>
                        <?php if (!$currentUser): ?>
                            <p class="hint">Register or log in to interact.</p>
                        <?php endif; ?>
                            <button>Reply</button>
                            <button>Retweet</button>
                            <button>Favorite</button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <aside class="sidebar">
            <?php if (!$currentUser): ?>
                <section class="card">
                    <h2>Register</h2>
                    <form method="post" enctype="multipart/form-data" class="stack">
                        <input type="hidden" name="action" value="register">
                        <label>
                            Handle
                            <input type="text" name="handle" placeholder="exampleuser" maxlength="20" required>
                        </label>
                        <label>
                            Display name
                            <input type="text" name="display" placeholder="Example User">
                        </label>
                        <label>
                            Password
                            <input type="password" name="password" required>
                        </label>
                        <label>
                            Profile picture
                            <input type="file" name="avatar" accept="image/png,image/jpeg,image/gif">
                        </label>
                        <button type="submit">Join Tweeter</button>
                    </form>
                </section>
                <section class="card">
                    <h2>Sign in</h2>
                    <form method="post" class="stack">
                        <input type="hidden" name="action" value="login">
                        <label>
                            Handle
                            <input type="text" name="handle" placeholder="exampleuser" maxlength="20" required>
                        </label>
                        <label>
                            Password
                            <input type="password" name="password" required>
                        </label>
                        <button type="submit" class="ghost">Sign in</button>
                    </form>
                </section>
            <?php else: ?>
                <section class="card profile">
                    <h2>Spotlight</h2>
                    <h3><?php echo htmlspecialchars($currentUser['display']); ?></h3>
                    <p><?php echo htmlspecialchars(domain_handle($currentUser['handle'])); ?></p>
                    <p><?php echo htmlspecialchars($currentUser['bio']); ?></p>
                </section>
                <section class="card">
                    <h2>Stats</h2>
                    <ul class="stats">
                        <li><strong><?php echo count($posts); ?></strong> tweets</li>
                        <li><strong><?php echo count($allUsers); ?></strong> users</li>
                        <li><strong>128</strong> followers</li>
                    </ul>
                </section>
                <?php if ($currentUser['admin']): ?>
                    <section class="card admin">
                        <h2>Admin panel</h2>
                        <ul class="admin-list">
                            <?php foreach ($allUsers as $user): ?>
                                <li>
                                    <span><?php echo htmlspecialchars(domain_handle($user['handle'])); ?></span>
                                    <span class="badge"><?php echo $user['verified'] ? 'Verified' : 'Unverified'; ?></span>
                                    <form method="post">
                                        <input type="hidden" name="action" value="verify">
                                        <input type="hidden" name="target" value="<?php echo htmlspecialchars($user['handle']); ?>">
                                        <button type="submit" class="ghost">Toggle</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
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
            <p>This project is in Heavy Development.</p>
            <p>© onsmrs Development</p>
        </div>
    </footer>
</body>
</html>
