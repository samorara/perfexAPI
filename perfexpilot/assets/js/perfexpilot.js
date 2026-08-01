/**
 * PerfexPilot — Scan & Fill
 *
 * Adds a "Scan & Fill" button to the Invoice, Expense and Record Payment forms,
 * uploads a document to the module's scan endpoint, and writes the extracted
 * values into the form that is already open. Nothing is ever submitted for the
 * user: applying only fills fields.
 *
 * Every selector is tried against a candidate list and silently skipped when it
 * is not on the page, so a Perfex theme that renames or drops a field degrades
 * to "that field was not filled" instead of breaking the form.
 */
(function ($) {
    'use strict';

    if (!$ || !window.perfexpilotConfig) {
        return;
    }

    var config = window.perfexpilotConfig;

    /**
     * Per-context form detection and field mapping.
     * `probe` decides whether a matched form really is the one we want.
     */
    var CONTEXTS = {
        invoice: {
            formSelectors: ['#invoice-form', 'form[action*="invoices/invoice"]', 'form[action*="invoices/update"]'],
            probe: ['#clientid', '#number'],
            fields: {
                number: ['#number'],
                issue_date: ['#date'],
                due_date: ['#duedate'],
                currency: ['#currency'],
                reference_no: ['#reference_no', '#adminnote_reference'],
                notes: ['#adminnote', '#clientnote'],
                terms: ['#terms']
            }
        },
        expense: {
            formSelectors: ['#expense-form', 'form[action*="expenses/expense"]'],
            probe: ['#expense_name', '#category', '#amount'],
            fields: {
                expense_name: ['#expense_name'],
                category: ['#category'],
                issue_date: ['#date', '#expense_date'],
                amount: ['#amount'],
                currency: ['#currency'],
                payment_method: ['#paymentmode'],
                reference_no: ['#reference_no'],
                notes: ['#note', '#expense_note']
            }
        },
        payment: {
            formSelectors: ['#payment-form', 'form[action*="payments/payment"]', '#invoice_payment_record form', '.payment-form'],
            probe: ['#transactionid', '#paymentmode'],
            fields: {
                amount: ['#amount'],
                payment_date: ['#date', '#paymentdate'],
                payment_method: ['#paymentmode'],
                transaction_id: ['#transactionid'],
                notes: ['#note']
            }
        }
    };

    /** Which contexts may exist on the current page. */
    var candidateContexts = config.context === 'invoice' ? ['invoice', 'payment'] : [config.context];

    var state = {
        context: config.context,
        data: null,
        lookups: null,
        customer: null,
        taxChoice: null,
        mounted: {}
    };

    var $modal;

    /* ------------------------------------------------------------------
     * Small helpers
     * ---------------------------------------------------------------- */

    function csrf() {
        var source = (window.app && window.app.csrfData) ? window.app.csrfData : window.csrfData,
            out = {};

        if (source && source.token_name) {
            out[source.token_name] = source.hash;
        }

        return out;
    }

    function firstMatch(selectors, $scope) {
        for (var i = 0; i < selectors.length; i++) {
            var $found = $scope && $scope.length ? $scope.find(selectors[i]) : $(selectors[i]);

            if ($found.length) {
                return $found.first();
            }
        }

        return $();
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    function isFilled(value) {
        return value !== null && value !== undefined && value !== '';
    }

    /** Convert an extracted YYYY-MM-DD date into the admin's configured format. */
    function formatDate(iso) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');

        if (!match) {
            return iso;
        }

        var tokens = {
            Y: match[1],
            y: match[1].slice(2),
            m: match[2],
            d: match[3],
            n: String(parseInt(match[2], 10)),
            j: String(parseInt(match[3], 10))
        };

        return (config.dateformat || 'Y-m-d').replace(/[A-Za-z]/g, function (character) {
            return tokens[character] !== undefined ? tokens[character] : character;
        });
    }

    function formatNumber(value) {
        return typeof value === 'number' ? String(Math.round(value * 100) / 100) : value;
    }

    /** Strip punctuation and common legal suffixes so names compare sensibly. */
    function normalizeName(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/\b(ltd|limited|llc|inc|gmbh|srl|s\.r\.l|sas|sarl|bv|nv|plc|co|company|corp|corporation|ag|spa|oy|ab|as|pty)\b/g, ' ')
            .replace(/[^a-z0-9 ]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    /** 0..1 similarity based on shared words plus a substring bonus. */
    function similarity(a, b) {
        a = normalizeName(a);
        b = normalizeName(b);

        if (!a || !b) {
            return 0;
        }

        if (a === b) {
            return 1;
        }

        var wordsA = a.split(' '),
            wordsB = b.split(' '),
            shared = 0;

        for (var i = 0; i < wordsA.length; i++) {
            if ($.inArray(wordsA[i], wordsB) !== -1) {
                shared++;
            }
        }

        var overlap = shared / Math.max(wordsA.length, wordsB.length);

        if (a.indexOf(b) !== -1 || b.indexOf(a) !== -1) {
            overlap = Math.max(overlap, 0.85);
        }

        return overlap;
    }

    /* ------------------------------------------------------------------
     * Writing into the Perfex form
     * ---------------------------------------------------------------- */

    /** Refresh whichever enhanced-select plugin is wrapping this element. */
    function refreshSelect($element) {
        if ($element.hasClass('selectpicker') && $.fn.selectpicker) {
            $element.selectpicker('refresh');
        }

        $element.trigger('change');
    }

    /**
     * Select an option by value, then by exact text, then by partial text.
     *
     * @return {boolean} whether an option was matched
     */
    function setSelect($select, value) {
        var wanted = String(value).toLowerCase(),
            matched = null;

        $select.find('option').each(function () {
            var $option = $(this),
                optionValue = String($option.val() || '').toLowerCase(),
                optionText = String($option.text() || '').trim().toLowerCase();

            if (matched === null && (optionValue === wanted || optionText === wanted)) {
                matched = $option.val();
            }
        });

        if (matched === null) {
            $select.find('option').each(function () {
                var $option = $(this),
                    optionText = String($option.text() || '').trim().toLowerCase();

                if (matched === null && optionText && (optionText.indexOf(wanted) !== -1 || wanted.indexOf(optionText) !== -1)) {
                    matched = $option.val();
                }
            });
        }

        if (matched === null) {
            return false;
        }

        $select.val(matched);
        refreshSelect($select);

        return true;
    }

    /**
     * Write a value into the first field that exists.
     *
     * @return {boolean} whether anything was written
     */
    function setField(selectors, value, $scope) {
        if (!isFilled(value)) {
            return false;
        }

        var $element = firstMatch(selectors, $scope);

        if (!$element.length) {
            return false;
        }

        if ($element.is('select')) {
            return setSelect($element, value);
        }

        $element.val(value).trigger('change').trigger('input');

        return true;
    }

    /**
     * Point the customer picker at a customer, injecting the option when the
     * AJAX-backed select does not have it loaded yet.
     */
    function setCustomer(id, name, $scope) {
        var $select = firstMatch(['#clientid', 'select[name="clientid"]'], $scope);

        if (!$select.length) {
            return false;
        }

        if (!$select.find('option[value="' + id + '"]').length) {
            $select.append(new Option(name, id, true, true));
        }

        $select.val(String(id));
        refreshSelect($select);

        return true;
    }

    /**
     * Add one line item to the invoice items table.
     *
     * Prefers Perfex's own add_item_to_table() so the row is built exactly like a
     * hand-added one; falls back to filling the manual-entry row and clicking the
     * theme's own add button.
     */
    function addLineItem(item, taxValue) {
        var payload = {
            description: item.description || '',
            long_description: item.long_description || '',
            qty: isFilled(item.qty) ? item.qty : 1,
            rate: isFilled(item.rate) ? item.rate : 0,
            unit: item.unit || '',
            taxname: taxValue ? [taxValue] : []
        };

        if (typeof window.add_item_to_table === 'function') {
            window.add_item_to_table(payload, 'new');

            return true;
        }

        var $description = firstMatch(['#description']),
            $addButton = firstMatch(['.add-item-to-table', '#add-item-to-table']);

        if (!$description.length || !$addButton.length) {
            return false;
        }

        $description.val(payload.description);
        setField(['#long_description'], payload.long_description);
        setField(['#quantity', '#qty'], payload.qty);
        setField(['#rate'], payload.rate);
        setField(['#unit'], payload.unit);

        $addButton.trigger('click');

        return true;
    }

    function recalculateTotals() {
        if (typeof window.calculate_total === 'function') {
            window.calculate_total();
        }
    }

    /* ------------------------------------------------------------------
     * Mounting the launch button
     * ---------------------------------------------------------------- */

    function findForm(context) {
        var definition = CONTEXTS[context];

        if (!definition) {
            return $();
        }

        for (var i = 0; i < definition.formSelectors.length; i++) {
            var $form = $(definition.formSelectors[i]).first();

            if ($form.length && hasProbe($form, definition.probe)) {
                return $form;
            }
        }

        // Fall back to whichever form on the page carries the probe fields.
        var $fallback = $();

        $('form').each(function () {
            var $candidate = $(this);

            if (!$fallback.length && hasProbe($candidate, definition.probe)) {
                $fallback = $candidate;
            }
        });

        return $fallback;
    }

    function hasProbe($form, probe) {
        for (var i = 0; i < probe.length; i++) {
            if ($form.find(probe[i]).length) {
                return true;
            }
        }

        return false;
    }

    function mount(context) {
        if (state.mounted[context]) {
            // The AJAX payment drawer is torn down and rebuilt; re-mount if the
            // button went away with it.
            if ($('.perfexpilot-launch[data-context="' + context + '"]').length) {
                return;
            }

            state.mounted[context] = false;
        }

        var $form = findForm(context);

        if (!$form.length) {
            return;
        }

        var $launch = $(
            '<div class="perfexpilot-launch" data-context="' + context + '">' +
                '<button type="button" class="btn btn-info perfexpilot-launch-btn">' +
                    '<i class="fa fa-magic"></i> ' + escapeHtml(config.lang.button) +
                '</button>' +
            '</div>'
        );

        var $heading = $form.closest('.panel-body').children('h4').first();

        if ($heading.length) {
            $heading.after($launch);
        } else {
            $form.prepend($launch);
        }

        $launch.on('click', '.perfexpilot-launch-btn', function () {
            open(context);
        });

        state.mounted[context] = true;
    }

    function mountAll() {
        for (var i = 0; i < candidateContexts.length; i++) {
            mount(candidateContexts[i]);
        }
    }

    /* ------------------------------------------------------------------
     * Modal flow
     * ---------------------------------------------------------------- */

    function showStep(step) {
        $modal.find('.perfexpilot-step').addClass('hide');
        $modal.find('.perfexpilot-step[data-step="' + step + '"]').removeClass('hide');

        $('#perfexpilot_apply').toggleClass('hide', step !== 'review');
        $('#perfexpilot_rescan').toggleClass('hide', step !== 'review');
    }

    function showError(message) {
        $('#perfexpilot_error').removeClass('hide').text(message);
    }

    function open(context) {
        state.context = context;
        state.data = null;
        state.customer = null;
        state.taxChoice = null;

        $('#perfexpilot_error').addClass('hide').text('');
        $('#perfexpilot_file').val('');
        $('#perfexpilot_capture').val('');

        showStep('capture');
        loadLookups();

        $modal.modal('show');
    }

    function loadLookups() {
        if (state.lookups) {
            return;
        }

        $.getJSON(config.urls.lookup, function (response) {
            if (response && response.success) {
                state.lookups = response.data;
            }
        });
    }

    function upload(file) {
        if (!file) {
            return;
        }

        $('#perfexpilot_error').addClass('hide').text('');

        if (file.size > config.maxFileMb * 1024 * 1024) {
            showError(config.lang.tooLarge);

            return;
        }

        var form = new FormData();
        form.append('file', file);
        form.append('context', state.context);

        var tokens = csrf();
        for (var name in tokens) {
            if (Object.prototype.hasOwnProperty.call(tokens, name)) {
                form.append(name, tokens[name]);
            }
        }

        $('#perfexpilot_filename').text(file.name);
        showStep('loading');

        $.ajax({
            url: config.urls.scan,
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (!response || !response.success) {
                showStep('capture');
                showError(response && response.message ? response.message : config.lang.networkError);

                return;
            }

            state.data = response.data;
            renderReview(response);
            showStep('review');
        }).fail(function (xhr) {
            showStep('capture');

            var message = config.lang.networkError;

            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }

            showError(message);
        });
    }

    /* ------------------------------------------------------------------
     * Review panel
     * ---------------------------------------------------------------- */

    /**
     * The rows shown for the current context, in display order.
     * `apply` is the value handed to the form; `display` is what the user reads.
     */
    function reviewFields(data) {
        var labels = config.lang.fields,
            rows = [];

        function push(key, label, value, display) {
            if (!isFilled(value)) {
                return;
            }

            rows.push({
                key: key,
                label: label,
                value: value,
                display: display === undefined ? value : display,
                confidence: data.confidence.fields[key]
            });
        }

        if (state.context === 'invoice') {
            push('number', labels.number, data.number);
            push('issue_date', labels.issue_date, formatDate(data.issue_date), data.issue_date);
            push('due_date', labels.due_date, formatDate(data.due_date), data.due_date);
            push('currency', labels.currency, data.currency);
            push('reference_no', labels.reference_no, data.reference_no);
            push('notes', labels.notes, data.notes);
            push('terms', labels.terms, data.terms);
        } else if (state.context === 'expense') {
            push('expense_name', labels.expense_name, data.vendor.name
                ? data.vendor.name + (data.number ? ' ' + data.number : '')
                : data.number);
            push('category', labels.category, suggestCategory(data));
            push('issue_date', labels.issue_date, formatDate(data.issue_date), data.issue_date);
            push('amount', labels.amount, formatNumber(isFilled(data.subtotal) ? data.subtotal : data.total));
            push('currency', labels.currency, data.currency);
            push('payment_method', labels.payment_method, data.payment_method);
            push('reference_no', labels.reference_no, data.reference_no || data.number);
            push('notes', labels.notes, data.notes);
        } else {
            push('amount', labels.amount, formatNumber(isFilled(data.amount_paid) ? data.amount_paid : data.total));
            push('payment_date', labels.payment_date, formatDate(data.payment_date || data.issue_date), data.payment_date || data.issue_date);
            push('payment_method', labels.payment_method, data.payment_method);
            push('transaction_id', labels.transaction_id, data.transaction_id || data.reference_no);
            push('notes', labels.notes, data.notes);
        }

        return rows;
    }

    /** Best-matching existing expense category for the model's hint. */
    function suggestCategory(data) {
        var hint = data.expense_category_hint;

        if (!isFilled(hint) || !state.lookups || !state.lookups.expense_categories.length) {
            return hint;
        }

        var best = null,
            bestScore = 0;

        $.each(state.lookups.expense_categories, function (index, category) {
            var score = similarity(hint, category.name);

            if (score > bestScore) {
                bestScore = score;
                best = category;
            }
        });

        return bestScore >= 0.5 && best ? best.name : hint;
    }

    function renderReview(response) {
        var data = response.data,
            confidence = response.confidence || 0;

        $('#perfexpilot_confidence_value').text(confidence + '%');
        $('#perfexpilot_confidence_bar')
            .css('width', confidence + '%')
            .removeClass('is-low is-medium is-high')
            .addClass(confidence < response.threshold ? 'is-low' : (confidence < 85 ? 'is-medium' : 'is-high'));

        $('#perfexpilot_low_confidence').toggleClass('hide', confidence >= response.threshold);

        renderFieldRows(data);
        renderCustomer(data);
        renderTax(data);
        renderItems(data);
    }

    function renderFieldRows(data) {
        var rows = reviewFields(data),
            html = '';

        $.each(rows, function (index, row) {
            var confidence = isFilled(row.confidence)
                ? '<span class="perfexpilot-field-confidence">' + row.confidence + '%</span>'
                : '';

            html += '<div class="perfexpilot-field">' +
                '<div class="checkbox checkbox-primary perfexpilot-field-check">' +
                    '<input type="checkbox" class="perfexpilot-field-toggle" id="perfexpilot_field_' + index + '" ' +
                        'data-key="' + escapeHtml(row.key) + '" checked>' +
                    '<label for="perfexpilot_field_' + index + '"></label>' +
                '</div>' +
                '<div class="perfexpilot-field-label">' + escapeHtml(row.label) + confidence + '</div>' +
                '<div class="perfexpilot-field-value">' +
                    '<input type="text" class="form-control input-sm perfexpilot-field-input" ' +
                        'data-key="' + escapeHtml(row.key) + '" value="' + escapeHtml(row.value) + '">' +
                '</div>' +
            '</div>';
        });

        if (state.context === 'invoice') {
            html += renderTotalsSummary(data);
        }

        $('#perfexpilot_fields').html(html);
        $('#perfexpilot_toggle_fields').prop('checked', true);
    }

    /**
     * Document totals are shown for cross-checking only — Perfex derives invoice
     * totals from the line items, so these are never written into the form.
     */
    function renderTotalsSummary(data) {
        var labels = config.lang.fields,
            parts = [];

        $.each([
            ['subtotal', labels.subtotal],
            ['tax_total', labels.tax_total],
            ['discount_total', labels.discount_total],
            ['total', labels.total]
        ], function (index, pair) {
            if (isFilled(data[pair[0]])) {
                parts.push('<span><em>' + escapeHtml(pair[1]) + ':</em> ' + escapeHtml(formatNumber(data[pair[0]])) + '</span>');
            }
        });

        if (!parts.length) {
            return '';
        }

        return '<div class="perfexpilot-totals text-muted">' + parts.join('') + '</div>';
    }

    function renderCustomer(data) {
        var $block = $('#perfexpilot_customer_block');

        if (state.context === 'payment') {
            $block.addClass('hide').empty();

            return;
        }

        // On an invoice the document's customer is the client; on an expense the
        // vendor who issued it goes into the Customer field.
        var name = state.context === 'expense'
            ? (data.vendor.name || data.customer.name)
            : (data.customer.name || data.vendor.name);

        if (!isFilled(name)) {
            $block.addClass('hide').empty();

            return;
        }

        $block.removeClass('hide').html(
            '<div class="perfexpilot-section-head"><h5>' + escapeHtml(config.lang.fields.customer) + '</h5></div>' +
            '<p class="text-muted perfexpilot-customer-status">' + escapeHtml(config.lang.customerSearching) + '</p>' +
            '<div class="perfexpilot-customer-results"></div>'
        );

        searchCustomers(name, function (results) {
            renderCustomerResults(name, results);
        });
    }

    /**
     * Query Perfex's own relation search so no extra customer endpoint is needed,
     * falling back to the options already loaded in the picker.
     */
    function searchCustomers(name, callback) {
        var payload = $.extend({
            type: 'customer',
            q: name,
            search: name,
            rel_id: ''
        }, csrf());

        $.ajax({
            url: config.urls.customers,
            type: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function (response) {
            callback(normalizeCustomerResults(response));
        }).fail(function () {
            callback(customersFromPicker());
        });
    }

    function normalizeCustomerResults(response) {
        var list = [];

        if ($.isArray(response)) {
            list = response;
        } else if (response && $.isArray(response.results)) {
            list = response.results;
        } else if (response && $.isArray(response.data)) {
            list = response.data;
        }

        var results = [];

        $.each(list, function (index, entry) {
            if (!entry) {
                return;
            }

            var id = entry.id || entry.userid || entry.value,
                label = entry.name || entry.company || entry.text || entry.label;

            if (id && label) {
                results.push({ id: id, name: label });
            }
        });

        return results.length ? results : customersFromPicker();
    }

    function customersFromPicker() {
        var results = [];

        $('#clientid option').each(function () {
            var value = $(this).val();

            if (value) {
                results.push({ id: value, name: $(this).text() });
            }
        });

        return results;
    }

    function renderCustomerResults(name, results) {
        var $block = $('#perfexpilot_customer_block'),
            $status = $block.find('.perfexpilot-customer-status'),
            $results = $block.find('.perfexpilot-customer-results');

        var scored = $.map(results, function (customer) {
            return { customer: customer, score: similarity(name, customer.name) };
        }).sort(function (a, b) {
            return b.score - a.score;
        }).slice(0, 5);

        if (!scored.length || scored[0].score < 0.34) {
            state.customer = null;
            $status.text(config.lang.customerNone + ' (' + name + ')');
            $results.empty();

            return;
        }

        // A clear winner is selected outright; anything closer is left to the user.
        if (scored[0].score >= 0.8 && (scored.length === 1 || scored[0].score - scored[1].score >= 0.15)) {
            state.customer = scored[0].customer;
            $status.text(config.lang.customerMatched);
        } else {
            state.customer = scored[0].customer;
            $status.text(config.lang.customerCandidates);
        }

        var html = '';

        $.each(scored, function (index, entry) {
            html += '<label class="perfexpilot-customer-option' + (entry.customer.id === state.customer.id ? ' is-selected' : '') + '">' +
                '<input type="radio" name="perfexpilot_customer" value="' + escapeHtml(entry.customer.id) + '"' +
                    (entry.customer.id === state.customer.id ? ' checked' : '') + '> ' +
                escapeHtml(entry.customer.name) +
                '<span class="perfexpilot-customer-score">' + Math.round(entry.score * 100) + '%</span>' +
            '</label>';
        });

        html += '<label class="perfexpilot-customer-option">' +
            '<input type="radio" name="perfexpilot_customer" value=""> ' +
            escapeHtml(config.lang.customerSkip) +
        '</label>';

        $results.html(html);

        $results.off('change').on('change', 'input[name="perfexpilot_customer"]', function () {
            var id = $(this).val();

            state.customer = null;

            $.each(scored, function (index, entry) {
                if (String(entry.customer.id) === String(id)) {
                    state.customer = entry.customer;
                }
            });

            $results.find('.perfexpilot-customer-option').removeClass('is-selected');
            $(this).closest('.perfexpilot-customer-option').addClass('is-selected');
        });
    }

    /* ------------------------------------------------------------------
     * Tax resolver
     * ---------------------------------------------------------------- */

    /** The rate the document implies, from the totals or the line items. */
    function detectTaxRate(data) {
        if (isFilled(data.implied_tax_rate) && data.implied_tax_rate > 0) {
            return { rate: data.implied_tax_rate, implied: true };
        }

        var rates = [];

        $.each(data.line_items, function (index, item) {
            if (isFilled(item.tax_rate) && item.tax_rate > 0) {
                rates.push(item.tax_rate);
            }
        });

        if (!rates.length) {
            return null;
        }

        // Use the rate that appears most often across the lines.
        var counts = {}, best = rates[0], bestCount = 0;

        $.each(rates, function (index, rate) {
            counts[rate] = (counts[rate] || 0) + 1;

            if (counts[rate] > bestCount) {
                bestCount = counts[rate];
                best = rate;
            }
        });

        return { rate: best, implied: false };
    }

    function findExistingTax(rate) {
        var found = null;

        if (!state.lookups) {
            return null;
        }

        $.each(state.lookups.taxes, function (index, tax) {
            if (found === null && Math.abs(tax.taxrate - rate) < 0.01) {
                found = tax;
            }
        });

        return found;
    }

    function renderTax(data) {
        var $block = $('#perfexpilot_tax_block'),
            detected = detectTaxRate(data);

        if (!detected) {
            $block.addClass('hide');
            state.taxChoice = null;

            return;
        }

        var existing = findExistingTax(detected.rate),
            rateLabel = formatNumber(detected.rate) + '%',
            html = '<p class="perfexpilot-tax-detected">' +
                escapeHtml(config.lang.taxDetected) + ' <strong>' + escapeHtml(rateLabel) + '</strong>' +
                (detected.implied ? ' <span class="text-muted">(' + escapeHtml(config.lang.taxImplied) + ')</span>' : '') +
            '</p>';

        if (existing) {
            html += '<label class="perfexpilot-tax-option">' +
                '<input type="radio" name="perfexpilot_tax" value="reuse" checked> ' +
                escapeHtml(config.lang.taxReuse) + ': <strong>' + escapeHtml(existing.name) + '</strong>' +
            '</label>';
        } else {
            html += '<label class="perfexpilot-tax-option">' +
                '<input type="radio" name="perfexpilot_tax" value="create" checked> ' +
                escapeHtml(config.lang.taxCreate) + ' (' + escapeHtml(rateLabel) + ')' +
            '</label>';
        }

        html += '<label class="perfexpilot-tax-option">' +
            '<input type="radio" name="perfexpilot_tax" value="none"> ' + escapeHtml(config.lang.taxNone) +
        '</label>';

        if (state.context === 'invoice') {
            html += '<div class="checkbox checkbox-primary">' +
                '<input type="checkbox" id="perfexpilot_tax_items" checked>' +
                '<label for="perfexpilot_tax_items">' + escapeHtml(config.lang.taxApplyToItems) + '</label>' +
            '</div>';
        }

        html += '<p class="perfexpilot-tax-status text-muted"></p>';

        $block.removeClass('hide');
        $('#perfexpilot_tax_body').html(html);

        state.taxChoice = {
            rate: detected.rate,
            existing: existing,
            mode: existing ? 'reuse' : 'create',
            resolved: existing
        };

        $('#perfexpilot_tax_body').off('change').on('change', 'input[name="perfexpilot_tax"]', function () {
            state.taxChoice.mode = $(this).val();
        });
    }

    /**
     * Turn the chosen tax option into a concrete tax record, creating it if the
     * user asked for that, then hand it to the callback (null when no tax).
     */
    function resolveTax(callback) {
        var choice = state.taxChoice;

        if (!choice || choice.mode === 'none') {
            callback(null);

            return;
        }

        if (choice.mode === 'reuse' && choice.existing) {
            callback(choice.existing);

            return;
        }

        var payload = $.extend({
            rate: choice.rate,
            name: 'TAX ' + formatNumber(choice.rate) + '%'
        }, csrf());

        $.ajax({
            url: config.urls.resolveTax,
            type: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function (response) {
            if (response && response.success && response.tax) {
                $('.perfexpilot-tax-status')
                    .removeClass('text-danger')
                    .text(response.created ? config.lang.taxCreated : config.lang.taxMatched);

                // Keep the lookup cache in step so a second scan reuses this tax.
                if (response.created && state.lookups) {
                    state.lookups.taxes.push(response.tax);
                }

                callback(response.tax);

                return;
            }

            $('.perfexpilot-tax-status').addClass('text-danger').text(response && response.message ? response.message : '');
            callback(null);
        }).fail(function () {
            $('.perfexpilot-tax-status').addClass('text-danger').text(config.lang.networkError);
            callback(null);
        });
    }

    /* ------------------------------------------------------------------
     * Line items
     * ---------------------------------------------------------------- */

    function renderItems(data) {
        var $block = $('#perfexpilot_items_block');

        if (state.context !== 'invoice') {
            $block.addClass('hide');

            return;
        }

        $block.removeClass('hide');

        if (!data.line_items.length) {
            $('#perfexpilot_items').html('<tr><td colspan="5" class="text-muted">' + escapeHtml(config.lang.noLineItems) + '</td></tr>');

            return;
        }

        var html = '';

        $.each(data.line_items, function (index, item) {
            html += '<tr>' +
                '<td class="perfexpilot-col-check">' +
                    '<div class="checkbox checkbox-primary no-margin">' +
                        '<input type="checkbox" class="perfexpilot-item-toggle" id="perfexpilot_item_' + index + '" checked>' +
                        '<label for="perfexpilot_item_' + index + '"></label>' +
                    '</div>' +
                '</td>' +
                '<td><input type="text" class="form-control input-sm" data-item="' + index + '" data-field="description" value="' + escapeHtml(item.description) + '"></td>' +
                '<td class="perfexpilot-col-num"><input type="text" class="form-control input-sm" data-item="' + index + '" data-field="qty" value="' + escapeHtml(formatNumber(item.qty)) + '"></td>' +
                '<td class="perfexpilot-col-num"><input type="text" class="form-control input-sm" data-item="' + index + '" data-field="rate" value="' + escapeHtml(formatNumber(item.rate)) + '"></td>' +
                '<td class="perfexpilot-col-num"><input type="text" class="form-control input-sm" data-item="' + index + '" data-field="tax_rate" value="' + escapeHtml(formatNumber(item.tax_rate)) + '"></td>' +
            '</tr>';
        });

        $('#perfexpilot_items').html(html);
        $('#perfexpilot_toggle_items').prop('checked', true);
    }

    /** Read the (possibly edited) line items the user still has ticked. */
    function selectedItems() {
        var items = [];

        $('#perfexpilot_items tr').each(function (index) {
            var $row = $(this);

            if (!$row.find('.perfexpilot-item-toggle').prop('checked')) {
                return;
            }

            var source = state.data.line_items[index] || {},
                item = $.extend({}, source);

            $row.find('input[data-field]').each(function () {
                var $input = $(this),
                    field = $input.data('field'),
                    value = $.trim($input.val());

                if (field === 'description') {
                    item.description = value;
                } else {
                    item[field] = value === '' ? null : parseFloat(value.replace(',', '.'));
                }
            });

            items.push(item);
        });

        return items;
    }

    /** Read the (possibly edited) header fields the user still has ticked. */
    function selectedFields() {
        var values = {};

        $('#perfexpilot_fields .perfexpilot-field').each(function () {
            var $row = $(this);

            if (!$row.find('.perfexpilot-field-toggle').prop('checked')) {
                return;
            }

            var $input = $row.find('.perfexpilot-field-input');

            values[$input.data('key')] = $.trim($input.val());
        });

        return values;
    }

    /* ------------------------------------------------------------------
     * Apply
     * ---------------------------------------------------------------- */

    function apply() {
        var values = selectedFields(),
            items = state.context === 'invoice' ? selectedItems() : [],
            hasCustomer = state.customer !== null;

        if ($.isEmptyObject(values) && !items.length && !hasCustomer) {
            showError(config.lang.nothingSelected);

            return;
        }

        resolveTax(function (tax) {
            applyToForm(values, items, tax);

            $modal.modal('hide');

            if (typeof window.alert_float === 'function') {
                window.alert_float('success', config.lang.applied);
            }
        });
    }

    function applyToForm(values, items, tax) {
        var definition = CONTEXTS[state.context],
            $form = findForm(state.context);

        $.each(definition.fields, function (key, selectors) {
            if (Object.prototype.hasOwnProperty.call(values, key)) {
                setField(selectors, values[key], $form);
            }
        });

        if (state.customer && state.context !== 'payment') {
            setCustomer(state.customer.id, state.customer.name, $form);
        }

        if (state.context === 'expense') {
            applyExpenseTax(tax, $form);
        }

        if (state.context === 'invoice') {
            applyInvoiceItems(items, tax);
        }
    }

    /**
     * Expense tax selects hold tax ids, unlike the invoice item selects.
     * A document carrying a second distinct rate fills Tax 2 as well, but only
     * from a tax that already exists — a second tax is never created silently.
     */
    function applyExpenseTax(tax, $form) {
        if (!tax) {
            return;
        }

        setField(['#tax'], tax.id, $form);

        var secondary = detectSecondaryTaxRate(state.data, tax.taxrate);

        if (secondary === null) {
            return;
        }

        var existing = findExistingTax(secondary);

        if (existing) {
            setField(['#tax2'], existing.id, $form);
        }
    }

    /** A second, distinct tax rate on the document, or null. */
    function detectSecondaryTaxRate(data, primaryRate) {
        var found = null;

        $.each(data.line_items, function (index, item) {
            if (found === null && isFilled(item.tax_rate) && item.tax_rate > 0
                && Math.abs(item.tax_rate - primaryRate) >= 0.01) {
                found = item.tax_rate;
            }
        });

        return found;
    }

    function applyInvoiceItems(items, tax) {
        if (!items.length) {
            return;
        }

        var applyTaxToItems = $('#perfexpilot_tax_items').length
            ? $('#perfexpilot_tax_items').prop('checked')
            : false;

        $.each(items, function (index, item) {
            var taxValue = null;

            if (tax && applyTaxToItems) {
                taxValue = tax.value;
            }

            addLineItem(item, taxValue);
        });

        recalculateTotals();
    }

    /* ------------------------------------------------------------------
     * Wiring
     * ---------------------------------------------------------------- */

    $(function () {
        $modal = $('#perfexpilot_modal');

        if (!$modal.length) {
            return;
        }

        mountAll();

        // The Record Payment drawer and other panels arrive over AJAX, so keep
        // watching for a form to attach to.
        if (window.MutationObserver) {
            var pending = null;

            new MutationObserver(function () {
                clearTimeout(pending);
                pending = setTimeout(mountAll, 250);
            }).observe(document.body, { childList: true, subtree: true });
        }

        $('#perfexpilot_choose').on('click', function () {
            $('#perfexpilot_file').trigger('click');
        });

        $('#perfexpilot_camera').on('click', function () {
            $('#perfexpilot_capture').trigger('click');
        });

        $('#perfexpilot_file, #perfexpilot_capture').on('change', function () {
            upload(this.files && this.files[0]);
        });

        var $dropzone = $('#perfexpilot_dropzone');

        $dropzone.on('dragover dragenter', function (event) {
            event.preventDefault();
            event.stopPropagation();
            $dropzone.addClass('is-dragging');
        });

        $dropzone.on('dragleave dragend drop', function () {
            $dropzone.removeClass('is-dragging');
        });

        $dropzone.on('drop', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var transfer = event.originalEvent.dataTransfer;

            if (transfer && transfer.files && transfer.files.length) {
                upload(transfer.files[0]);
            }
        });

        $dropzone.on('click', function (event) {
            if (!$(event.target).closest('button').length) {
                $('#perfexpilot_file').trigger('click');
            }
        });

        $('#perfexpilot_toggle_fields').on('change', function () {
            $('.perfexpilot-field-toggle').prop('checked', $(this).prop('checked'));
        });

        $('#perfexpilot_toggle_items').on('change', function () {
            $('.perfexpilot-item-toggle').prop('checked', $(this).prop('checked'));
        });

        $('#perfexpilot_rescan').on('click', function () {
            $('#perfexpilot_error').addClass('hide').text('');
            $('#perfexpilot_file').val('');
            $('#perfexpilot_capture').val('');
            showStep('capture');
        });

        $('#perfexpilot_apply').on('click', apply);
    });
})(window.jQuery);
