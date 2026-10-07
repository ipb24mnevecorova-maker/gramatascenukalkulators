<?php
session_start();
require __DIR__ . '/calculator.php';

$is_admin = !empty($_SESSION['admin']);

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function sel(string $field, string $value, string $default = ''): string {
    return (($_POST[$field] ?? $default) === $value) ? ' selected' : '';
}
function val(string $field, string $default = ''): string { return e($_POST[$field] ?? $default); }
function money(float $x): string { return number_format($x, 2, ',', ' ') . ' €'; }
function cell(array $prices, bool $from_post, string $group, string $key): string
{
    if ($from_post) {
        $raw = $_POST[$group][$key] ?? '';
        return e(is_string($raw) ? $raw : '');
    }
    return e(plain_number($prices[$group][$key]));
}

$errors = [];
$price_errors = [];
$result = null;
$message = '';
$posted_prices = false;

$saved_prices = load_prices();
$prices = $saved_prices;
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? 'calculate') : '';

if (!$is_admin) {
    if ($action !== '') {
        $outcome = calculate_book_price($_POST, $saved_prices);
        $errors  = $outcome['errors'];
        $result  = $outcome['result'];
    }
} else {
    if ($action === 'calculate') {
        $_POST = fill_blank_prices($_POST, $saved_prices);
    }

    if ($action === 'reset') {
        if (reset_prices()) {
            $message = 'Cenas atjaunotas uz sākotnējām.';
        } else {
            $errors[] = 'Neizdevās atjaunot cenas.';
        }
        $saved_prices = $prices = load_prices();
        $result = calculate_book_price($_POST, $prices)['result'];
    } elseif ($action === 'calculate' || $action === 'save_prices') {
        $posted_prices = true;
        ['prices' => $form_prices, 'errors' => $price_errors] = parse_prices_form($_POST);

        if ($price_errors) {
            $errors = $price_errors;
        } else {
            $prices = $form_prices;

            if ($action === 'save_prices') {
                if (save_prices($prices)) {
                    $saved_prices = $prices;
                    $message = 'Cenas saglabātas.';
                } else {
                    $errors[] = 'Neizdevās saglabāt failu prices.json. Pārbaudi, vai mape ir rakstāma.';
                }
            }

            $outcome = calculate_book_price($_POST, $prices);
            if ($action === 'calculate') {
                $errors = array_merge($errors, $outcome['errors']);
            }
            $result = $outcome['result'];
        }
    }
}

$unsaved = $posted_prices && !$price_errors && $prices != $saved_prices;
$prices_open = true;

