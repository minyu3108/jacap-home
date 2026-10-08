/* 관리자 페이지 스크립트: 본문 편집기 / 파일 미리보기 / 로그인 유지 */
(function () {
  'use strict';
  var A = window.JHA || {};
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---- 메뉴 열기 버튼 / 삭제 확인 (보안 설정 때문에 HTML 안의 onclick 대신 여기서 처리) ---- */
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('.ad-burger')) document.body.classList.toggle('nav-open');
  });
  document.addEventListener('submit', function (e) {
    var f = e.target.closest && e.target.closest('form[data-confirm]');
    if (f && !window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
  }, true);

  /* ---- 본문 편집기 ---- */
  function initEditor(box) {
    var ta = box.querySelector('.ed-src');
    var area = box.querySelector('.ed-area');
    var bar = box.querySelector('.ed-bar');
    var fileIn = box.querySelector('.ed-file');
    var colorIn = box.querySelector('.ed-color');
    var sizeSel = box.querySelector('.ed-size');
    var fontSel = box.querySelector('.ed-font');
    var bgIn = box.querySelector('.ed-bg');
    var form = box.closest('form');
    var source = false;

    area.innerHTML = ta.value;
    ta.hidden = true;
    try { document.execCommand('styleWithCSS', false, true); } catch (e) { /* 무시 */ }

    function cmd(c, v) {
      area.focus();
      document.execCommand(c, false, v === undefined ? null : v);
    }
    function setSource(on) {
      if (on === source) return;
      if (on) { ta.value = area.innerHTML; area.hidden = true; ta.hidden = false; }
      else { area.innerHTML = ta.value; ta.hidden = true; area.hidden = false; }
      source = on;
      box.classList.toggle('is-source', on);
    }

    bar.addEventListener('mousedown', function (e) {
      if (e.target.closest('button')) e.preventDefault(); // 선택 영역 유지
    });
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('[data-cmd]');
      if (!b) return;
      e.preventDefault();
      var c = b.getAttribute('data-cmd');
      if (c === 'source') { setSource(!source); return; }
      if (c === 'imgs') { toggleImgs(); return; }
      if (source) return;
      if (c === 'link') {
        var u = window.prompt('연결할 주소를 입력하세요 (https://...)');
        if (u) cmd('createLink', u);
      } else if (c === 'image') { fileIn.click(); }
      else if (c === 'clean') { cmd('removeFormat'); }
      else { cmd(c); }
    });
    colorIn.addEventListener('input', function () { if (!source) cmd('foreColor', colorIn.value); });
    sizeSel.addEventListener('change', function () {
      if (!source && sizeSel.value) cmd('fontSize', sizeSel.value);
      sizeSel.value = '';
    });

    fontSel.addEventListener('change', function () {
      if (!source && fontSel.value) cmd('fontName', fontSel.value);
      fontSel.value = '';
    });
    bgIn.addEventListener('input', function () {
      if (source) return;
      area.focus();
      if (!document.execCommand('hiliteColor', false, bgIn.value)) document.execCommand('backColor', false, bgIn.value);
    });

    /* ---- 이미지 관리: 본문 속 이미지 목록, 깨진 이미지 교체 ---- */
    var panel = document.createElement('div');
    panel.className = 'ed-imgs';
    panel.hidden = true;
    box.appendChild(panel);
    function workRoot() {
      if (!source) return area;
      var d = document.createElement('div');
      d.innerHTML = ta.value;
      return d;
    }
    function commit(root) { if (source) ta.value = root.innerHTML; }
    function toggleImgs() {
      panel.hidden = !panel.hidden;
      if (!panel.hidden) buildPanel();
    }
    function buildPanel() {
      var root = workRoot();
      var srcs = [];
      Array.prototype.forEach.call(root.querySelectorAll('img'), function (im) {
        var s = im.getAttribute('src') || '';
        if (s && srcs.indexOf(s) < 0) srcs.push(s);
      });
      panel.innerHTML = '';
      var head = document.createElement('p');
      head.className = 'help';
      head.textContent = srcs.length
        ? '본문에 쓰인 이미지예요 (같은 이미지는 한 번에 모두 바뀌어요). 깨진 이미지는 파일을 올리거나 새 주소를 넣어 교체하세요.'
        : '본문에 이미지가 없어요.';
      panel.appendChild(head);
      srcs.forEach(function (src) {
        var row = document.createElement('div');
        row.className = 'ed-imgrow';
        var th = document.createElement('img');
        th.src = src;
        th.alt = '';
        var badge = document.createElement('span');
        badge.className = 'ed-badge';
        th.onerror = function () { badge.textContent = '깨짐'; badge.classList.add('bad'); };
        th.onload = function () { badge.textContent = '정상'; };
        var lab = document.createElement('code');
        lab.textContent = src.length > 60 ? src.slice(0, 57) + '…' : src;
        var file = document.createElement('input');
        file.type = 'file'; file.accept = 'image/*';
        var url = document.createElement('input');
        url.type = 'text'; url.placeholder = '또는 새 이미지 주소(URL)';
        var btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'btn sm'; btn.textContent = '교체';
        btn.addEventListener('click', function () {
          function apply(newSrc) {
            var r = workRoot();
            Array.prototype.forEach.call(r.querySelectorAll('img'), function (im) {
              if (im.getAttribute('src') === src) im.setAttribute('src', newSrc);
            });
            commit(r);
            buildPanel();
          }
          if (file.files[0]) {
            var fd = new FormData();
            fd.append('file', file.files[0]);
            fd.append('csrf', A.csrf);
            btn.disabled = true;
            fetch(A.base + 'admin/upload.php', { method: 'POST', body: fd, credentials: 'same-origin' })
              .then(function (r) { return r.json(); })
              .then(function (d) { btn.disabled = false; if (d.ok) apply(d.url); else window.alert(d.msg || '올리지 못했어요.'); })
              .catch(function () { btn.disabled = false; window.alert('업로드 중 오류가 났어요.'); });
          } else if (url.value.trim()) {
            apply(url.value.trim());
          } else {
            window.alert('파일을 고르거나 새 주소를 입력해 주세요.');
          }
        });
        var top = document.createElement('div');
        top.className = 'ed-imgtop';
        top.appendChild(th); top.appendChild(badge); top.appendChild(lab);
        row.appendChild(top);
        var ctl = document.createElement('div');
        ctl.className = 'ed-imgctl';
        ctl.appendChild(file); ctl.appendChild(url); ctl.appendChild(btn);
        row.appendChild(ctl);
        panel.appendChild(row);
      });
    }

    fileIn.addEventListener('change', function () {
      var f = fileIn.files[0];
      if (!f) return;
      var fd = new FormData();
      fd.append('file', f);
      fd.append('csrf', A.csrf);
      fetch(A.base + 'admin/upload.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.ok) { cmd('insertImage', d.url); } else { window.alert(d.msg || '이미지를 올리지 못했어요.'); }
        })
        .catch(function () { window.alert('이미지를 올리는 중 오류가 났어요.'); });
      fileIn.value = '';
    });

    // 붙여넣기: 스크립트 같은 위험한 요소는 서버에서 저장 시 제거되므로 그대로 허용
    if (form) {
      form.addEventListener('submit', function () {
        if (!source) ta.value = area.innerHTML;
      });
    }
  }
  $$('.htmled').forEach(initEditor);


  /* ---- 롤20 채팅 로그 붙여넣기 칸: 이미지 관리 (깨진 이미지 교체) ---- */
  $$('.rawbox').forEach(function (ta) {
    var wrap = document.createElement('div');
    wrap.className = 'raw-tools';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn sm ghost';
    btn.textContent = '이미지 관리 (깨진 이미지 교체)';
    var panel = document.createElement('div');
    panel.className = 'ed-imgs';
    panel.hidden = true;
    wrap.appendChild(btn);
    ta.parentNode.appendChild(wrap);
    ta.parentNode.appendChild(panel);

    function parse() { return new DOMParser().parseFromString(ta.value, 'text/html'); }
    function build() {
      var doc = parse();
      var srcs = [];
      Array.prototype.forEach.call(doc.querySelectorAll('img'), function (im) {
        var sv = im.getAttribute('src') || '';
        if (sv && srcs.indexOf(sv) < 0) srcs.push(sv);
      });
      panel.innerHTML = '';
      var head = document.createElement('p');
      head.className = 'help';
      head.textContent = srcs.length ? '로그에 쓰인 이미지예요. 깨진 이미지는 파일을 올리거나 새 주소를 넣어 교체하세요. (같은 이미지는 한 번에 모두 바뀌어요)' : '로그에 이미지가 없어요.';
      panel.appendChild(head);
      srcs.forEach(function (src) {
        var row = document.createElement('div');
        row.className = 'ed-imgrow';
        var top = document.createElement('div');
        top.className = 'ed-imgtop';
        var th = document.createElement('img');
        th.src = src; th.alt = '';
        var badge = document.createElement('span');
        badge.className = 'ed-badge';
        th.onerror = function () { badge.textContent = '깨짐'; badge.classList.add('bad'); };
        th.onload = function () { badge.textContent = '정상'; };
        var lab = document.createElement('code');
        lab.textContent = src.length > 60 ? src.slice(0, 57) + '…' : src;
        top.appendChild(th); top.appendChild(badge); top.appendChild(lab);
        var ctl = document.createElement('div');
        ctl.className = 'ed-imgctl';
        var file = document.createElement('input'); file.type = 'file'; file.accept = 'image/*';
        var url = document.createElement('input'); url.type = 'text'; url.placeholder = '또는 새 이미지 주소(URL)';
        var go = document.createElement('button'); go.type = 'button'; go.className = 'btn sm'; go.textContent = '교체';
        function apply(newSrc) {
          var d = parse();
          Array.prototype.forEach.call(d.querySelectorAll('img'), function (im) {
            if (im.getAttribute('src') === src) im.setAttribute('src', newSrc);
          });
          ta.value = '<!doctype html>\n' + d.documentElement.outerHTML;
          build();
        }
        go.addEventListener('click', function () {
          if (file.files[0]) {
            var fd = new FormData();
            fd.append('file', file.files[0]);
            fd.append('csrf', A.csrf);
            go.disabled = true;
            fetch(A.base + 'admin/upload.php', { method: 'POST', body: fd, credentials: 'same-origin' })
              .then(function (r) { return r.json(); })
              .then(function (d) { go.disabled = false; if (d.ok) apply(d.url); else window.alert(d.msg || '올리지 못했어요.'); })
              .catch(function () { go.disabled = false; window.alert('업로드 중 오류가 났어요.'); });
          } else if (url.value.trim()) { apply(url.value.trim()); }
          else { window.alert('파일을 고르거나 새 주소를 입력해 주세요.'); }
        });
        ctl.appendChild(file); ctl.appendChild(url); ctl.appendChild(go);
        row.appendChild(top); row.appendChild(ctl);
        panel.appendChild(row);
      });
    }
    btn.addEventListener('click', function () { panel.hidden = !panel.hidden; if (!panel.hidden) build(); });
  });


  /* ---- 색상 선택기: 색을 고르면 "이 색 사용" 체크박스를 자동으로 켬 ---- */
  $$('.fld-color').forEach(function (fld) {
    var color = fld.querySelector('input[type=color]');
    var chk = fld.querySelector('input[type=checkbox]');
    if (color && chk) {
      color.addEventListener('input', function () { chk.checked = true; });
    }
  });

  /* ---- 이미지 경로를 실제 볼 수 있는 주소로: 이미 절대경로/외부주소면 그대로, 아니면 base를 붙임 ---- */
  function imgDisplayUrl(src) {
    src = String(src || '');
    if (/^(https?:)?\/\//i.test(src)) return src; // http(s):// 또는 //로 시작 (외부 주소)
    if (src.charAt(0) === '/') return src; // 이미 사이트 루트 기준 절대경로 (업로드 결과가 보통 이 형태)
    return A.base + src; // uploads/xxx.jpg 같은 상대경로일 때만 base를 붙임
  }

  /* ---- 여러 장 이미지 필드: 업로드/URL 추가/순서 변경/삭제 ---- */
  $$('.imgs-field').forEach(function (field) {
    var hidden = field.querySelector('.imgs-json');
    var list = field.querySelector('.imgs-list');
    var fileIn = field.querySelector('.imgs-file');
    var urlIn = field.querySelector('.imgs-url');
    var addBtn = field.querySelector('.imgs-addurl');
    var arr = [];
    try { arr = JSON.parse(hidden.value || '[]'); } catch (e) { arr = []; }
    if (!Array.isArray(arr)) arr = [];

    var isMedia = field.getAttribute('data-media') === '1';
    function isVid(u) { return /\.(mp4|webm|m4v)(\?|#|$)/i.test(String(u)); }
    function save() { hidden.value = JSON.stringify(arr); }
    function render() {
      list.innerHTML = '';
      if (!arr.length) { var e = document.createElement('p'); e.className = 'imgs-empty'; e.textContent = isMedia ? '아직 추가된 이미지·영상이 없어요.' : '아직 추가된 이미지가 없어요.'; list.appendChild(e); return; }
      arr.forEach(function (src, i) {
        var row = document.createElement('div');
        row.className = 'imgs-row';
        var order = document.createElement('span');
        order.className = 'imgs-order';
        order.textContent = String(i + 1);
        var vid = isVid(src);
        var img;
        if (vid) { img = document.createElement('video'); img.muted = true; img.preload = 'metadata'; img.src = imgDisplayUrl(src) + '#t=0.1'; }
        else { img = document.createElement('img'); img.src = imgDisplayUrl(src); img.alt = ''; }
        var name = document.createElement('span');
        name.className = 'imgs-name';
        name.textContent = (i + 1) + (vid ? '번째 영상' : '번째 이미지');
        var ctl = document.createElement('div');
        ctl.className = 'imgs-ctl';
        var up = document.createElement('button'); up.type = 'button'; up.className = 'btn sm ghost'; up.textContent = '↑'; up.disabled = i === 0;
        var down = document.createElement('button'); down.type = 'button'; down.className = 'btn sm ghost'; down.textContent = '↓'; down.disabled = i === arr.length - 1;
        var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'btn sm danger'; rm.textContent = '×';
        up.addEventListener('click', function () { if (i > 0) { var t = arr[i - 1]; arr[i - 1] = arr[i]; arr[i] = t; save(); render(); } });
        down.addEventListener('click', function () { if (i < arr.length - 1) { var t = arr[i + 1]; arr[i + 1] = arr[i]; arr[i] = t; save(); render(); } });
        rm.addEventListener('click', function () { arr.splice(i, 1); save(); render(); });
        ctl.appendChild(up); ctl.appendChild(down); ctl.appendChild(rm);
        row.appendChild(order); row.appendChild(img); row.appendChild(name); row.appendChild(ctl);
        list.appendChild(row);
      });
    }
    render();

    fileIn.addEventListener('change', function () {
      var files = Array.prototype.slice.call(fileIn.files);
      if (!files.length) return;
      fileIn.disabled = true;
      var chain = Promise.resolve();
      files.forEach(function (f) {
        chain = chain.then(function () {
          var fd = new FormData();
          fd.append('file', f);
          fd.append('csrf', A.csrf);
          if (isMedia) fd.append('kind', 'media');
          return fetch(A.base + 'admin/upload.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.ok) { arr.push(d.url); save(); render(); } else { window.alert((f.name || '') + ': ' + (d.msg || '올리지 못했어요.')); } })
            .catch(function () { window.alert((f.name || '') + ': 업로드 중 오류가 났어요.'); });
        });
      });
      chain.then(function () { fileIn.disabled = false; fileIn.value = ''; });
    });

    addBtn.addEventListener('click', function () {
      var u = urlIn.value.trim();
      if (!u) return;
      arr.push(u);
      save(); render();
      urlIn.value = '';
    });
    urlIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addBtn.click(); } });
  });

  /* ---- 파일 선택 시 미리보기 ---- */
  $$('.fileF .filein').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var f = inp.files[0];
      var wrap = inp.closest('.fileF');
      var prev = wrap.querySelector('.prev');
      if (!f || f.type.indexOf('image/') !== 0) return;
      if (!prev) { prev = document.createElement('div'); prev.className = 'prev'; wrap.insertBefore(prev, wrap.firstChild); }
      prev.innerHTML = '';
      var im = document.createElement('img');
      im.src = URL.createObjectURL(f);
      prev.appendChild(im);
    });
  });

  /* ---- 관리자 왼쪽 메뉴: 대분류(레일) / 소분류 접기·펼치기 / 찾기 ---- */
  (function () {
    var side = document.getElementById('adSide');
    if (!side) return;
    var KEY = 'jha_nav', st = {};
    try { st = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { st = {}; }
    function save() { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) { /* 무시 */ } }
    var panels = $$('.ad-panel', side), rbtns = $$('.ad-r[data-cat]', side);
    function showCat(c) {
      var any = false;
      panels.forEach(function (p) { var on = p.getAttribute('data-panel') === c; p.hidden = !on; if (on) any = true; });
      rbtns.forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-cat') === c); });
      side.classList.toggle('has-panel', any);
    }
    rbtns.forEach(function (b) { b.addEventListener('click', function () { showCat(b.getAttribute('data-cat')); }); });

    function shouldOpen(g) {
      if (g.querySelector('.lnk.on')) return true;
      var k = g.getAttribute('data-g');
      if (st[k] === 1) return true;
      return false;
    }
    function setOpen(g, on) { g.classList.toggle('open', on); var h = g.querySelector('.ad-gh'); if (h) h.setAttribute('aria-expanded', on ? 'true' : 'false'); }
    var groups = $$('.ad-grp', side);
    groups.forEach(function (g) {
      setOpen(g, shouldOpen(g));
      g.querySelector('.ad-gh').addEventListener('click', function () {
        var on = !g.classList.contains('open');
        setOpen(g, on); st[g.getAttribute('data-g')] = on ? 1 : 0; save();
      });
    });

    $$('[data-navfind]', side).forEach(function (inp) {
      var panel = inp.closest('.ad-panel');
      inp.addEventListener('input', function () {
        var q = inp.value.trim().toLowerCase();
        $$('.lnk', panel).forEach(function (a) { a.hidden = !!q && a.textContent.toLowerCase().indexOf(q) === -1; });
        $$('.ad-grp', panel).forEach(function (g) {
          var vis = $$('.lnk', g).filter(function (a) { return !a.hidden; }).length;
          g.hidden = !!q && vis === 0;
          setOpen(g, q ? true : shouldOpen(g));
        });
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
      var t = e.target, tag = t && t.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (t && t.isContentEditable)) return;
      var vis = panels.filter(function (p) { return !p.hidden; })[0];
      if (!vis) { showCat('content'); vis = panels.filter(function (p) { return !p.hidden; })[0]; }
      var f = vis && vis.querySelector('[data-navfind]');
      if (f) { e.preventDefault(); document.body.classList.add('nav-open'); f.focus(); }
    });
    document.addEventListener('click', function (e) {
      if (!document.body.classList.contains('nav-open')) return;
      if (e.target.closest('#adSide') || e.target.closest('.ad-burger')) return;
      document.body.classList.remove('nav-open');
    });
  })();

  /* ---- 설정 화면: 바뀐 내용 표시 / 되돌리기 / 나갈 때 경고 ---- */
  $$('form.set-form').forEach(function (form) {
    var bar = form.querySelector('.savebar');
    if (!bar) return;
    var msg = bar.querySelector('.sb-msg'), reset = bar.querySelector('[data-sb-reset]');
    var els = Array.prototype.filter.call(form.elements, function (el) { return el.name && el.type !== 'file' && el.type !== 'submit' && el.type !== 'button'; });
    function val(el) { return (el.type === 'checkbox' || el.type === 'radio') ? (el.checked ? '1' : '0') : el.value; }
    var init = els.map(val), files = 0;
    function recalc() {
      var seen = {}, n = 0;
      els.forEach(function (el, i) {
        if (val(el) !== init[i]) {
          var b = el.name.replace(/^(v|clr|url|del|file)_/, '');
          if (!seen[b]) { seen[b] = 1; n++; }
        }
      });
      n += files;
      bar.classList.toggle('dirty', n > 0);
      msg.textContent = n > 0 ? '저장하지 않은 변경이 ' + n + '개 있어요' : '바뀐 내용이 없어요';
      if (reset) reset.disabled = n === 0;
      form.__dirty = n > 0;
    }
    form.addEventListener('input', recalc);
    form.addEventListener('change', function (e) {
      if (e.target && e.target.type === 'file') {
        files = $$('input[type=file]', form).filter(function (f) { return f.files && f.files.length; }).length;
      }
      recalc();
    });
    if (reset) reset.addEventListener('click', function () { form.reset(); files = 0; recalc(); });
    form.addEventListener('submit', function () { form.__saving = true; });
    window.addEventListener('beforeunload', function (e) {
      if (form.__dirty && !form.__saving) { e.preventDefault(); e.returnValue = ''; }
    });
    recalc();
  });

  /* ---- 콘텐츠 목록: 검색 / 공개 상태 필터 ---- */
  $$('[data-listtools]').forEach(function (box) {
    var rows = $$('tbody tr[data-t]'), q = box.querySelector('input[type=search]');
    var chips = $$('.chip[data-vis]', box), vis = 'all', empty = document.querySelector('[data-list-empty]');
    function apply() {
      var s = q ? q.value.trim().toLowerCase() : '', shown = 0;
      rows.forEach(function (r) {
        var ok = (vis === 'all' || r.getAttribute('data-v') === vis) && (!s || r.getAttribute('data-t').indexOf(s) !== -1);
        r.hidden = !ok; if (ok) shown++;
      });
      if (empty) empty.hidden = shown !== 0;
    }
    if (q) q.addEventListener('input', apply);
    chips.forEach(function (c) {
      c.addEventListener('click', function () {
        vis = c.getAttribute('data-vis');
        chips.forEach(function (x) { x.classList.toggle('on', x === c); });
        apply();
      });
    });
  });

  /* ---- 오래 작성해도 로그인이 풀리지 않게 ---- */
  setInterval(function () {
    fetch(A.base + 'admin/ping.php', { credentials: 'same-origin' }).catch(function () {});
  }, 5 * 60 * 1000);
})();
