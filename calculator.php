<?php

const PRICES_FILE = __DIR__ . '/prices.json';
const COUNTER_FILE = __DIR__ . '/counter.json';
const SETTINGS_FILE = __DIR__ . '/settings.json';
const TAMES_DIR = __DIR__ . '/tames';

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function money(float $x): string
{
    return number_format($x, 2, ',', ' ') . ' €';
}

const LABELS = [
    'format' => [
        'a5' => 'A5 (148 x 210 mm)',
        'a4' => 'A4 (210 x 297 mm)',
        'pocket' => 'Kabatas formāts',
        'custom' => 'Nestandarta',
    ],
    'print' => ['blackandwhite' => 'Melnbalta', 'color' => 'Krāsaina'],
    'cover' => ['softcover' => 'Mīksts', 'hardcover' => 'Ciets'],
    'finish' => [
        'none' => 'Bez apdares',
        'matte' => 'Matēts lamināts',
        'gloss' => 'Spīdīgs lamināts',
        'uv' => 'UV lakojums',
    ],
    'binding' => [
        'glued' => 'Līmēts (termolīme)',
        'sewn_glued' => 'Šūts un līmēts',
        'saddle' => 'Skavotas',
        'spiral' => 'Spirāle',
    ],
];

const PRICE_GROUPS = [
    'price_per_page' => ['title' => 'Druka (€ par vienu A5 lappusi)', 'labels' => LABELS['print']],
    'format_factor' => [
        'title' => 'Formāta koeficients (A5 = 1)',
        'labels' => [
            'a5' => LABELS['format']['a5'],
            'pocket' => LABELS['format']['pocket'],
            'a4' => LABELS['format']['a4'],
        ],
    ],
    'cover_price' => ['title' => 'Vāks (€ par eksemplāru)', 'labels' => LABELS['cover']],
    'finish_price' => ['title' => 'Vāka apdare (€ par eksemplāru)', 'labels' => LABELS['finish']],
    'binding_price' => ['title' => 'Iesiešana (€ par eksemplāru)', 'labels' => LABELS['binding']],
];

function generate_tame_number(): string
{
    $year = date('Y');
    $number = 1;

    if (is_file(COUNTER_FILE)) {
        $data = json_decode((string) file_get_contents(COUNTER_FILE), true);
        if (is_array($data)) {
            $saved_year = $data['year'] ?? $year;
            $saved_number = (int) ($data['number'] ?? 0);

            if ($saved_year === $year) {
                $number = $saved_number + 1;
            }
        }
    }

    file_put_contents(COUNTER_FILE, json_encode(['year' => $year, 'number' => $number]), LOCK_EX);

    return sprintf('TM-%s-%04d', $year, $number);
}

function default_prices(): array
{
    return [
        'vat_percent' => 21.0,
        'price_per_page' => ['blackandwhite' => 0.013, 'color' => 0.05],
        'format_factor' => ['a5' => 1.00, 'pocket' => 0.70, 'a4' => 1.80],
        'cover_price' => ['softcover' => 0.75, 'hardcover' => 2.65],
        'finish_price' => ['none' => 0.00, 'matte' => 0.35, 'gloss' => 0.35, 'uv' => 0.60],
        'binding_price' => ['glued' => 1.60, 'sewn_glued' => 2.40, 'saddle' => 0.30, 'spiral' => 1.20],
    ];
}

function to_number($value): ?float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    if (!is_string($value)) {
        return null;
    }
    $value = str_replace(',', '.', trim($value));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    return (float) $value;
}

function plain_number(float $x): string
{
    return rtrim(rtrim(number_format($x, 4, '.', ''), '0'), '.');
}

function load_prices(): array
{
    $prices = default_prices();
    if (!is_file(PRICES_FILE)) {
        return $prices;
    }

    $saved = json_decode((string) file_get_contents(PRICES_FILE), true);
    if (!is_array($saved)) {
        return $prices;
    }

    $vat = to_number($saved['vat_percent'] ?? null);
    if ($vat !== null) {
        $prices['vat_percent'] = $vat;
    }

    foreach (array_keys(PRICE_GROUPS) as $group) {
        foreach ($prices[$group] as $key => $default) {
            $number = to_number($saved[$group][$key] ?? null);
            if ($number !== null) {
                $prices[$group][$key] = $number;
            }
        }
    }

    return $prices;
}

