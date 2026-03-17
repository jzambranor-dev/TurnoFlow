/**
 * TurnoFlow — Client-side table pagination
 *
 * Usage:
 *   const pag = new TablePaginator({
 *       tableId:       'myTable',
 *       pageSizeSelId: 'pageSize',
 *       paginationId:  'pagination',
 *       infoId:        'visibleCount',
 *       totalId:       'totalCount',
 *       defaultSize:   10
 *   });
 *
 *   // After external filter (search, stat-click) call:
 *   pag.refresh();
 */
(function () {
    'use strict';

    function TablePaginator(opts) {
        this.table       = document.getElementById(opts.tableId);
        this.pageSizeSel = document.getElementById(opts.pageSizeSelId);
        this.paginationEl= document.getElementById(opts.paginationId);
        this.infoEl      = document.getElementById(opts.infoId);
        this.totalEl     = document.getElementById(opts.totalId);
        this.currentPage = 1;
        this.pageSize    = parseInt(localStorage.getItem('tf_pageSize') || opts.defaultSize || 10, 10);
        this._allRows    = [];

        if (!this.table) return;

        this._allRows = Array.from(this.table.querySelectorAll('tbody tr'));

        // Set select value
        if (this.pageSizeSel) {
            this.pageSizeSel.value = this.pageSize;
            var self = this;
            this.pageSizeSel.addEventListener('change', function () {
                self.pageSize = parseInt(this.value, 10);
                localStorage.setItem('tf_pageSize', self.pageSize);
                self.currentPage = 1;
                self.render();
            });
        }

        this.render();
    }

    /**
     * Return rows that pass external filters (display !== 'none' via _filtered flag).
     * External filters set row._tfFiltered = true/false.
     */
    TablePaginator.prototype._getFilteredRows = function () {
        var rows = [];
        for (var i = 0; i < this._allRows.length; i++) {
            var row = this._allRows[i];
            if (row._tfFiltered !== false) {
                rows.push(row);
            }
        }
        return rows;
    };

    /** Call after any external filter changes */
    TablePaginator.prototype.refresh = function () {
        this.currentPage = 1;
        this.render();
    };

    TablePaginator.prototype.render = function () {
        var filtered = this._getFilteredRows();
        var total    = filtered.length;
        var pages    = Math.max(1, Math.ceil(total / this.pageSize));

        if (this.currentPage > pages) this.currentPage = pages;

        var start = (this.currentPage - 1) * this.pageSize;
        var end   = start + this.pageSize;

        // Hide all, then show only current page slice
        for (var i = 0; i < this._allRows.length; i++) {
            this._allRows[i].style.display = 'none';
        }
        for (var j = 0; j < filtered.length; j++) {
            filtered[j].style.display = (j >= start && j < end) ? '' : 'none';
        }

        var showing = Math.min(end, total) - start;
        if (total === 0) showing = 0;

        // Update info
        if (this.infoEl) this.infoEl.textContent = showing;
        if (this.totalEl) this.totalEl.textContent = total;

        // Build pagination
        this._buildPagination(pages);
    };

    TablePaginator.prototype._buildPagination = function (pages) {
        if (!this.paginationEl) return;
        var self = this;
        var el = this.paginationEl;
        el.innerHTML = '';

        if (pages <= 1) return;

        // Prev
        var prev = document.createElement('button');
        prev.innerHTML = '<svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';
        prev.disabled = this.currentPage === 1;
        prev.title = 'Anterior';
        prev.addEventListener('click', function () { self.currentPage--; self.render(); });
        el.appendChild(prev);

        // Page buttons with ellipsis
        var btns = this._pageNumbers(pages);
        for (var i = 0; i < btns.length; i++) {
            var val = btns[i];
            if (val === '...') {
                var dots = document.createElement('span');
                dots.className = 'tf-page-ellipsis';
                dots.textContent = '...';
                el.appendChild(dots);
            } else {
                var btn = document.createElement('button');
                btn.textContent = val;
                if (val === this.currentPage) btn.classList.add('active');
                btn.addEventListener('click', (function (p) {
                    return function () { self.currentPage = p; self.render(); };
                })(val));
                el.appendChild(btn);
            }
        }

        // Next
        var next = document.createElement('button');
        next.innerHTML = '<svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>';
        next.disabled = this.currentPage === pages;
        next.title = 'Siguiente';
        next.addEventListener('click', function () { self.currentPage++; self.render(); });
        el.appendChild(next);
    };

    /** Generate page numbers with ellipsis */
    TablePaginator.prototype._pageNumbers = function (pages) {
        var c = this.currentPage;
        if (pages <= 7) {
            var arr = [];
            for (var i = 1; i <= pages; i++) arr.push(i);
            return arr;
        }
        var nums = [1];
        if (c > 3) nums.push('...');
        var s = Math.max(2, c - 1);
        var e = Math.min(pages - 1, c + 1);
        for (var j = s; j <= e; j++) nums.push(j);
        if (c < pages - 2) nums.push('...');
        nums.push(pages);
        return nums;
    };

    /**
     * Helper: wrap existing search/filter logic to integrate with pagination.
     * Returns a function(filterFn) that applies the filter and refreshes pagination.
     *
     * filterFn(row) => boolean — whether the row should be visible
     */
    TablePaginator.prototype.applyFilter = function (filterFn) {
        for (var i = 0; i < this._allRows.length; i++) {
            this._allRows[i]._tfFiltered = filterFn(this._allRows[i]);
        }
        this.refresh();
    };

    // Expose globally
    window.TablePaginator = TablePaginator;
})();
