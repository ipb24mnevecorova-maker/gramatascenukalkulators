function toggleInput() {
    const isCustom = document.getElementById('book_format').value === 'custom';

    document.getElementById('extraField').hidden = !isCustom;
    document.getElementById('custom_width').required = isCustom;
    document.getElementById('custom_height').required = isCustom;
}

function downloadTamePdf(tameNumber) {
    const element = document.getElementById('tameContainer');

    if (!element) {
        alert('Tāme nav atrasta!');
        return;
    }

    const filename = tameNumber ? `${tameNumber}.pdf` : 'gramatas_tame.pdf';

    const opt = {
        margin:       10,
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  {
            scale: 2,
            useCORS: true,
            scrollX: 0,
            scrollY: 0
        },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak:    { mode: ['avoid-all', 'css'] }
    };

    html2pdf().set(opt).from(element).save();
}

document.addEventListener('DOMContentLoaded', () => {
    toggleInput();

    const btn = document.getElementById('toggleBtn');
    const wrap = document.getElementById('tameWrap');

    if (!btn || !wrap) return;   

    btn.addEventListener('click', () => {
        wrap.hidden = !wrap.hidden;
        btn.textContent = wrap.hidden ? 'Skatīt tāmi' : 'Aizvērt tāmi';
    });
});