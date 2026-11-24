{{-- The diagnosis boxes ([data-icd10]): as words or a code are typed, the ICD-10 codes that fit are offered; picking one fills the text and the hidden code of the same form. --}}
<script>
(function () {
    document.querySelectorAll('[data-icd10]').forEach(function (box) {
        if (box.getAttribute('data-ready')) return; box.setAttribute('data-ready', '1');
        var input = box.querySelector('[data-icd10-text]'), list = box.querySelector('[data-icd10-results]');
        var code = box.closest('form').querySelector('[data-icd10-code]'), timer = null, last = 0;
        input.addEventListener('input', function () {
            if (code) code.value = '';   // a changed text is not the code any more
            var q = input.value.trim(), n = ++last; clearTimeout(timer);
            if (q.length < 2) { list.hidden = true; return; }
            timer = setTimeout(function () {
                fetch(box.getAttribute('data-url') + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                    .then(function (d) {
                        if (n !== last) return;
                        list.innerHTML = '';
                        d.results.forEach(function (row) {
                            var b = document.createElement('button'); b.type = 'button'; b.className = 'list-group-item list-group-item-action small';
                            var c = document.createElement('b'); c.textContent = row.code; c.dir = 'ltr'; b.appendChild(c); b.appendChild(document.createTextNode(' ' + row.text));
                            if (!row.billable) b.classList.add('text-muted');
                            b.addEventListener('click', function () { input.value = row.text; if (code) code.value = row.code; list.hidden = true; });
                            list.appendChild(b);
                        });
                        list.hidden = d.results.length === 0;
                    }).catch(function () {});
            }, 250);
        });
        document.addEventListener('click', function (e) { if (!box.contains(e.target)) list.hidden = true; });
    });
})();
</script>
