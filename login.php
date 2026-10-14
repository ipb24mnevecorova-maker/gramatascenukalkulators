<?php
require __DIR__ . '/auth.php';

if (!empty($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
    $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!hash_equals($_SESSION['csrf'], is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        $error = 'Nederīgs pieprasījums.';
    } elseif (admin_login_ok($user, $pass)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        sleep(1);
        $error = 'Nepareizs lietotājvārds vai parole.';
    }
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Admin pieslēgšanās</title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
<main class="narrow">
    <h1>Admin pieslēgšanās</h1>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <fieldset>
            <legend>Pieslēgšanās</legend>

            <?php if ($error): ?>
                <ul class="msg-err">
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                </ul>
            <?php endif; ?>

            <div class="field">
                <label for="username">Lietotājvārds</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>

            <div class="field">
                <label for="password">Parole</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
        </fieldset>

        <p>
            <button type="submit" class="primary">Pieslēgties</button>
            <a href="index.php" class="button">← Atpakaļ uz kalkulatoru</a>
        </p>
    </form>
</main>
</body>
</html>