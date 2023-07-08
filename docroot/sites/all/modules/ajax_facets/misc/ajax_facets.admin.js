(function ($) {

Drupal.behaviors.ajax_facets_admin = {
  attach: function(context, settings) {
    // Ensures ALL "update results" checkboxes boxes are updated.
    // @see http://drupal.org/node/735528
    $('input[name="show_reset_link"]').change(function() {
      if ($(this).is(':checked')) {
        $('input[name="show_reset_link"]').attr('checked', 'checked');
      }
      else {
        $('input[name="show_reset_link"]').removeAttr('checked');
      }
    });

    // Ensures ALL "reset link text" textfields are updated.
    // @see http://drupal.org/node/735528
    $('input[name="reset_link_text"]').change(function() {
      $('input[name="reset_link_text"]').val($(this).val());
    });
  }
}

})(jQuery);
