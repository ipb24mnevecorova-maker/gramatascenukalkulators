<?php
require __DIR__ . '/calculator.php';

$id = is_string($_GET['id'] ?? null) ? strtoupper(trim($_GET['id'])) : '';
$record = $id !== '' ? load_tame($id) : null;
$just_saved = isset($_GET['saved']);

if ($record) {
    $result = $record['result'];
    $tame_meta = $record['meta'];
    $c = $result['copies'];
    $expired = ($tame_meta['valid_until'] ?? '') < date('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Saglabātā tāme</title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>" defer></script>
</head>
<body>
<main>
    <h1>Saglabātā tāme</h1>

    <?php if ($record): ?>
        <?php if ($just_saved): ?>
            <p class="msg-ok">Tāme saglabāta.</p>
        <?php endif; ?>

        <?php if ($expired): ?>
            <p class="msg-warn">Šī piedāvājuma derīguma termiņš ir beidzies (<?= e($tame_meta['valid_until']) ?>). Cenas var būt mainījušās — aprēķini jaunu tāmi.</p>
        <?php endif; ?>

        <?php include __DIR__ . '/tame_partial.php'; ?>

        <p style="margin-top: 16px;">
            <button type="button" id="downloadPdfBtn" class="primary" onclick="downloadTamePdf('<?= e($result['tame_number']) ?>')">Lejupielādēt PDF</button>
            <a href="tames.php" class="button">← Saglabātās tāmes</a>
            <a href="index.php" class="button">Jauna tāme</a>
        </p>

        <form method="post" action="tames.php"
            onsubmit="return confirm('Dzēst tāmi <?= e($result['tame_number']) ?>? To nevar atcelt.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($result['tame_number']) ?>">
            <button type="submit">Dzēst šo tāmi</button>
        </form>
    <?php else: ?>
        <p class="msg-err">Tāme netika atrasta.</p>
        <p>
            <a href="tames.php" class="button">← Saglabātās tāmes</a>
            <a href="index.php" class="button">Jauna tāme</a>
        </p>
    <?php endif; ?>
</main>
</body>
</html>