if ($posted_prices) {
    $vat_value = is_string($_POST['vat_percent'] ?? null) ? $_POST['vat_percent'] : '';
} else {
    $vat_value = plain_number($prices['vat_percent']);
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grāmatu cenu kalkulators</title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>" defer></script>
</head>
<body>
<main>
    <div class="admin-bar">
        <?php if ($is_admin): ?>
            <span>Admin režīms</span>
            <a href="logout.php" class="button">Iziet</a>
        <?php else: ?>
            <a href="login.php" class="button">Admin</a>
        <?php endif; ?>
    </div>

    <h1>Grāmatu cenu kalkulators</h1>

    <form action="index.php#rezultats" method="post">

        <fieldset>
            <legend>Grāmata</legend>

            <div class="field">
                <label for="book_format">Formāts</label>
                <select id="book_format" name="book_format" onchange="toggleInput()">
                    <option value="a5"<?= sel('book_format', 'a5', 'a5') ?>>A5 (148 x 210 mm)</option>
                    <option value="a4"<?= sel('book_format', 'a4') ?>>A4 (210 x 297 mm)</option>
                    <option value="pocket"<?= sel('book_format', 'pocket') ?>>Kabatas formāts</option>
                    <option value="custom"<?= sel('book_format', 'custom') ?>>Nestandarta</option>
                </select>
            </div>

            <div id="extraField" class="field" hidden>
                <label for="custom_width">Izmēri (mm)</label>
                <div>
                    <input type="number" id="custom_width" name="custom_width" min="50" max="500" step="1" placeholder="platums" value="<?= val('custom_width') ?>">
                    ×
                    <input type="number" id="custom_height" name="custom_height" min="50" max="500" step="1" placeholder="augstums" value="<?= val('custom_height') ?>">
                </div>
            </div>

            <div class="field">
                <label for="pages">Lappušu skaits</label>
                <input type="number" id="pages" name="pages" min="1" max="2000" step="1" required value="<?= val('pages') ?>">
            </div>

            <div class="field">
                <label for="copies">Eksemplāru skaits</label>
                <input type="number" id="copies" name="copies" min="1" max="10000" step="1" required value="<?= val('copies', '1') ?>">
            </div>
        </fieldset>

        <fieldset>
            <legend>Druka un vāks</legend>

            <div class="field">
                <label for="printing_type">Druka</label>
                <select id="printing_type" name="printing_type">
                    <option value="blackandwhite"<?= sel('printing_type', 'blackandwhite', 'blackandwhite') ?>>Melnbalta</option>
                    <option value="color"<?= sel('printing_type', 'color') ?>>Krāsaina</option>
                </select>
            </div>

            <div class="field">
                <label for="book_cover">Vāka veids</label>
                <select id="book_cover" name="book_cover">
                    <option value="softcover"<?= sel('book_cover', 'softcover', 'softcover') ?>>Mīksts</option>
                    <option value="hardcover"<?= sel('book_cover', 'hardcover') ?>>Ciets</option>
                </select>
            </div>

            <div class="field">
                <label for="cover_finish">Vāka apdare</label>
                <select id="cover_finish" name="cover_finish">
                    <option value="none"<?= sel('cover_finish', 'none', 'none') ?>>Bez apdares</option>
                    <option value="matte"<?= sel('cover_finish', 'matte') ?>>Matēts lamināts</option>
                    <option value="gloss"<?= sel('cover_finish', 'gloss') ?>>Spīdīgs lamināts</option>
                    <option value="uv"<?= sel('cover_finish', 'uv') ?>>UV lakojums</option>
                </select>
            </div>

            <div class="field">
                <label for="book_binding">Iesiešanas veids</label>
                <select id="book_binding" name="book_binding">
                    <option value="glued"<?= sel('book_binding', 'glued', 'glued') ?>>Līmēts (termolīme)</option>
                    <option value="sewn_glued"<?= sel('book_binding', 'sewn_glued') ?>>Šūts un līmēts</option>
                    <option value="saddle"<?= sel('book_binding', 'saddle') ?>>Skavotas</option>
                    <option value="spiral"<?= sel('book_binding', 'spiral') ?>>Spirāle</option>
                </select>
            </div>
        </fieldset>

        <fieldset>
            <legend>Citi parametri</legend>
            <textarea id="other_params" name="other_params" rows="3" maxlength="500"
                placeholder="Piemēram, īpašs papīrs, termiņš... (nav obligāti)"><?= val('other_params') ?></textarea>
        </fieldset>

        <input type="hidden" name="tame_number" value="<?= e($result['tame_number'] ?? (is_string($_POST['tame_number'] ?? null) ? $_POST['tame_number'] : '')) ?>">

        <p>
            <button type="submit" name="action" value="calculate" class="primary">
                <?= $result ? 'Pārrēķināt' : 'Aprēķināt' ?>
            </button>
        </p>

        <div id="rezultats">
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

            <?php if ($unsaved): ?>
                <p class="msg-warn">Aprēķinā izmantotas nesaglabātas cenas. Nospied «Saglabāt cenas», lai tās paturētu.</p>
            <?php endif; ?>

            <?php if ($result): ?>
                <?php $c = $result['copies']; ?>

                <table class="result">
                        <thead>
                            <tr>
                                <th>Pozīcija</th>
                                <th class="num">1 eksemplārs</th>
                                <th class="num">Tirāža (<?= $c ?> eks.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    Iekšlapas<br>
                                    <small><?= $result['pages'] ?> lpp × <?= number_format($result['page_price'], 3, ',', ' ') ?> € × <?= number_format($result['factor'], 2, ',', ' ') ?> (formāta koeficients)</small>
                                </td>
                                <td class="num"><?= money($result['inside']) ?></td>
                                <td class="num"><?= money($result['inside'] * $c) ?></td>
                            </tr>
                            <tr>
                                <td>Vāks</td>
                                <td class="num"><?= money($result['cover']) ?></td>
                                <td class="num"><?= money($result['cover'] * $c) ?></td>
                            </tr>
                            <tr>
                                <td>Vāka apdare</td>
                                <td class="num"><?= money($result['finish']) ?></td>
                                <td class="num"><?= money($result['finish'] * $c) ?></td>
                            </tr>
                            <tr>
                                <td>Iesiešana</td>
                                <td class="num"><?= money($result['binding']) ?></td>
                                <td class="num"><?= money($result['binding'] * $c) ?></td>
                            </tr>
                            <tr class="sum">
                                <td>Cena bez PVN</td>
                                <td class="num"><?= money($result['per_copy']) ?></td>
                                <td class="num"><?= money($result['total']) ?></td>
                            </tr>
                            <tr>
                                <td>PVN <?= number_format($result['vat_percent'], 1, ',', ' ') ?>%</td>
                                <td class="num"><?= money($result['per_copy_vat_amount']) ?></td>
                                <td class="num"><?= money($result['vat_amount']) ?></td>
                            </tr>
                            <tr class="final">
                                <td>Cena ar PVN</td>
                                <td class="num"><?= money($result['per_copy_vat']) ?></td>
                                <td class="num"><?= money($result['total_vat']) ?></td>
                            </tr>
                        </tbody>
                    </table>


                <p style="margin-top: 16px;">
                    <button type="button" id="toggleBtn" class="primary">Skatīt tāmi</button>
                </p>

                <div id="tameWrap" hidden>
                <div id="tameContainer" style="background: #ffffff; padding: 20px; border-radius: 8px;">
                    <h2>Grāmatas tipogrāfijas tāme</h2>

                    <ul style="list-style: none; padding-left: 0;">
                        <li>Izdevniecība "Iedvesmas Grāmata"</li>
                        <li>Datums: <?= date('Y-m-d') ?></li>
                        <li>Tāmes numurs: <strong><?= e($result['tame_number']) ?></strong></li>
                        <?php foreach ($result['specs'] as $label => $value): ?>
                            <li style="margin-bottom: 4px;"><?= e($label) ?>: <strong><?= e($value) ?></strong></li>
                        <?php endforeach; ?>
                    </ul>

                    <table class="result">
                        <thead>
                            <tr>
                                <th>Pozīcija</th>
                                <th class="num">1 eksemplārs</th>
                                <th class="num">Tirāža (<?= $c ?> eks.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    Iekšlapas<br>
                                    <small><?= $result['pages'] ?> lpp × <?= number_format($result['page_price'], 3, ',', ' ') ?> € × <?= number_format($result['factor'], 2, ',', ' ') ?> (formāta koeficients)</small>
                                </td>
                                <td class="num"><?= money($result['inside']) ?></td>
                                <td class="num"><?= money($result['inside'] * $c) ?></td>
                            </tr>
                            <tr>
                                <td>Vāks</td>
                                <td class="num"><?= money($result['cover']) ?></td>
                                <td class="num"><?= money($result['cover'] * $c) ?></td>
                            </tr>
                            <tr>
                                <td>Vāka apdare</td>
                                <td class="num"><?= money($result['finish']) ?></td>
                                <td class="num"><?= money($result['finish'] * $c) ?></td>
                            </tr>
                            <tr>
                                <td>Iesiešana</td>
                                <td class="num"><?= money($result['binding']) ?></td>
                                <td class="num"><?= money($result['binding'] * $c) ?></td>
                            </tr>
                            <tr class="sum">
                                <td>Cena bez PVN</td>
                                <td class="num"><?= money($result['per_copy']) ?></td>
                                <td class="num"><?= money($result['total']) ?></td>
                            </tr>
                            <tr>
                                <td>PVN <?= number_format($result['vat_percent'], 1, ',', ' ') ?>%</td>
                                <td class="num"><?= money($result['per_copy_vat_amount']) ?></td>
                                <td class="num"><?= money($result['vat_amount']) ?></td>
                            </tr>
                            <tr class="final">
                                <td>Cena ar PVN</td>
                                <td class="num"><?= money($result['per_copy_vat']) ?></td>
                                <td class="num"><?= money($result['total_vat']) ?></td>
                            </tr>
                        </tbody>
                    </table>

                </div>

                <p style="margin-top: 16px;">
                    <button type="button" id="downloadPdfBtn" class="primary" onclick="downloadTamePdf('<?= e($result['tame_number']) ?>')">Lejupielādēt PDF</button>
                </p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_admin): ?>
        <details class="prices"<?= $prices_open ? ' open' : '' ?>>
            <summary><strong>Cenas (var mainīt)</strong></summary>

            <p><small>Maini cenas un nospied «Skatīt tāmi», lai redzētu jauno rezultātu. «Saglabāt cenas» paturēs tās nākamajām reizēm.</small></p>

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
                                            value="<?= cell($prices, $posted_prices, $group, $key) ?>">
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

            <p>
                <button type="submit" name="action" value="save_prices" formnovalidate>Saglabāt cenas</button>
                <button type="submit" name="action" value="reset" formnovalidate
                    onclick="return confirm('Atjaunot visas cenas uz sākotnējām?');">Atjaunot sākotnējās cenas</button>
            </p>
        </details>
        <?php endif; ?>
    </form>
</main>
</body>
</html>