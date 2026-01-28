console.log('Speed Matrix Admin JS loaded');
jQuery(document).ready(function ($) {
  // Tab navigation
  $('.nav-item').on('click', function (e) {
    e.preventDefault();
    var tab = $(this).data('tab');
    $('.nav-item').removeClass('active');
    $(this).addClass('active');
    $('.content-tab').removeClass('active');
    $('#' + tab).addClass('active');
    localStorage.setItem('speedMatrixActiveTab', tab);
  });

  // Restore active tab
  var activeTab = localStorage.getItem('speedMatrixActiveTab');
  if (activeTab) {
    $('.nav-item[data-tab="' + activeTab + '"]').click();
  }

  // Preset handling
  const presets = {
    basic: {
      enable_page_cache: true,
      minify_html: true,
      minify_css: true,
      minify_js: true,
      defer_js: true,
      lazy_load: true,
      disable_emojis: true,
      enable_browser_cache: true
    },
    recommended: {
      enable_page_cache: true,
      cache_mobile_separate: true,
      enable_browser_cache: true,
      minify_html: true,
      minify_css: true,
      combine_css: false,
      async_css: true,
      minify_js: true,
      defer_js: true,
      delay_js_execution: true,
      lazy_load: true,
      enable_webp: true,
      preload_key_requests: true,
      remove_query_strings: true,
      disable_emojis: true,
      disable_embeds: true,
      disable_dashicons: true
    },
    advanced: {
      enable_page_cache: true,
      cache_mobile_separate: true,
      enable_browser_cache: true,
      minify_html: true,
      minify_inline_css: true,
      minify_inline_js: true,
      minify_css: true,
      combine_css: true,
      async_css: true,
      minify_js: true,
      combine_js: true,
      defer_js: true,
      delay_js_execution: true,
      lazy_load: true,
      lazy_load_iframes: true,
      enable_webp: true,
      preload_key_requests: true,
      optimize_google_fonts: true,
      dns_prefetch: true,
      remove_query_strings: true,
      disable_emojis: true,
      disable_embeds: true,
      disable_dashicons: true,
      disable_jquery_migrate: true
    }
  };

  $('input[name="preset_radio"]').on('change', function () {
    const presetName = $(this).val();
    const presetConfig = presets[presetName];
    $('#optimization_preset').val(presetName);
    $('input[type="checkbox"]').each(function () {
      var name = $(this).attr('name');
      if (name !== 'exclude_jquery') {
        $(this).prop('checked', false);
      }
    });
    Object.keys(presetConfig).forEach(function (key) {
      if (presetConfig[key]) {
        $('input[name="' + key + '"]').prop('checked', true);
      }
    });
  });
});

console.log(speedMatrixData);
// Export/Import Functions
function exportSettings() {
  var settings = speedMatrixData.settings;
  var dataStr =
    'data:text/json;charset=utf-8,' +
    encodeURIComponent(JSON.stringify(settings, null, 2));
  var downloadAnchor = document.createElement('a');
  downloadAnchor.setAttribute('href', dataStr);
  downloadAnchor.setAttribute('download', 'speed-matrix-settings.json');
  document.body.appendChild(downloadAnchor);
  downloadAnchor.click();
  downloadAnchor.remove();
}

function importSettings(event) {
  var file = event.target.files[0];
  if (file) {
    var reader = new FileReader();
    reader.onload = function (e) {
      try {
        var settings = JSON.parse(e.target.result);
        Object.keys(settings).forEach(function (key) {
          var element = document.querySelector('[name="' + key + '"]');
          if (element) {
            if (element.type === 'checkbox') {
              element.checked = settings[key] === '1';
            } else {
              element.value = settings[key];
            }
          }
        });
        alert(speedMatrixData.i18n.import_success);
      } catch (error) {
        alert(speedMatrixData.i18n.import_error);
      }
    };
    reader.readAsText(file);
  }
}
