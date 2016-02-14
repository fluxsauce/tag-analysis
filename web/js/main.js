/*eslint-env jquery */
$(function() {
  "use strict";
  $("#analysis tr").click(function() {
    var row = $(this).closest("tr"),
        tagName;
    // Clear active row in tag table.
    $("#analysis tr").removeClass("success");
    // Clear all highlighted rows in source.
    $("#source span").removeClass("highlight");

    // Determine tag.
    tagName = row.attr("class").substr("analysis_".length);

    // Highlight row in analysis.
    row.addClass("success");

    // Highlight tags in source.
    $("#source .tag_" + tagName).addClass("highlight");
  });
});
