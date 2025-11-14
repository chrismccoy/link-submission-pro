/**
 * Front-end JavaScript for handling the AJAX form submission.
 */
jQuery(document).ready(function ($) {
  $('#lsp-submission-form').on('submit', function (e) {
    e.preventDefault();

    const form = $(this);
    const submitButton = form.find('#lsp-submit-button');
    const messagesDiv = $('#lsp-form-messages');
    const formData = form.serialize();

    // Set the form to a "loading" state.
    submitButton.prop('disabled', true).text('Submitting...');
    messagesDiv.html('').removeClass('text-green-400 text-red-400');

    // Perform the AJAX request.
    $.ajax({
      type: 'POST',
      url: lsp_ajax.ajax_url,
      data: formData,
      success: function (response) {
        if (response.success) {
          form[0].reset();
          messagesDiv.html(response.data.message).addClass('text-green-400');
        } else {
          messagesDiv.html(response.data.message).addClass('text-red-400');
        }
      },
      error: function (xhr) {
        const errorMsg =
          xhr.responseJSON && xhr.responseJSON.data
            ? xhr.responseJSON.data.message
            : 'An unknown error occurred.';
        messagesDiv.html(errorMsg).addClass('text-red-400');
      },
      complete: function () {
        submitButton.prop('disabled', false).text('Submit Link');
      },
    });
  });
});
