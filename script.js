(function($){
  $(document).ready(function() {
    $("#download-page-builder").on("click", function(event){
      event.preventDefault();

      jQuery.ajax({
        type: "POST",
        url: ajaxurl,
        action: "download_page_builder"
      });
    })
  });
})(jQuery);
