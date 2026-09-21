/**
 * Client-Side Pagination for Premium Tables
 * Lightweight Vanilla JS — no dependencies
 *
 * Usage:
 *   initPagination(document.getElementById('myTable'));
 *   initPagination(document.getElementById('myTable'), { perPage: 25 });
 */

function initPagination(tableEl, options) {
    if (!tableEl) return;

    const opts = Object.assign({ perPage: 10 }, options || {});
    const perPageOptions = [10, 25, 50];
    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return;

    const allRows = Array.from(tbody.querySelectorAll('tr'));
    // Jika hanya ada 1 baris "tidak ada data" atau total <= minimum, skip pagination
    if (allRows.length <= perPageOptions[0]) return;

    let currentPage = 1;
    let perPage = opts.perPage;

    // ── Build Controls ──────────────────────────────────────

    // Wrapper atas (dropdown + info)
    const topWrapper = document.createElement('div');
    topWrapper.className = 'pagination-top';

    // Dropdown "Tampilkan"
    const selectWrap = document.createElement('div');
    selectWrap.className = 'pagination-select-wrap';
    selectWrap.innerHTML = '<span class="pagination-label">Tampilkan</span>';

    const select = document.createElement('select');
    select.className = 'pagination-select form-control';
    perPageOptions.forEach(function(n) {
        const opt = document.createElement('option');
        opt.value = n;
        opt.textContent = n;
        if (n === perPage) opt.selected = true;
        select.appendChild(opt);
    });

    const labelAfter = document.createElement('span');
    labelAfter.className = 'pagination-label';
    labelAfter.textContent = 'entri';

    selectWrap.appendChild(select);
    selectWrap.appendChild(labelAfter);

    // Info text
    const infoEl = document.createElement('div');
    infoEl.className = 'pagination-info';

    topWrapper.appendChild(selectWrap);
    topWrapper.appendChild(infoEl);

    // Wrapper bawah (navigation buttons)
    const navWrapper = document.createElement('div');
    navWrapper.className = 'pagination-nav';

    // ── Insert into DOM ─────────────────────────────────────

    // Insert topWrapper before the table (but inside table-section)
    const tableParent = tableEl.parentNode;
    tableParent.insertBefore(topWrapper, tableEl);

    // Insert navWrapper after the table
    if (tableEl.nextSibling) {
        tableParent.insertBefore(navWrapper, tableEl.nextSibling);
    } else {
        tableParent.appendChild(navWrapper);
    }

    // ── Render Logic ────────────────────────────────────────

    function render() {
        var totalRows = allRows.length;
        var totalPages = Math.ceil(totalRows / perPage);
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        var start = (currentPage - 1) * perPage;
        var end = start + perPage;

        // Show/hide rows
        for (var i = 0; i < allRows.length; i++) {
            allRows[i].style.display = (i >= start && i < end) ? '' : 'none';
        }

        // Update info
        var showStart = totalRows === 0 ? 0 : start + 1;
        var showEnd = Math.min(end, totalRows);
        infoEl.textContent = 'Menampilkan ' + showStart + ' – ' + showEnd + ' dari ' + totalRows + ' data';

        // Build nav buttons
        navWrapper.innerHTML = '';

        if (totalPages <= 1) return;

        // Prev button
        var prevBtn = document.createElement('button');
        prevBtn.className = 'pagination-btn' + (currentPage === 1 ? ' disabled' : '');
        prevBtn.textContent = '‹ Prev';
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', function() {
            if (currentPage > 1) { currentPage--; render(); scrollToTable(); }
        });
        navWrapper.appendChild(prevBtn);

        // Page numbers (smart windowing)
        var pages = getPageNumbers(currentPage, totalPages);
        for (var p = 0; p < pages.length; p++) {
            var pg = pages[p];
            if (pg === '...') {
                var dots = document.createElement('span');
                dots.className = 'pagination-dots';
                dots.textContent = '…';
                navWrapper.appendChild(dots);
            } else {
                var btn = document.createElement('button');
                btn.className = 'pagination-btn' + (pg === currentPage ? ' active' : '');
                btn.textContent = pg;
                btn.dataset.page = pg;
                btn.addEventListener('click', function() {
                    currentPage = parseInt(this.dataset.page);
                    render();
                    scrollToTable();
                });
                navWrapper.appendChild(btn);
            }
        }

        // Next button
        var nextBtn = document.createElement('button');
        nextBtn.className = 'pagination-btn' + (currentPage === totalPages ? ' disabled' : '');
        nextBtn.textContent = 'Next ›';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', function() {
            if (currentPage < totalPages) { currentPage++; render(); scrollToTable(); }
        });
        navWrapper.appendChild(nextBtn);
    }

    function scrollToTable() {
        topWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function getPageNumbers(current, total) {
        if (total <= 7) {
            var arr = [];
            for (var i = 1; i <= total; i++) arr.push(i);
            return arr;
        }
        var pages = [];
        pages.push(1);
        if (current > 3) pages.push('...');
        var rangeStart = Math.max(2, current - 1);
        var rangeEnd = Math.min(total - 1, current + 1);
        for (var j = rangeStart; j <= rangeEnd; j++) pages.push(j);
        if (current < total - 2) pages.push('...');
        pages.push(total);
        return pages;
    }

    // ── Event Listeners ─────────────────────────────────────

    select.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        render();
    });

    // Initial render
    render();
}
