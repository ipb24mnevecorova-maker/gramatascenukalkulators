<?php
require __DIR__ . '/auth.php';
require_admin();
require __DIR__ . '/calculator.php';

// The admin can change only: prices (incl. VAT), the notes shown on tāmes, and the offer validity period.

function cell(array $prices, bool $from_post, string $group, string $key): string
{
    if ($from_post) {
        $raw = $_POST[$group][$key] ?? '';
        return e(is_string($raw) ? $raw : '');
    }
    return e(plain_number($prices[$group][$key]));
}

$errors = [];
$message = '';
$posted = false;
$prices = load_prices();
$settings = load_settings();
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : '';

if ($action === 'reset') {
    if (reset_prices()) {
        $message = 'Cenas atjaunotas uz sākotnējām.';
        $prices = load_prices();
    } else {
        $errors[] = 'Neizdevās atjaunot cenas.';
    }
} elseif ($action === 'save') {
    $posted = true;
    ['prices' => $form_prices, 'errors' => $price_errors] = parse_prices_form($_POST);
    ['settings' => $form_settings, 'errors' => $settings_errors] = parse_settings_form($_POST);
    $errors = array_merge($price_errors, $settings_errors);

    if (!$errors) {
        if (save_prices($form_prices) && save_settings($form_settings)) {
            $prices = $form_prices;
            $settings = $form_settings;
            $posted = false;
            $message = 'Cenas, piezīmes un derīguma termiņš saglabāti.';
        } else {
            $errors[] = 'Neizdevās saglabāt failus prices.json / settings.json. Pārbaudi, vai mape ir rakstāma.';
        }
    }
}

$vat_value = $posted ? (is_string($_POST['vat_percent'] ?? null) ? $_POST['vat_percent'] : '') : plain_number($prices['vat_percent']);
$validity_value = $posted ? (is_string($_POST['valid_until'] ?? null) ? $_POST['valid_until'] : '') : (string) $settings['valid_until'];
$notes_value = $posted ? (is_string($_POST['notes'] ?? null) ? $_POST['notes'] : '') : $settings['notes'];
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Admin — cenas un iestatījumi</title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
<main>
    <div class="admin-bar">
        <span>Admin</span>
        <a href="logout.php" class="button">Iziet</a>
    </div>

    <h1>Cenas un iestatījumi</h1>
    <p><small>Izmaiņas attiecas uz jaunām tāmēm. Jau saglabātās tāmes nemainās.</small></p>

    <?php if ($message): ?>
        <p class="msg-ok"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <ul class="msg-err">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post">
        <div class="price-grid">
            <?php foreach (PRICE_GROUPS as $group => $info): ?>
                <div>
                    <h3><?= e($info['title']) ?></h3>
                    <table>
                        <?php foreach ($info['labels'] as $key => $label): ?>
                            <tr>
                                <td><label for="<?= e($group . '_' . $key) ?>"><?= e($label) ?></label></td>
                                <td>
                                    <input type="text" inputmode="decimal"
                                        id="<?= e($group . '_' . $key) ?>"
                                        name="<?= e($group) ?>[<?= e($key) ?>]"
                                        value="<?= cell($prices, $posted, $group, $key) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>

            <div>
                <h3>PVN</h3>
                <table>
                    <tr>
                        <td><label for="vat_percent">PVN likme (%)</label></td>
                        <td><input type="text" inputmode="decimal" id="vat_percent" name="vat_percent" value="<?= e($vat_value) ?>"></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="price-grid">
            <div>
                <h3>Cenu piedāvājuma derīgums</h3>
                <table>
                    <tr>
                        <td><label for="valid_until">Derīgs līdz</label></td>
                        <td><input type="date" id="valid_until" name="valid_until" value="<?= e($validity_value) ?>"></td>
                    </tr>
                </table>
                <small>Šis datums tiks rādīts visās jaunajās tāmēs. Kad tas pienāk, ieliec jaunu.</small>
            </div>
            <div style="flex: 1 1 320px;">
                <h3>Piezīmes tāmē</h3>
                <textarea id="notes" name="notes" rows="4" maxlength="1000"
                    placeholder="Piemēram, apmaksas noteikumi, izpildes termiņš..."><?= e($notes_value) ?></textarea>
            </div>
        </div>

        <p>
            <button type="submit" name="action" value="save" class="primary">Saglabāt</button>
            <button type="submit" name="action" value="reset" formnovalidate
                onclick="return confirm('Atjaunot visas cenas uz sākotnējām?');">Atjaunot sākotnējās cenas</button>
        </p>
    </form>
</main>
</body>
</html>