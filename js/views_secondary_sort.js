/**
 * @file
 * Table dragging for secondary sorts.
 */

(function($) {
  Drupal.behaviors.ViewsSecondarySort = {
    attach: function(context, settings) {
      if (!Drupal.tableDrag) {
        return;
      }

      var tableDrag = Drupal.tableDrag['views-secondary-sort'];
      if (!tableDrag) {
        return;
      }

      /**
       * Handle when a field is dragged.
       */
      tableDrag.onDrop = function() {
        var sortSelector = 'input[name$="[sort]"]';

        // Check if above or below no_sort title.
        var el = this.rowObject.element;
        var regionTitle = $(el).prevAll('.views-secondary-sort-region-no_sort-title');
        var sort = +!regionTitle.size();

        // Set the value accordingly.
        var input = $(sortSelector, el);
        input.val(sort);

        // Show/hide the order selector.
        var order = $('.views-secondary-sort-order', el);
        if (sort) {
          order.removeClass('views-secondary-sort-hidden');
        }
        else {
          order.addClass('views-secondary-sort-hidden');
        }

        // Set the messages for each region.
        var regions = ['no_sort', 'sort'];
        var counts = [0, 0];
        $(sortSelector, this.table).each(function() {
          var idx = +$(this).val();
          counts[idx]++;
        });
        for (var i = 0; i < regions.length; ++i) {
          var message = $('.views-secondary-sort-region-' + regions[i] + '-message',
            this.table);
          if (counts[i]) {
            message.addClass('views-secondary-sort-region-populated');
            message.removeClass('views-secondary-sort-region-empty');
          }
          else {
            message.addClass('views-secondary-sort-region-empty');
            message.removeClass('views-secondary-sort-region-populated');
          }
        }
      };
    }
  }
})(jQuery);
