/**
 * Admin-side JavaScript for handling AJAX approve/deny actions.
 */
jQuery(document).ready(function ($) {
  $(document).on('click', '.lsp-action-btn', function (e) {
    e.preventDefault();

    const button = $(this);
    const id = button.data('id');
    const newStatus = button.data('status');
    const row = button.closest('tr');
    const statusCell = row.find('.lsp-status');

    // Show an immediate "Updating..." feedback in the UI.
    button.closest('.row-actions').html('<em>Updating...</em>');

    // Perform the AJAX request.
    $.ajax({
      type: 'POST',
      url: lsp_admin_ajax.ajax_url,
      data: {
        action: 'lsp_update_status',
        id: id,
        status: newStatus,
        nonce: lsp_admin_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          // On success, update the UI to reflect the change.
          row.find('.row-actions').remove();
          // Update the status text and color.
          statusCell.text(
            newStatus.charAt(0).toUpperCase() + newStatus.slice(1)
          );
          statusCell.css(
            'color',
            newStatus === 'approved' ? '#00a32a' : '#d63638'
          );
        } else {
          // On failure, show an alert and restore the UI.
          alert('Error: ' + response.data.message);
          button.closest('.row-actions').html('<em>Failed! Please refresh.</em>');
        }
      },
      error: function () {
        alert('An unknown AJAX error occurred. Please refresh the page.');
        button.closest('.row-actions').html('<em>Failed! Please refresh.</em>');
      },
    });
  });
});
