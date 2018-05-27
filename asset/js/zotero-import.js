// Kept for compatibility with Omeka < 1.2.
Omeka.zoteroImportManageSelectedActions = function() {
    var selectedOptions = $('[value="update-selected"], [value="delete-selected"], #batch-form .batch-inputs .batch-selected');
    if ($('.batch-edit td input[type="checkbox"]:checked').length > 0) {
        selectedOptions.removeAttr('disabled');
    } else {
        selectedOptions.attr('disabled', true);
        $('.batch-actions-select').val('default');
        $('.batch-actions .active').removeClass('active');
        $('.batch-actions .default').addClass('active');
    }
};

(function($, window, document) {
    $(function() {

        var batchSelect = $('#batch-form .batch-actions-select');
        batchSelect.append(
            $('<option class="batch-selected" disabled></option>').val('zotero-selected').html(Omeka.jsTranslate('Export selected to Zotero'))
        );
        batchSelect.append(
            $('<option></option>').val('zotero-all').html(Omeka.jsTranslate('Export all to Zotero'))
        );
        var batchActions = $('#batch-form .batch-actions');
        batchActions.append(
            $('<input type="submit" class="zotero-selected" formaction="zotero/export">').val(Omeka.jsTranslate('Go'))
        );
        batchActions.append(
            $('<input type="submit" class="zotero-all" formaction="zotero/export">').val(Omeka.jsTranslate('Go'))
        );
        var resourceType = window.location.pathname.split("/").pop();
        batchActions.append(
            $('<input type="hidden" name="resource_type">').val(resourceType)
        );

        // Kept for compatibility with Omeka < 1.2.
        $('.select-all').change(function() {
            Omeka.zoteroImportManageSelectedActions();
        });
        $('.batch-edit td input[type="checkbox"]').change(function() {
            Omeka.zoteroImportManageSelectedActions();
        });

    });
}(window.jQuery, window, document));
