// school-fees-system/assets/js/script.js

$(document).ready(function() {
    // Enable Bootstrap tooltips everywhere
    // This is useful for showing rejection reasons or other info on hover.
    $('[data-toggle="tooltip"], [title]').tooltip();

    // Add any other global JavaScript functionality here.
});

/*
 * Feenion Main JavaScript File
 * (CLEANED & CORRECTED)
 */

/*
 * Feenion Main JavaScript File
 * (CLEANED & CORRECTED)
 */

$(document).ready(function() {

    // --- DARK MODE TOGGLE LOGIC ---
    var $body = $('body');
    var $toggleButton = $('#dark-mode-toggle');
    var $toggleIcon = $('#dark-mode-toggle i');

    function applyTheme(theme) {
        if (theme === 'dark') {
            $body.addClass('dark-mode');
            $toggleIcon.removeClass('fa-moon').addClass('fa-sun');
            localStorage.setItem('theme', 'dark');
        } else {
            $body.removeClass('dark-mode');
            $toggleIcon.removeClass('fa-sun').addClass('fa-moon');
            localStorage.setItem('theme', 'light');
        }
    }

    var savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);

    $toggleButton.on('click', function(e) {
        e.preventDefault();
        var newTheme = $body.hasClass('dark-mode') ? 'light' : 'dark';
        applyTheme(newTheme);
    });
    // --- END OF DARK MODE LOGIC ---


    // --- DATATABLES INITIALIZATION ---
    // NOTE: Each page (expenses.php, reports.php, users.php, etc.) already
    // initializes its own table(s) with page-specific options in an inline
    // <script> block. A global catch-all here would try to initialize the
    // same table twice and trigger "Cannot reinitialise DataTable" errors,
    // so table initialization is intentionally left to each page.
    // --- END OF DATATABLES ---

});