<?php

const PRICES_FILE = __DIR__ . '/prices.json';
const COUNTER_FILE = __DIR__ . '/counter.json';

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