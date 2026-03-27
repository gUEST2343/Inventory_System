window.AdminActions = (function($) {
    function request(url, data, options) {
        const settings = Object.assign({
            method: 'POST',
            dataType: 'json',
            beforeSend: null
        }, options || {});

        let payload;
        if (Array.isArray(data)) {
            payload = data.slice();
            payload.push({ name: 'csrf_token', value: window.ADMIN_CSRF_TOKEN || '' });
        } else {
            payload = Object.assign({}, data || {}, {
                csrf_token: window.ADMIN_CSRF_TOKEN || ''
            });
        }

        return $.ajax({
            url: url,
            type: settings.method,
            dataType: settings.dataType,
            data: payload,
            beforeSend: settings.beforeSend
        });
    }

    function showAlert(type, message, undoData) {
        const container = document.getElementById('ajaxAlertContainer');
        if (!container) {
            window.alert(message);
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'alert alert-' + type + ' alert-dismissible fade show';
        wrapper.role = 'alert';
        wrapper.innerHTML = '<span>' + escapeHtml(message) + '</span>';

        if (undoData && undoData.type && undoData.id) {
            const undoButton = document.createElement('button');
            undoButton.type = 'button';
            undoButton.className = 'btn btn-sm btn-link ms-2 p-0';
            undoButton.textContent = 'Undo';
            undoButton.addEventListener('click', function() {
                request('ajax/undo_action.php', undoData).done(function(response) {
                    showAlert(response.success ? 'success' : 'danger', response.message || 'Undo completed.');
                    window.location.reload();
                });
            });
            wrapper.appendChild(undoButton);
        }

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.setAttribute('data-bs-dismiss', 'alert');
        closeButton.setAttribute('aria-label', 'Close');
        wrapper.appendChild(closeButton);

        container.innerHTML = '';
        container.appendChild(wrapper);
    }

    function withLoading(button, loadingText) {
        if (!button) {
            return function() {};
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (loadingText || 'Saving...');

        return function restore() {
            button.disabled = false;
            button.innerHTML = originalHtml;
        };
    }

    function confirmAction(message) {
        return window.confirm(message);
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    return {
        request: request,
        showAlert: showAlert,
        withLoading: withLoading,
        confirmAction: confirmAction
    };
})(jQuery);
