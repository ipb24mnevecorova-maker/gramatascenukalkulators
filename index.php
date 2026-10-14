<?php
require __DIR__ . '/calculator.php';

function sel(string $field, string $value, string $default = ''): string {
    return (($_POST[$field] ?? $default) === $value) ? ' selected' : '';
}
function val(string $field, string $default = ''): string { return e($_POST[$field] ?? $default); }

$errors = [];
$result = null;
$prices = load_prices();
$settings = load_settings();
$customer = ['name' => '', 'email' => '', 'phone' => ''];
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? 'calculate') : '';

if ($action !== '') {
    ['customer' => $customer, 'errors' => $customer_errors] = parse_customer($_POST);

    $outcome = calculate_book_price($_POST, $prices);
    $errors  = $outcome['errors'];
    $result  = $outcome['result'];

    if ($action === 'save_tame' && $result) {
        if ($customer_errors) {
            $errors = $customer_errors;
        } else {
            $id = save_tame($result, tame_meta($settings, $customer));
            if ($id !== null) {
                header('Location: tame.php?id=' . rawurlencode($id) . '&saved=1');
                exit;
            }
            $errors[] = 'Neizdevās saglabāt tāmi. Pārbaudi, vai mape ir rakstāma.';
        }
    }
}

$tame_meta = $result ? tame_meta($settings, $customer) : null;
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Grāmatu cenu kalkulators</title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>" defer></script>
</head>
<body>
<main>
    <div class="admin-bar">
        <a href="tames.php" class="button">Saglabātās tāmes</a>
        <a href="login.php" class="button">Admin</a>
    </div>

    <h1>Grāmatu cenu kalkulators</h1>

    <form action="index.php#rezultats" method="post">


        <fieldset>
            <legend>Pasūtītāja informācija</legend>

            <div class="field">
                <label for="customer_name">Vārds, uzvārds / uzņēmums</label>
                <input type="text" id="customer_name" name="customer_name" required value="<?= val('customer_name') ?>">
            </div>

            <div class="field">
                <label for="customer_email">E-pasts</label>
                <input type="email" id="customer_email" name="customer_email" required value="<?= val('customer_email') ?>">
            </div>

            <div class="field">
                <label for="customer_phone">Telefons</label>
                <input type="tel" id="customer_phone" name="customer_phone" required value="<?= val('customer_phone') ?>">
            </div>
        </fieldset>        
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



        <input type="hidden" name="tame_number" value="<?= e($result['tame_number'] ?? (is_string($_POST['tame_number'] ?? null) ? $_POST['tame_number'] : '')) ?>">

        <p>
            <button type="submit" name="action" value="calculate" class="primary">
                <?= $result ? 'Pārrēķināt' : 'Aprēķināt' ?>
            </button>
            <?php if ($result): ?>
                <a href="index.php" class="button">Jauna tāme</a>
            <?php endif; ?>
        </p>

        <div id="rezultats">
            <?php if ($errors): ?>
                <ul class="msg-err">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
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
                    <button type="submit" name="action" value="save_tame">Saglabāt tāmi</button>
                </p>

                <div id="tameWrap" hidden>
                    <?php include __DIR__ . '/tame_partial.php'; ?>

                    <p style="margin-top: 16px;">
                        <button type="button" id="downloadPdfBtn" class="primary" onclick="downloadTamePdf('<?= e($result['tame_number']) ?>')">Lejupielādēt PDF</button>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </form>
</main>
</body>
</html>