function save_prices(array $prices): bool
{
    $json = json_encode($prices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false && file_put_contents(PRICES_FILE, $json, LOCK_EX) !== false;
}

function reset_prices(): bool
{
    return !is_file(PRICES_FILE) || unlink(PRICES_FILE);
}

function fill_blank_prices(array $post, array $prices): array
{
    if (!is_string($post['vat_percent'] ?? null) || trim($post['vat_percent']) === '') {
        $post['vat_percent'] = plain_number($prices['vat_percent']);
    }

    foreach (array_keys(PRICE_GROUPS) as $group) {
        foreach ($prices[$group] as $key => $value) {
            $raw = $post[$group][$key] ?? null;
            if (!is_string($raw) || trim($raw) === '') {
                if (!isset($post[$group]) || !is_array($post[$group])) {
                    $post[$group] = [];
                }
                $post[$group][$key] = plain_number($value);
            }
        }
    }

    return $post;
}

function parse_prices_form(array $post): array
{
    $prices = default_prices();
    $errors = [];

    $vat = to_number($post['vat_percent'] ?? null);
    if ($vat === null || $vat < 0 || $vat > 100) {
        $errors[] = 'PVN likmei jābūt skaitlim no 0 līdz 100.';
    } else {
        $prices['vat_percent'] = $vat;
    }

    foreach (PRICE_GROUPS as $group => $info) {
        foreach ($info['labels'] as $key => $label) {
            $number = to_number($post[$group][$key] ?? null);
            $min = $group === 'format_factor' ? 0.01 : 0;
            if ($number === null || $number < $min || $number > 100000) {
                $errors[] = 'Nederīga vērtība: ' . $info['title'] . ', ' . $label . '.';
            } else {
                $prices[$group][$key] = $number;
            }
        }
    }

    return ['prices' => $prices, 'errors' => $errors];
}

function calculate_book_price(array $input, ?array $prices = null): array
{
    $prices ??= load_prices();
    $errors = [];

    $format  = $input['book_format'] ?? '';
    $print   = $input['printing_type'] ?? '';
    $cover   = $input['book_cover'] ?? '';
    $finish  = $input['cover_finish'] ?? '';
    $binding = $input['book_binding'] ?? '';
    $other   = trim($input['other_params'] ?? '');

    $tame_number = $input['tame_number'] ?? '';
    if (!is_string($tame_number) || !preg_match('/^TM-\d{4}-\d{4}$/', $tame_number)) {
        $tame_number = '';   // missing or invalid: a new one is generated below
    }

    $pages  = filter_var($input['pages'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2000]]);
    $copies = filter_var($input['copies'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);

    if (!in_array($format, ['a5', 'a4', 'pocket', 'custom'], true)) $errors[] = 'Nederīgs formāts.';
    if (!isset($prices['price_per_page'][$print]))                 $errors[] = 'Nederīgs drukas veids.';
    if (!isset($prices['cover_price'][$cover]))                    $errors[] = 'Nederīgs vāka veids.';
    if (!isset($prices['finish_price'][$finish]))                  $errors[] = 'Nederīga vāka apdare.';
    if (!isset($prices['binding_price'][$binding]))                $errors[] = 'Nederīgs iesiešanas veids.';
    if (!$pages)                                                   $errors[] = 'Lappušu skaitam jābūt no 1 līdz 2000.';
    if (!$copies)                                                  $errors[] = 'Eksemplāru skaitam jābūt no 1 līdz 10000.';
    if (mb_strlen($other) > 500)                                   $errors[] = 'Citi parametri ir pārāk gari.';

    $factor = $prices['format_factor'][$format] ?? null;
    $format_label = LABELS['format'][$format] ?? '';
    if ($format === 'custom') {
        $range  = ['options' => ['min_range' => 50, 'max_range' => 500]];
        $width  = filter_var($input['custom_width'] ?? null, FILTER_VALIDATE_INT, $range);
        $height = filter_var($input['custom_height'] ?? null, FILTER_VALIDATE_INT, $range);
        if (!$width || !$height) {
            $errors[] = 'Izmēriem jābūt veseliem skaitļiem no 50 līdz 500 mm.';
        } else {
            $factor = ($width * $height) / (148 * 210);
            $format_label = 'Nestandarta (' . $width . ' x ' . $height . ' mm)';
        }
    }

    if ($cover === 'hardcover' && $binding === 'saddle') {
        $errors[] = 'Skavotas nav pieejamas kopā ar cieto vāku.';
    }

    if ($errors) {
        return ['errors' => $errors, 'result' => null];
    }

    $page_price = $prices['price_per_page'][$print];
    $inside = round($pages * $page_price * $factor, 2);
    $cover_cost = round($prices['cover_price'][$cover], 2);
    $finish_cost = round($prices['finish_price'][$finish], 2);
    $binding_cost = round($prices['binding_price'][$binding], 2);
    $subtotal = round($inside + $cover_cost + $finish_cost + $binding_cost, 2);

    $vat_percent = $prices['vat_percent'];
    $per_copy = $subtotal;
    $per_copy_vat = round($per_copy * (1 + $vat_percent / 100), 2);
    $total = round($per_copy * $copies, 2);
    $total_vat = round($total * (1 + $vat_percent / 100), 2);

    $specs = [
        'Formāts' => $format_label,
        'Lappušu skaits' => (string) $pages,
        'Tirāža' => $copies . ' eks.',
        'Druka' => LABELS['print'][$print],
        'Vāka veids' => LABELS['cover'][$cover],
        'Vāka apdare' => LABELS['finish'][$finish],
        'Iesiešana' => LABELS['binding'][$binding],
    ];
    if ($other !== '') {
        $specs['Citi parametri'] = $other;
    }

    return [
        'errors' => [],
        'result' => [
            'tame_number' => $tame_number !== '' ? $tame_number : generate_tame_number(),
            'specs' => $specs,
            'copies' => $copies,
            'pages' => $pages,
            'page_price' => $page_price,
            'factor' => $factor,
            'inside' => $inside,
            'cover' => $cover_cost,
            'finish' => $finish_cost,
            'binding' => $binding_cost,
            'per_copy' => $per_copy,
            'per_copy_vat_amount' => round($per_copy_vat - $per_copy, 2),
            'per_copy_vat' => $per_copy_vat,
            'total' => $total,
            'vat_percent' => $vat_percent,
            'vat_amount' => round($total_vat - $total, 2),
            'total_vat' => $total_vat,
        ],
    ];
}

// ---------------------------------------------------------------------------
// Admin settings: offer validity date and notes shown on every tāme
// ---------------------------------------------------------------------------

function default_settings(): array
{
    return ['valid_until' => (new DateTime())->modify('+30 days')->format('Y-m-d'), 'notes' => ''];
}

function load_settings(): array
{
    $settings = default_settings();
    if (!is_file(SETTINGS_FILE)) {
        return $settings;
    }

    $saved = json_decode((string) file_get_contents(SETTINGS_FILE), true);
    if (!is_array($saved)) {
        return $settings;
    }

    if (is_string($saved['valid_until'] ?? null) && is_valid_date($saved['valid_until'])) {
        $settings['valid_until'] = $saved['valid_until'];
    }
    if (is_string($saved['notes'] ?? null)) {
        $settings['notes'] = $saved['notes'];
    }

    return $settings;
}

function is_valid_date(string $value): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

function parse_settings_form(array $post): array
{
    $settings = default_settings();
    $errors = [];

    $raw_date = is_string($post['valid_until'] ?? null) ? trim($post['valid_until']) : '';
    if (!is_valid_date($raw_date)) {
        $errors[] = 'Norādi derīgu cenu piedāvājuma derīguma datumu.';
    } elseif ($raw_date < date('Y-m-d')) {
        $errors[] = 'Derīguma datums nevar būt agrāks par šodienu.';
    } else {
        $settings['valid_until'] = $raw_date;
    }

    $notes = is_string($post['notes'] ?? null) ? trim(str_replace("\r\n", "\n", $post['notes'])) : '';
    if (mb_strlen($notes) > 1000) {
        $errors[] = 'Piezīmes ir pārāk garas (max 1000 rakstzīmes).';
    } else {
        $settings['notes'] = $notes;
    }

    return ['settings' => $settings, 'errors' => $errors];
}

function save_settings(array $settings): bool
{
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false && file_put_contents(SETTINGS_FILE, $json, LOCK_EX) !== false;
}

// ---------------------------------------------------------------------------
// Saved tāmes (stored one JSON file per tāme in tames/)
// ---------------------------------------------------------------------------

function parse_customer(array $post): array
{
    $get = fn(string $k): string => is_string($post[$k] ?? null) ? trim($post[$k]) : '';
    $customer = [
        'name'  => $get('customer_name'),
        'email' => $get('customer_email'),
        'phone' => $get('customer_phone'),
    ];
    $errors = [];

    if ($customer['name'] === '' || mb_strlen($customer['name']) > 200) {
        $errors[] = 'Norādi pasūtītāja vārdu, uzvārdu vai uzņēmumu (līdz 200 rakstzīmēm).';
    }
    if (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($customer['email']) > 200) {
        $errors[] = 'Norādi derīgu e-pasta adresi.';
    }
    if ($customer['phone'] === '' || mb_strlen($customer['phone']) > 50) {
        $errors[] = 'Norādi tālruņa numuru (līdz 50 rakstzīmēm).';
    }

    return ['customer' => $customer, 'errors' => $errors];
}

// Date, validity and notes that go onto a tāme.
function tame_meta(array $settings, ?array $customer = null): array
{
    return [
        'date'        => date('Y-m-d'),
        'valid_until' => (string) $settings['valid_until'],
        'notes'       => (string) $settings['notes'],
        'customer'    => $customer,
    ];
}

function tame_path(string $id): ?string
{
    return preg_match('/^TM-\d{4}-\d{4}$/', $id) ? TAMES_DIR . '/' . $id . '.json' : null;
}

/**
 * Stores a snapshot of the tāme (numbers, customer, validity, notes).
 * Saved tāmes are never overwritten: if the number is already taken, a new one is issued.
 * Returns the tāme number, or null on failure.
 */
function save_tame(array $result, array $meta): ?string
{
    if (!is_dir(TAMES_DIR) && !mkdir(TAMES_DIR, 0775, true) && !is_dir(TAMES_DIR)) {
        return null;
    }

    // The folder holds customer contact details, so block direct web access to it.
    $htaccess = TAMES_DIR . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }

    $id = $result['tame_number'];
    if (tame_path($id) === null || is_file(tame_path($id))) {
        $id = generate_tame_number();
        $result['tame_number'] = $id;
    }
    $path = tame_path($id);
    if ($path === null) {
        return null;
    }

    $json = json_encode(
        ['created' => date('Y-m-d H:i:s'), 'meta' => $meta, 'result' => $result],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
    if ($json === false) {
        return null;
    }

    $fh = @fopen($path, 'x');   // 'x' = fail if the file already exists
    if (!$fh) {
        return null;
    }
    $ok = fwrite($fh, $json) !== false;
    fclose($fh);

    return $ok ? $id : null;
}

function load_tame(string $id): ?array
{
    $path = tame_path($id);
    if ($path === null || !is_file($path)) {
        return null;
    }

    $record = json_decode((string) file_get_contents($path), true);
    if (!is_array($record) || !is_array($record['result'] ?? null) || !is_array($record['meta'] ?? null)) {
        return null;
    }

    return $record;
}

// Summary rows of all saved tāmes, newest first. $search matches number, name or e-mail.
function list_tames(string $search = ''): array
{
    $rows = [];
    foreach (glob(TAMES_DIR . '/TM-*.json') ?: [] as $file) {
        $record = json_decode((string) file_get_contents($file), true);
        if (!is_array($record) || !is_array($record['result'] ?? null) || !is_array($record['meta'] ?? null)) {
            continue;
        }

        $customer = $record['meta']['customer'] ?? [];
        $row = [
            'id'          => (string) ($record['result']['tame_number'] ?? basename($file, '.json')),
            'created'     => (string) ($record['created'] ?? ''),
            'valid_until' => (string) ($record['meta']['valid_until'] ?? ''),
            'name'        => (string) ($customer['name'] ?? ''),
            'email'       => (string) ($customer['email'] ?? ''),
            'total_vat'   => (float) ($record['result']['total_vat'] ?? 0),
        ];

        if ($search !== '' && mb_stripos($row['id'] . ' ' . $row['name'] . ' ' . $row['email'], $search) === false) {
            continue;
        }
        $rows[] = $row;
    }

    usort($rows, fn($a, $b) => strcmp($b['created'] . $b['id'], $a['created'] . $a['id']));
    return $rows;
}

function delete_tame(string $id): bool
{
    $path = tame_path($id);
    return $path !== null && is_file($path) && unlink($path);
}