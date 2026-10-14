<?php
require __DIR__ . '/calculator.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delete_id = is_string($_POST['id'] ?? null) ? strtoupper(trim($_POST['id'])) : '';
    delete_tame($delete_id);
    header('Location: tames.php?deleted=1');
    exit;
}

$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$rows = list_tames($search);
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Saglabātās tāmes</title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
<main>
    <h1>Saglabātās tāmes</h1>

    <?php if (isset($_GET['deleted'])): ?>
        <p class="msg-ok">Tāme dzēsta.</p>
    <?php endif; ?>

    <form method="get" action="tames.php" style="margin-bottom: 16px;">
        <input type="text" name="q" placeholder="Numurs, pasūtītājs vai e-pasts" value="<?= e($search) ?>">
        <button type="submit">Meklēt</button>
        <a href="index.php" class="button">Jauna tāme</a>
    </form>

    <?php if (!$rows): ?>
        <p><?= $search !== '' ? 'Nekas netika atrasts.' : 'Vēl nav saglabātu tāmju.' ?></p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="result">
                <thead>
                    <tr>
                        <th>Numurs</th>
                        <th>Datums</th>
                        <th>Pasūtītājs</th>
                        <th class="num">Kopā ar PVN</th>
                        <th>Derīga līdz</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><a href="tame.php?id=<?= e(rawurlencode($row['id'])) ?>"><?= e($row['id']) ?></a></td>
                            <td><?= e(substr($row['created'], 0, 10)) ?></td>
                            <td><?= e($row['name']) ?><br><small><?= e($row['email']) ?></small></td>
                            <td class="num"><?= money($row['total_vat']) ?></td>
                            <td><?= e($row['valid_until']) ?><?= $row['valid_until'] !== '' && $row['valid_until'] < $today ? ' <span class="msg-warn">(beidzies)</span>' : '' ?></td>
                            <td>
                                <form method="post" action="tames.php" style="margin: 0;"
                                    onsubmit="return confirm('Dzēst tāmi <?= e($row['id']) ?>? To nevar atcelt.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                    <button type="submit">Dzēst</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>