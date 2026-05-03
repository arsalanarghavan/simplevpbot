(function($){
  function showDialog(title, obj, isError){
    var $dlg = $('#svp-dialog');
    if (!$dlg.length){
      $dlg = $('<div id="svp-dialog" class="svp-dialog"><div class="svp-dialog-inner"><h3></h3><pre></pre><p><button type="button" class="button svp-close">بستن</button></p></div></div>').appendTo('body');
      $dlg.on('click', '.svp-close', function(){ $dlg.hide(); });
    }
    $dlg.find('h3').text(title);
    $dlg.find('pre').text(typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2));
    $dlg.toggleClass('is-error', !!isError);
    $dlg.show();
  }

  function post(action, extra, onSuccess, onError){
    var payload = $.extend({ action: action, nonce: simplevpbotAdmin.nonce }, extra || {});
    return $.ajax({
      url: simplevpbotAdmin.ajax,
      method: 'POST',
      dataType: 'json',
      data: payload
    }).done(function(res){
      if (res && res.success) {
        onSuccess && onSuccess(res.data);
      } else {
        var msg = (res && res.data && res.data.message) ? res.data.message : 'خطا';
        onError && onError(msg, (res && res.data) || res);
      }
    }).fail(function(xhr){
      var err = xhr && xhr.responseText ? xhr.responseText : 'Network error';
      onError && onError('خطای شبکه', err);
    });
  }

  function bindButton(id, action, okTitle){
    $(document).on('click', id, function(){
      var $b = $(this).prop('disabled', true);
      post(action, {}, function(data){
        $b.prop('disabled', false);
        showDialog(okTitle || 'انجام شد', data, false);
      }, function(msg, data){
        $b.prop('disabled', false);
        showDialog('خطا: ' + msg, data, true);
      });
    });
  }

  $(document).on('click', '.svp-test-xpanel', function(){
    if (!ensureAdmin()) { return false; }
    var id = parseInt($(this).data('panel-id'), 10) || 0;
    var $b = $(this).prop('disabled', true);
    post('simplevpbot_test_panel', { panel_id: id }, function(data){
      $b.prop('disabled', false);
      showDialog('اتصال پنل برقرار است', data, false);
    }, function(msg, data){
      $b.prop('disabled', false);
      showDialog('خطا: ' + msg, data, true);
    });
    return false;
  });

  bindButton('#svp-traffic-cap-repair', 'simplevpbot_traffic_cap_repair_db', 'اصلاح سقف حجم');
  bindButton('#svp-test-tg',      'simplevpbot_test_telegram', 'Telegram OK');
  bindButton('#svp-set-wh-tg',    'simplevpbot_set_webhook_tg','Webhook تلگرام تنظیم شد');
  bindButton('#svp-set-wh-bale',  'simplevpbot_set_webhook_bale','Webhook بله تنظیم شد');
  bindButton('#svp-backup-now',   'simplevpbot_backup_now',    'بکاپ ارسال شد');

  $(document).on('click', '#svp-restore-btn', function(){
    if (!ensureAdmin()) { window.alert('اسکریپت ادمین لود نشده.'); return; }
    var f = document.getElementById('svp-restore-file');
    if (!f || !f.files || !f.files[0]) { window.alert('یک فایل zip انتخاب کنید.'); return; }
    if (!$('#svp-restore-confirm').prop('checked')) { window.alert('تایید را بزنید.'); return; }
    var $b = $(this).prop('disabled', true);
    var fd = new FormData();
    fd.append('action', 'simplevpbot_restore_backup');
    fd.append('nonce', simplevpbotAdmin.nonce);
    fd.append('confirm', '1');
    fd.append('file', f.files[0]);
    $.ajax({
      url: simplevpbotAdmin.ajax,
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done(function(res){
      $b.prop('disabled', false);
      if (res && res.success) {
        showDialog('ریستور انجام شد', res.data || '', false);
      } else {
        var msg = (res && res.data && res.data.message) ? res.data.message : 'خطا';
        showDialog('خطا: ' + msg, res && res.data || res, true);
      }
    }).fail(function(xhr){
      $b.prop('disabled', false);
      showDialog('خطای شبکه', xhr && xhr.responseText ? xhr.responseText : '', true);
    });
  });

  function ensureAdmin(){
    if ( typeof simplevpbotAdmin === 'undefined' || ! simplevpbotAdmin || ! simplevpbotAdmin.ajax ) {
      return false;
    }
    return true;
  }

  function svpInboundPanelId(){
    var $p = $('#svp-inb-panel-id');
    if (!$p.length) return 1;
    var v = parseInt($p.val(), 10);
    if (isNaN(v)) return 1;
    return v;
  }

  $(document).on('click', '#svp-inb-load', function(e){
    e.preventDefault();
    e.stopPropagation();
    if (!ensureAdmin()) { window.alert('اسکریپت ادمین لود نشده. صفحه را با Ctrl+F5 رفرش کنید.'); return false; }
    var $b = $(this).prop('disabled', true);
    post('simplevpbot_inbounds_list', { panel_id: svpInboundPanelId() }, function(data){
      $b.prop('disabled', false);
      var $sel = $('#svp-inb-sel').empty().append($('<option value="">—</option>'));
      var list = (data && Array.isArray( data.inbounds ) ) ? data.inbounds : [];
      list.forEach(function(inb){
        $sel.append($('<option/>').val(inb.id).text('#' + inb.id + ' ' + (inb.remark || '') + ' :' + inb.port + ' ' + inb.protocol));
      });
      $('#svp-inb-clients').prop('disabled', list.length === 0);
      $('#svp-inb-autolink').prop('disabled', list.length === 0);
    }, function(msg, extra){
      $b.prop('disabled', false);
      showDialog( 'بارگذاری Inbound: خطا', ( msg || 'خطا' ) + ( extra && typeof extra === 'string' ? ( '\n' + extra ) : ( extra ? '\n' + JSON.stringify( extra ) : '' ) ), true );
    });
    return false;
  });
  var svpInbCache = [];
  var svpInbCurrentId = 0;

  function svpFormatExpiry(ms){
    var n = parseInt(ms, 10) || 0;
    if (!n) return '♾️';
    var d = new Date(n);
    if (isNaN(d.getTime())) return '—';
    var pad = function(x){ return (x < 10 ? '0' : '') + x; };
    return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
  }

  function svpBestComment(c){
    var keys = ['comment', 'remark', 'memo', 'note', 'desc'];
    for (var i = 0; i < keys.length; i++) {
      var v = c[keys[i]];
      if (typeof v === 'string' && v.length && v.trim().length) { return v.trim(); }
    }
    return '';
  }

  function svpRenderInbClients(){
    var $w = $('#svp-inb-clients-wrap').empty();
    var filter = ($('#svp-inb-filter').val() || 'all');
    var total = svpInbCache.length;
    var items = svpInbCache.filter(function(c){
      var linked = !!(c.is_linked || c.linked_user_id);
      if (filter === 'linked') return linked;
      if (filter === 'unlinked') return !linked;
      return true;
    });
    var linkedCount = svpInbCache.filter(function(c){ return !!(c.is_linked || c.linked_user_id); }).length;
    var $head = $('<p class="description"/>');
    $head.text('کل: ' + total + ' | متصل: ' + linkedCount + ' | بدون کاربر: ' + (total - linkedCount) + ' | نمایش: ' + items.length);
    $w.append($head);
    var $table = $('<table class="widefat svp-inb-table"><thead><tr>' +
      '<th>email</th>' +
      '<th>کامنت (پنل)</th>' +
      '<th>یادداشت</th>' +
      '<th>حجم (GB)</th>' +
      '<th>انقضا</th>' +
      '<th>کاربر ربات</th>' +
      '<th>وضعیت</th>' +
      '<th></th></tr></thead><tbody></tbody></table>');
    $w.append($table);
    var $tb = $table.find('tbody');
    items.forEach(function(c){
      var isLinked = !!(c.is_linked || c.linked_user_id);
      var $tr = $('<tr/>').addClass(isLinked ? 'svp-row-linked' : 'svp-row-unlinked');
      $tr.append($('<td class="ltr"/>').text(c.email));
      $tr.append($('<td/>').text(svpBestComment(c) || '—'));
      $tr.append($('<td/>').text(c.remark || '—'));
      $tr.append($('<td class="ltr"/>').text(c.total_gb ? c.total_gb : '♾️'));
      $tr.append($('<td class="ltr"/>').text(svpFormatExpiry(c.expiry_ms)));
      if (isLinked) {
        $tr.append($('<td/>').html('<strong>#' + c.linked_user_id + '</strong> ' + $('<div/>').text(c.linked_user_label || '').html()));
        var provLabel = (c.provision_type === 'linked') ? 'متصل (دستی)' : 'متصل (خودکار)';
        $tr.append($('<td/>').html('<span class="svp-status svp-status-ok">' + provLabel + '</span>'));
        $tr.append($('<td/>').text('—'));
      } else {
        $tr.append($('<td/>').text('—'));
        $tr.append($('<td/>').html('<span class="svp-status svp-status-bad">بدون کاربر</span>'));
        var $inp = $('<input type="number" class="small-text svp-inb-uid" min="1" placeholder="svp_users.id"/>');
        var $btn = $('<button type="button" class="button button-small svp-inb-link-row"/>').text('اتصال');
        $btn.data({ inbound_id: svpInbCurrentId, email: c.email });
        var $td = $('<td/>').append($inp).append(' ').append($btn);
        $tr.append($td);
      }
      $tb.append($tr);
    });
  }

  $(document).on('click', '#svp-inb-clients', function(e){
    e.preventDefault();
    e.stopPropagation();
    if (!ensureAdmin()) { return false; }
    var id = parseInt($('#svp-inb-sel').val(), 10);
    if (!id) { return false; }
    svpInbCurrentId = id;
    var $b = $(this).prop('disabled', true);
    post('simplevpbot_inbound_clients', { inbound_id: id, panel_id: svpInboundPanelId() }, function(data){
      $b.prop('disabled', false);
      svpInbCache = (data && Array.isArray( data.clients ) ? data.clients : []);
      svpRenderInbClients();
    }, function(msg, extra){
      $b.prop('disabled', false);
      showDialog( 'کلاینت\u200cها: خطا', ( msg || 'خطا' ) + ( extra ? '\n' + ( typeof extra === 'string' ? extra : JSON.stringify( extra, null, 2 ) ) : '' ), true );
    });
    return false;
  });
  $(document).on('change', '#svp-inb-filter', function(){ svpRenderInbClients(); });
  $(document).on('click', '#svp-inb-autolink', function(e){
    e.preventDefault();
    e.stopPropagation();
    if (!ensureAdmin()) { return false; }
    var id = parseInt($('#svp-inb-sel').val(), 10);
    if (!id) { showDialog('اتصال خودکار', 'ابتدا یک Inbound انتخاب کنید.', true); return false; }
    var $b = $(this).prop('disabled', true);
    post('simplevpbot_inbound_autolink', { inbound_id: id, panel_id: svpInboundPanelId() }, function(data){
      $b.prop('disabled', false);
      var rows = [];
      rows.push('Linked: ' + (data.linked || 0));
      rows.push('Skipped: ' + (data.skipped || 0));
      rows.push('Ambiguous: ' + (data.ambiguous || 0));
      rows.push('Errors: ' + (data.errors || 0));
      if (data.details && data.details.length) {
        rows.push('');
        rows.push(JSON.stringify(data.details.slice(0, 20), null, 2));
      }
      showDialog('اتصال خودکار انجام شد', rows.join('\n'), false);
      $('#svp-inb-clients').trigger('click');
    }, function(msg, extra){
      $b.prop('disabled', false);
      showDialog('اتصال خودکار: خطا', (msg || 'خطا') + (extra ? '\n' + (typeof extra === 'string' ? extra : JSON.stringify(extra, null, 2)) : ''), true);
    });
    return false;
  });
  $(document).on('click', '.svp-inb-link-row', function(){
    var $tr = $(this).closest('tr');
    var uid = parseInt($tr.find('.svp-inb-uid').val(), 10);
    var d = $(this).data();
    if (!d || !d.inbound_id || !d.email) { return; }
    if (!uid) { showDialog('شناسه کاربر', 'عدد svp_users.id را وارد کنید', true); return; }
    var $b = $(this).prop('disabled', true);
    post('simplevpbot_inbound_link', { inbound_id: d.inbound_id, email: d.email, user_id: uid, panel_id: svpInboundPanelId() }, function(res){
      $b.prop('disabled', false);
      showDialog('انجام شد', 'سرویس #' + (res.service_id || ''), false);
    }, function(msg){
      $b.prop('disabled', false);
      showDialog('خطا', msg, true);
    });
  });
  $(document).on('click', '.svp-l2tp-test', function(){
    var $b = $(this).prop('disabled', true);
    var id = parseInt($b.data('id'), 10) || 0;
    var $out = $b.siblings('.svp-l2tp-test-result');
    $out.text('…');
    post('simplevpbot_l2tp_test', { id: id }, function(data){
      $b.prop('disabled', false);
      $out.css('color', 'green').text('✓ ' + (data.message || 'OK') + ' [' + (data.driver || '') + ']');
    }, function(msg){
      $b.prop('disabled', false);
      $out.css('color', 'crimson').text('✗ ' + msg);
    });
  });

  function svpTogglePlanRows(root){
    var $sel = $(root).find('select.svp-plan-stype');
    if (!$sel.length) { return; }
    $sel.each(function(){
      var $s = $(this);
      var sfx = $s.data('target');
      var is_l2tp = $s.val() === 'l2tp';
      $s.closest('form, table').find('.svp-row-l2tp-' + sfx).toggle(is_l2tp);
      $s.closest('form, table').find('.svp-row-xray-' + sfx).toggle(!is_l2tp);
    });
  }
  $(function(){ svpTogglePlanRows(document); });
  $(document).on('change', 'select.svp-plan-stype', function(){ svpTogglePlanRows(document); });

  function svpParsePlanCatsByPanel(){
    var $j = $('#svp-plan-cats-by-panel');
    if (!$j.length) return {};
    try {
      var raw = ($j.text() || '').replace(/^\s+|\s+$/g, '');
      return raw ? JSON.parse(raw) : {};
    } catch (e) {
      return {};
    }
  }
  /** Sentinel when a panel has no plan categories in DB (avoid empty <select>). */
  var SVP_PLAN_CAT_PLACEHOLDER = 'z_svp_need_cat';

  function svpRefillPlanCategorySelect($sel, panelId){
    var all = svpParsePlanCatsByPanel();
    var rows = all[String(panelId)] || [];
    var cur = $sel.val();
    $sel.empty();
    rows.forEach(function(r){
      var lab = r.label + (r.active ? '' : ' (غیرفعال)');
      $sel.append($('<option/>').attr('value', r.slug).text(lab));
    });
    if (!$sel.find('option').length) {
      $sel.append(
        $('<option/>')
          .attr('value', SVP_PLAN_CAT_PLACEHOLDER)
          .text('— برای این پنل دسته‌ای در «دسته‌های خرید» تعریف نشده؛ ابتدا دسته بسازید —')
      );
      $sel.val(SVP_PLAN_CAT_PLACEHOLDER);
      return;
    }
    if ($sel.find('option[value="' + cur + '"]').length) {
      $sel.val(cur);
    } else if ($sel.find('option').length) {
      $sel.prop('selectedIndex', 0);
    }
  }
  $(document).on('change', 'select[name="plan_panel_id"]', function(){
    var pid = parseInt($(this).val(), 10) || 1;
    $(this).closest('form').find('select[name="category"]').each(function(){
      svpRefillPlanCategorySelect($(this), pid);
    });
  });

  $(document).on('click', '.svp-receipt-retry', function(){
    if (!ensureAdmin()) { return false; }
    var $b  = $(this);
    var rid = parseInt($b.data('rid'), 10) || 0;
    var $o  = $('.svp-receipt-retry-out[data-for="' + rid + '"]');
    if (!rid) { return; }
    $b.prop('disabled', true);
    $o.text('…');
    post('simplevpbot_receipt_retry_provision', { rid: rid }, function(data){
      $b.prop('disabled', false);
      $o.css('color', 'green').text('✓ ' + (data.message || 'OK') + (data.service_id ? ' #' + data.service_id : ''));
    }, function(msg){
      $b.prop('disabled', false);
      $o.css('color', 'crimson').text('✗ ' + (msg || 'err'));
    });
  });

  $(document).on('click', '#svp-stx-run', function(){
    if (!ensureAdmin()) { return false; }
    var sid = parseInt($('#svp-stx-sid').val(), 10) || 0;
    var tgt = ($('#svp-stx-target').val() || '').trim();
    if (!sid || !tgt) { $('#svp-stx-out').css('color','crimson').text('فیلدها کامل نیست'); return; }
    var $b = $(this).prop('disabled', true);
    $('#svp-stx-out').text('…').css('color', '');
    post('simplevpbot_service_transfer', { service_id: sid, target: tgt }, function(data){
      $b.prop('disabled', false);
      $('#svp-stx-out').css('color', 'green').text('✓ منتقل شد به کاربر #' + (data.target_id || ''));
    }, function(msg){
      $b.prop('disabled', false);
      $('#svp-stx-out').css('color', 'crimson').text('✗ ' + (msg || 'err'));
    });
  });

  function svpMergeIds() {
    var keep = parseInt($('#svp-mrg-keep').val(), 10) || 0;
    var drop = parseInt($('#svp-mrg-drop').val(), 10) || 0;
    return { keep: keep, drop: drop };
  }

  $(document).on('click', '#svp-mrg-preview', function(){
    if (!ensureAdmin()) { return false; }
    var ids = svpMergeIds();
    if (!ids.keep || !ids.drop) { showDialog('ادغام', 'keep_id و drop_id لازم است', true); return; }
    var $b = $(this).prop('disabled', true);
    post('simplevpbot_user_merge_preview', { keep_id: ids.keep, drop_id: ids.drop }, function(data){
      $b.prop('disabled', false);
      $('#svp-mrg-out').show().text(JSON.stringify(data, null, 2));
    }, function(msg){
      $b.prop('disabled', false);
      $('#svp-mrg-out').show().text('خطا: ' + (msg || ''));
    });
  });

  $(document).on('click', '#svp-mrg-run', function(){
    if (!ensureAdmin()) { return false; }
    var ids = svpMergeIds();
    if (!ids.keep || !ids.drop) { showDialog('ادغام', 'keep_id و drop_id لازم است', true); return; }
    if (!window.confirm('ردیف drop حذف و اطلاعاتش به keep منتقل می‌شود. ادامه؟')) { return; }
    var $b = $(this).prop('disabled', true);
    post('simplevpbot_user_merge', { keep_id: ids.keep, drop_id: ids.drop, confirm: 1 }, function(data){
      $b.prop('disabled', false);
      $('#svp-mrg-out').show().text(JSON.stringify(data, null, 2));
    }, function(msg){
      $b.prop('disabled', false);
      $('#svp-mrg-out').show().text('خطا: ' + (msg || ''));
    });
  });

  $(document).on('click', '#svp-inb-link', function(){
    var iid = parseInt($('#svp-inb-id').val(), 10);
    var em = ($('#svp-inb-email').val() || '').trim();
    var uid = parseInt($('#svp-inb-uid').val(), 10);
    if (!iid || !em || !uid) { showDialog('فیلدها', 'Inbound ID، ایمیل و شناسه کاربر لازم است', true); return; }
    var $b = $(this).prop('disabled', true);
    post('simplevpbot_inbound_link', { inbound_id: iid, email: em, user_id: uid, panel_id: svpInboundPanelId() }, function(data){
      $b.prop('disabled', false);
      $('#svp-inb-msg').text('OK #' + (data.service_id || ''));
    }, function(msg){
      $b.prop('disabled', false);
      $('#svp-inb-msg').text(msg);
    });
  });
})(jQuery);
