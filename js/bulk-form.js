(function ($) {

  'use strict';

  Drupal.behaviors.elasticsearchViewsBulkForm = {
    attach: function (context, settings) {
      var views_form = $('.views-form', context);

      // Prepend a row for select all operations.
      var select_all_markup = $('.select-all-markup', views_form);
      // Get colspan of the table.
      var colspan = $('table th', views_form).length;

      // Copy the markup to the first row.
      $('tbody', views_form).prepend('<tr class="views-table-row-select-all even"><td colspan="' + colspan + '">' + select_all_markup.html() + '</td></tr>');

      // Handle "select all" checkbox click.
      $('.select-all input', views_form).click(function () {
        // Toggle the visibility of the "select all" markup row (if any).
        if (this.checked) {
          $('.views-table-row-select-all', views_form).show();
        }
        else {
          $('.views-table-row-select-all', views_form).hide();
        }
      });

      // Handle "select all rows in the view" button click.
      $('.select-all-pages-button input[type="submit"]', views_form).click(function (e) {
        e.preventDefault();

        $('.select-all-pages-button', views_form).hide();
        $('.select-this-page-button', views_form).show();

        $('.select-all-pages-flag', views_form).val('1');

        return false;
      });

      // Handle "select rows on this page" button click.
      $('.select-this-page-button input[type="submit"]', views_form).click(function (e) {
        e.preventDefault();

        $('.select-this-page-button', views_form).hide();
        $('.select-all-pages-button', views_form).show();

        $('.select-all-pages-flag', views_form).val('0');

        return false;
      });
    }
  };

})(jQuery);
