<?php
// Expects: $result (calculate_book_price result), $tame_meta (tame_meta()), $c (copies).
$tame_customer = $tame_meta['customer'] ?? null;
$tame_notes = trim((string) ($tame_meta['notes'] ?? ''));
?>
<div id="tameContainer" style="background: #ffffff; padding: 20px; border-radius: 8px;">
    <h2>Grāmatas tipogrāfijas tāme</h2>

    <ul style="list-style: none; padding-left: 0;">
        <li>Izdevniecība "Iedvesmas Grāmata"</li>
        <li>E-pasts: izdevniecibaig@gmail.com</li>
        <li>Telefons: + 371 27155428</li>
        <li>Datums: <?= e($tame_meta['date']) ?></li>
        <li>Tāmes numurs: <strong><?= e($result['tame_number']) ?></strong></li>
        <li>Cenu piedāvājums derīgs līdz: <strong><?= e($tame_meta['valid_until']) ?></strong></li>
        <?php if (!empty($tame_customer['name'])): ?>
            <li>Pasūtītājs: <strong><?= e($tame_customer['name']) ?></strong></li>
        <?php endif; ?>
        <?php if (!empty($tame_customer['email'])): ?>
            <li>E-pasts: <strong><?= e($tame_customer['email']) ?></strong></li>
        <?php endif; ?>
        <?php if (!empty($tame_customer['phone'])): ?>
            <li>Telefons: <strong><?= e($tame_customer['phone']) ?></strong></li>
        <?php endif; ?>
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

    <?php if ($tame_notes !== ''): ?>
        <div class="tame-notes">
            <strong>Piezīmes</strong>
            <p><?= nl2br(e($tame_notes)) ?></p>
        </div>
    <?php endif; ?>
</div>