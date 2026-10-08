/* 자캐 커플 홈 - 프론트 스크립트
 * 곡선 메뉴 / 페이지 전환(PJAX) / 뮤직 플레이어 / 드래그 위젯 / 라이트박스 / 폼 처리
 */
(function () {
  'use strict';

  var JH = window.JH || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var view = $('#view');
  if (JH.admin) document.documentElement.classList.add('is-admin');
  var bgEl = $('#bg');

  /** 페이지 전환(PJAX) 시 배경 뒤 흐린 이미지(#pageGhost)를 새 페이지 설정에 맞춰 생성/갱신/제거 */
  function syncGhost(ghost) {
    var el = document.getElementById('pageGhost');
    if (!ghost || !ghost.img) {
      if (el) el.remove();
      return;
    }
    if (!el) {
      el = document.createElement('img');
      el.id = 'pageGhost';
      el.alt = '';
      el.setAttribute('aria-hidden', 'true');
      bgEl.insertAdjacentElement('afterend', el);
    }
    el.src = ghost.img;
    el.style.opacity = ghost.opacity;
    el.classList.toggle('flip', !!ghost.flip);
  }
  var LOCK_SVG = '<svg class="lock-ico" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function api(action, data) {
    return fetch(JH.base + 'api.php?a=' + action, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-CSRF': JH.csrf },
      body: new URLSearchParams(data || {})
    }).then(function (r) { return r.json(); });
  }
  function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* 무시 */ } }

  /* ============ 곡선 메뉴 ============ */
  var Rail = (function () {
    var rail = $('#rail'), svg = $('#rail-svg'), path = $('#rail-path'), dotsG = $('#rail-dots'), grad = $('#rg');
    var items = $$('.mi', rail);
    var logo = $('#rail-logo', rail);
    var n = items.length;
    var flat = !!(rail && rail.classList.contains('rs-icon'));   // 선 없는 아이콘 메뉴: 크기 변화 없이 가운데 정렬된 목록
    var cur = (n - 1) / 2, target = cur, active = -1, raf = 0, H = 0, W = 0;
    var NS = 'http://www.w3.org/2000/svg';
    var dots = items.map(function () {
      var c = document.createElementNS(NS, 'circle');
      c.setAttribute('class', 'rdot');
      dotsG.appendChild(c);
      return c;
    });

    function metrics() {
      H = rail.clientHeight; W = rail.clientWidth;
      grad.setAttribute('y2', H);
      svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    }
    function small() { return W < 160; }
    function xAt(y) {
      return small() ? 18 : 34;
    }
    function gap() { return Math.max(30, Math.min(58, H * 0.072)); }

    function draw() {
      var d = '', y, g = gap();
      for (y = -10; y <= H + 10; y += 10) { d += (d ? 'L' : 'M') + xAt(y).toFixed(1) + ' ' + y + ' '; }
      path.setAttribute('d', d);
      drawLogo(g);
      items.forEach(function (el, i) {
        var dd = i - cur, a = Math.abs(dd);
        var yy = H / 2 + dd * g, xx = xAt(yy);
        var big = small() ? 14 : 19, step = small() ? 1.6 : 2.4;
        if (flat) {
          el.style.fontSize = (small() ? 14 : 16) + 'px';
          el.style.opacity = i === active ? '1' : '0.72';
          el.style.transform = 'translate(' + (small() ? 12 : 24) + 'px,' + yy.toFixed(1) + 'px) translateY(-50%)';
        } else {
          el.style.fontSize = Math.max(11, big - a * step).toFixed(1) + 'px';
          el.style.opacity = Math.max(0.42, 1 - a * 0.16).toFixed(2);
          el.style.transform = 'translate(' + (xx + 16).toFixed(1) + 'px,' + yy.toFixed(1) + 'px) translateY(-50%)';
        }
        var c = dots[i];
        c.setAttribute('cx', xx.toFixed(1));
        c.setAttribute('cy', yy.toFixed(1));
        c.setAttribute('r', i === active ? 6 : 3.5);
        c.classList.toggle('on', i === active);
      });
    }
    function drawLogo(g) {
      if (!logo) return;
      var h = logo.offsetHeight || 0;
      var y0 = H / 2 + (0 - cur) * g;            // 첫 번째 메뉴의 세로 위치
      var top = y0 - 46 - h;                      // 메뉴 목록 바로 위
      var op = h ? Math.max(0, Math.min(1, (top + h * 0.6) / (h * 0.6))) : 1;
      logo.style.top = top.toFixed(1) + 'px';
      logo.style.opacity = op.toFixed(2);
      logo.style.pointerEvents = op < 0.3 ? 'none' : 'auto';
    }
    function tick() {
      raf = 0;
      var diff = target - cur;
      if (Math.abs(diff) < 0.003) { cur = target; draw(); return; }
      cur += diff * 0.16;
      draw();
      raf = requestAnimationFrame(tick);
    }
    function set(idx) {
      active = idx;
      target = (!flat && idx >= 0) ? idx : (n - 1) / 2;
      lsSetSession('jh_rail', String(target));
      items.forEach(function (el, i) { el.classList.toggle('on', i === idx); });
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { cur = target; draw(); return; }
      if (!raf) raf = requestAnimationFrame(tick);
    }
    function lsSetSession(k, v) { try { sessionStorage.setItem(k, v); } catch (e) { /* 무시 */ } }
    function init(idx) {
      metrics();
      var prev = NaN;
      try { prev = parseFloat(sessionStorage.getItem('jh_rail')); } catch (e) { /* 무시 */ }
      if (isFinite(prev) && !flat) cur = prev;
      set(idx);
    }
    window.addEventListener('resize', function () { metrics(); draw(); });
    if (logo) { var li = $('img', logo); if (li) li.addEventListener('load', function () { draw(); }); }
    return { init: init, set: set };
  })();

  /* ============ 드래그 위젯 ============ */
  function boxOf(el, fixed) {
    if (fixed) return { l: 0, t: 0, w: window.innerWidth, h: window.innerHeight };
    var p = el.offsetParent || document.body;
    var r = p.getBoundingClientRect();
    return { l: r.left, t: r.top, w: r.width, h: r.height };
  }
  function place(el, fixed, px, py) {
    var b = boxOf(el, fixed), w = el.offsetWidth, h = el.offsetHeight;
    var x = Math.max(0, Math.min(b.w - w, px - b.l));
    var y = Math.max(0, Math.min(b.h - h, py - b.t));
    el.style.left = (x / b.w * 100).toFixed(2) + '%';
    el.style.top = (y / b.h * 100).toFixed(2) + '%';
    el.style.right = 'auto';
    el.style.bottom = 'auto';
  }
  function clamp(el, fixed) { var r = el.getBoundingClientRect(); place(el, fixed, r.left, r.top); }
  function posOf(el, fixed) {
    var r = el.getBoundingClientRect(), b = boxOf(el, fixed);
    return { x: +((r.left - b.l) / b.w * 100).toFixed(2), y: +((r.top - b.t) / b.h * 100).toFixed(2) };
  }
  // 방문자가 옮긴 위치는 이 방문(메모리)에서만 유지되고, 다시 접속하면 관리자가 정한 위치로 돌아가요.
  var MEM = {};
  try { localStorage.removeItem('jh_pos'); } catch (e) { /* 무시 */ }
  function readStore() { return MEM; }
  function applyPos(el, key, fixed) {
    var p = readStore()[key] || (JH.widgetPos && JH.widgetPos[key]);
    if (!p) return;
    el.style.left = p.x + '%';
    el.style.top = p.y + '%';
    el.style.right = 'auto';
    el.style.bottom = 'auto';
    clamp(el, fixed);
  }
  function makeDraggable(el, key, fixed) {
    if (el.__drag) return;
    el.__drag = true;
    applyPos(el, key, fixed);
    var sx = 0, sy = 0, ox = 0, oy = 0, drag = false, moved = false;
    el.addEventListener('pointerdown', function (e) {
      if (e.button > 0) return;
      if (e.target.closest('button, input, a, select, textarea, .mw-bar')) return;
      var r = el.getBoundingClientRect();
      drag = true; moved = false;
      sx = e.clientX; sy = e.clientY; ox = r.left; oy = r.top;
      try { el.setPointerCapture(e.pointerId); } catch (err) { /* 무시 */ }
      el.classList.add('dragging');
    });
    el.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var dx = e.clientX - sx, dy = e.clientY - sy;
      if (Math.abs(dx) + Math.abs(dy) > 3) moved = true;
      if (moved) place(el, fixed, ox + dx, oy + dy);
    });
    function end(e) {
      if (!drag) return;
      drag = false;
      el.classList.remove('dragging');
      try { el.releasePointerCapture(e.pointerId); } catch (err) { /* 무시 */ }
      if (moved) {
        MEM[key] = posOf(el, fixed);
      }
    }
    el.addEventListener('pointerup', end);
    el.addEventListener('pointercancel', end);
  }
  // 스티커: 누구나 끌어서 옮길 수 있음 (방문자는 이번 방문 동안만, 관리자는 "위젯·스티커 위치 저장"으로 기본 위치 저장)
  var stickerSelected = null;
  function selectSticker(el) {
    if (stickerSelected === el) return;
    if (stickerSelected && stickerSelected.__hideHandles) stickerSelected.__hideHandles();
    stickerSelected = el;
    if (el && el.__showHandles) el.__showHandles();
  }
  document.addEventListener('pointerdown', function (e) {
    if (!e.target.closest('.sticker, .sk-handle')) selectSticker(null);
  });

  function makeStickerDrag(el) {
    if (el.__drag) return;
    el.__drag = true;
    var skey = 's' + el.getAttribute('data-sid');
    if (MEM[skey]) { el.style.left = MEM[skey].x + '%'; el.style.top = MEM[skey].y + '%'; }
    var sx = 0, sy = 0, ol = 0, ot = 0, on = false;
    el.addEventListener('pointerdown', function (e) {
      if (e.button > 0) return;
      on = true; sx = e.clientX; sy = e.clientY; ol = el.offsetLeft; ot = el.offsetTop;
      try { el.setPointerCapture(e.pointerId); } catch (err) { /* 무시 */ }
      el.classList.add('dragging');
      selectSticker(el);
      e.preventDefault();
    });
    el.addEventListener('pointermove', function (e) {
      if (!on) return;
      var p = el.offsetParent; if (!p) return;
      var l = Math.max(0, Math.min(p.clientWidth - el.offsetWidth, ol + e.clientX - sx));
      var t = Math.max(0, Math.min(p.clientHeight - el.offsetHeight, ot + e.clientY - sy));
      el.style.left = (l / p.clientWidth * 100).toFixed(2) + '%';
      el.style.top = (t / p.clientHeight * 100).toFixed(2) + '%';
      if (el.__updateHandles) el.__updateHandles();
    });
    function end(e) {
      if (!on) return;
      on = false; el.classList.remove('dragging');
      try { el.releasePointerCapture(e.pointerId); } catch (err) { /* 무시 */ }
      var p = el.offsetParent;
      if (p && p.clientWidth && p.clientHeight) MEM[skey] = { x: +(el.offsetLeft / p.clientWidth * 100).toFixed(2), y: +(el.offsetTop / p.clientHeight * 100).toFixed(2) };
    }
    el.addEventListener('pointerup', end);
    el.addEventListener('pointercancel', end);

    // 관리자 전용: 화면에서 바로 회전·크기 조절하는 손잡이
    if (!document.documentElement.classList.contains('is-admin') || el.__handles) return;
    el.__handles = true;
    var rotH = document.createElement('div'); rotH.className = 'sk-handle sk-rot'; rotH.title = '드래그해서 회전';
    var szH = document.createElement('div'); szH.className = 'sk-handle sk-resize'; szH.title = '드래그해서 크기 조절';
    (el.offsetParent || el.parentNode).appendChild(rotH);
    (el.offsetParent || el.parentNode).appendChild(szH);

    function curRot() {
      var v = parseFloat(el.getAttribute('data-rot'));
      return isFinite(v) ? v : 0;
    }
    function updateHandles() {
      var p = el.offsetParent; if (!p) return;
      var l = el.offsetLeft, t = el.offsetTop, w = el.offsetWidth, h = el.offsetHeight;
      rotH.style.left = (l + w / 2) + 'px'; rotH.style.top = (t - 26) + 'px';
      szH.style.left = (l + w) + 'px'; szH.style.top = (t + h) + 'px';
    }
    el.__updateHandles = updateHandles;
    el.__showHandles = function () { el.classList.add('selected'); rotH.style.display = 'block'; szH.style.display = 'block'; updateHandles(); };
    el.__hideHandles = function () { el.classList.remove('selected'); rotH.style.display = 'none'; szH.style.display = 'none'; };
    updateHandles();

    var rotOn = false, rotStartAngle = 0, rotStartRot = 0;
    rotH.addEventListener('pointerdown', function (e) {
      rotOn = true;
      try { rotH.setPointerCapture(e.pointerId); } catch (err) { /* 무시 */ }
      var r = el.getBoundingClientRect();
      rotStartAngle = Math.atan2(e.clientY - (r.top + r.height / 2), e.clientX - (r.left + r.width / 2));
      rotStartRot = curRot();
      e.preventDefault(); e.stopPropagation();
    });
    rotH.addEventListener('pointermove', function (e) {
      if (!rotOn) return;
      var r = el.getBoundingClientRect();
      var ang = Math.atan2(e.clientY - (r.top + r.height / 2), e.clientX - (r.left + r.width / 2));
      var newRot = Math.max(-180, Math.min(180, Math.round(rotStartRot + (ang - rotStartAngle) * 180 / Math.PI)));
      el.style.transform = 'rotate(' + newRot + 'deg)';
      el.setAttribute('data-rot', newRot);
    });
    function rotEnd(e) { if (!rotOn) return; rotOn = false; try { rotH.releasePointerCapture(e.pointerId); } catch (err) { /* 무시 */ } }
    rotH.addEventListener('pointerup', rotEnd);
    rotH.addEventListener('pointercancel', rotEnd);

    var szOn = false, szStartX = 0, szStartW = 0;
    szH.addEventListener('pointerdown', function (e) {
      szOn = true;
      try { szH.setPointerCapture(e.pointerId); } catch (err) { /* 무시 */ }
      szStartX = e.clientX; szStartW = el.offsetWidth;
      e.preventDefault(); e.stopPropagation();
    });
    szH.addEventListener('pointermove', function (e) {
      if (!szOn) return;
      var newW = Math.max(30, Math.min(1200, Math.round(szStartW + (e.clientX - szStartX))));
      el.style.width = newW + 'px';
      el.setAttribute('data-w', newW);
      updateHandles();
    });
    function szEnd(e) { if (!szOn) return; szOn = false; try { szH.releasePointerCapture(e.pointerId); } catch (err) { /* 무시 */ } }
    szH.addEventListener('pointerup', szEnd);
    szH.addEventListener('pointercancel', szEnd);

    window.addEventListener('resize', updateHandles);
  }

  window.addEventListener('resize', function () {
    $$('[data-widget]').forEach(function (el) {
      if (el.style.left) clamp(el, el.getAttribute('data-widget') === 'music');
    });
  });

  /* ============ 유튜브 API 로더 (뮤직 플레이어 / 프로필 사운드 버튼이 함께 씀) ============ */
  var YT_PENDING = [];
  function ensureYT(cb) {
    if (window.YT && window.YT.Player) { cb(); return; }
    YT_PENDING.push(cb);
    if (document.getElementById('yt-api')) return;
    var s = document.createElement('script');
    s.id = 'yt-api';
    s.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(s);
    var prev = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = function () {
      if (prev) prev();
      var q = YT_PENDING; YT_PENDING = [];
      q.forEach(function (f) { f(); });
    };
  }

  /* ============ 뮤직 플레이어 (mp3 + 유튜브) ============ */
  var Music = (function () {
    var el = $('#music');
    var tracks = JH.tracks || [];
    var stub = { pause: function () {}, hold: function () {}, release: function () {}, setPage: function () {}, playOverride: function () {}, clearOverride: function () {} };
    if (!el || !tracks.length) return stub;

    var audio = new Audio();
    audio.preload = 'none';
    var idx = parseInt((function () { try { return sessionStorage.getItem('jh_track'); } catch (e) { return '0'; } })() || '0', 10) || 0;
    if (idx >= tracks.length) idx = 0;
    var media = $('.mw-media', el), coverEl = $('.mw-cover', el), videoBox = $('.mw-video', el);
    var titleEl = $('.mw-title', el), artistEl = $('.mw-artist', el), fill = $('.mw-fill', el), bar = $('.mw-bar', el), vol = $('.mw-vol', el);
    var userPaused = false, held = false;
    var isYt = false, yt = null, ytVid = '', ytErrTimer = 0;
    var volume = 0.7;

    function playing() { return el.classList.contains('playing'); }
    function setPlaying(on) {
      el.classList.toggle('playing', on);
      if (on) { held = false; $$('.theme-audio').forEach(function (a) { a.pause(); }); if (window.Sound) Sound.stop(); }
    }

    function onYtState(e) {
      var st = e.data;
      if (!isYt) return;
      if (st === 1) { setPlaying(true); }
      else if (st === 2) { setPlaying(false); }
      else if (st === 0) { setPlaying(false); load(idx + 1, true); }
    }
    function onYtError() {
      titleEl.textContent = '재생할 수 없는 영상이에요 (퍼가기 금지 등)';
      clearTimeout(ytErrTimer);
      ytErrTimer = setTimeout(function () { if (isYt && tracks.length > 1) load(idx + 1, true); }, 1800);
    }
    function ytStart(id) {
      ensureYT(function () {
        if (!isYt) return;
        el.classList.add('yt-on');
        if (!yt) {
          var inner = document.createElement('div');
          videoBox.appendChild(inner);
          ytVid = id;
          yt = new window.YT.Player(inner, {
            width: '100%', height: '100%', videoId: id,
            playerVars: { autoplay: 1, controls: 0, disablekb: 1, playsinline: 1, rel: 0, origin: window.location.origin },
            events: {
              onReady: function (ev) { try { ev.target.setVolume(volume * 100); ev.target.playVideo(); } catch (err) { /* 무시 */ } },
              onStateChange: onYtState,
              onError: onYtError
            }
          });
        } else if (ytVid !== id) {
          ytVid = id;
          yt.loadVideoById(id);
        } else {
          yt.playVideo();
        }
      });
    }

    /* --- 공통 --- */
    function currentTrack() { return overrideTrackObj || tracks[idx]; }
    function playCur() {
      var t = currentTrack();
      if (isYt) { audio.pause(); ytStart(t.yt); return; }
      if (!audio.src) audio.src = t.src;
      var pr = audio.play();
      if (pr && pr.catch) pr.catch(function () {});
    }
    function pauseCur() {
      if (isYt) { try { if (yt && yt.pauseVideo) yt.pauseVideo(); } catch (e) { /* 무시 */ } }
      else audio.pause();
    }
    function applyTrack(t, play) {
      clearTimeout(ytErrTimer);
      titleEl.textContent = t.title || '제목 없음';
      artistEl.textContent = t.artist || '';
      fill.style.width = '0%';
      if (t.cover) { coverEl.src = t.cover; media.hidden = false; }
      else { coverEl.removeAttribute('src'); media.hidden = !t.yt; } // 커버가 없어도 유튜브 곡이면 영상이 보여야 하니 숨기지 않음
      pauseCur();
      isYt = !!t.yt;
      if (!isYt) el.classList.remove('yt-on');
      if (isYt) {
        audio.removeAttribute('src');
        if (play) playCur(); else setPlaying(false);
      } else {
        try { if (yt && yt.pauseVideo) yt.pauseVideo(); } catch (e) { /* 무시 */ }
        audio.src = t.src;
        if (play) playCur();
      }
    }
    function load(i, play) {
      idx = (i + tracks.length) % tracks.length;
      try { sessionStorage.setItem('jh_track', String(idx)); } catch (e) { /* 무시 */ }
      applyTrack(tracks[idx], play);
    }

    /* --- 글마다 정한 음악: 위젯 자체가 그 곡으로 바뀌고, 그 글을 나가면 원래 듣던 사이트 음악으로 이어서 돌아감 --- */
    var overrideOn = false, overrideTrackObj = null, savedIdx = 0, savedPlaying = false;
    var hidePaused = false;
    function playOverride(track, autoplay) {
      if (!overrideOn) {
        savedIdx = idx; savedPlaying = playing();
        overrideOn = true;
        el.classList.add('override');
      }
      overrideTrackObj = track;
      applyTrack(track, autoplay);
      if (autoplay) {
        setTimeout(function () {
          if (overrideTrackObj !== track || playing()) return;
          var retry = function () {
            document.removeEventListener('pointerdown', retry, true);
            document.removeEventListener('keydown', retry, true);
            if (overrideTrackObj === track && !playing()) playCur();
          };
          document.addEventListener('pointerdown', retry, true);
          document.addEventListener('keydown', retry, true);
        }, 200);
      }
    }
    function clearOverride() {
      if (!overrideOn) return;
      overrideOn = false;
      overrideTrackObj = null;
      el.classList.remove('override');
      idx = savedIdx;
      var wasPlaying = savedPlaying;
      applyTrack(tracks[idx], wasPlaying);
      // PJAX 전환은 fetch()를 거치는 비동기 과정이라, 그사이 브라우저가 "방금 사용자가 눌렀다"는
      // 판단을 거둬들여서 자동 재생이 막히는 경우가 있음. 이때는 화면을 한 번 더 누르면 이어서 재생됨.
      if (wasPlaying) {
        setTimeout(function () {
          if (overrideOn || playing()) return;
          var retry = function () {
            document.removeEventListener('pointerdown', retry, true);
            document.removeEventListener('keydown', retry, true);
            if (!overrideOn && !playing()) playCur();
          };
          document.addEventListener('pointerdown', retry, true);
          document.addEventListener('keydown', retry, true);
        }, 200);
      }
    }
    function toggle() {
      if (playing()) { userPaused = true; pauseCur(); }
      else { userPaused = false; playCur(); }
    }
    function setVol(v) {
      volume = v;
      audio.volume = v;
      try { if (yt && yt.setVolume) yt.setVolume(v * 100); } catch (e) { /* 무시 */ }
    }

    var v0 = parseFloat(lsGet('jh_vol'));
    if (isFinite(v0)) volume = Math.max(0, Math.min(1, v0));
    audio.volume = volume;
    vol.value = volume;
    load(idx, false);

    audio.addEventListener('play', function () { if (!isYt) setPlaying(true); });
    audio.addEventListener('pause', function () { if (!isYt) setPlaying(false); });
    audio.addEventListener('ended', function () {
      if (isYt) return;
      // 이 글만의 음악(override)이 끝났을 때는 사이트 기본 재생목록으로 넘어가지 않고,
      // 그 글의 음악을 그대로 이어서(처음부터) 다시 재생함 -- 배경음악이니까 계속 반복돼야 함.
      if (overrideOn) { playCur(); return; }
      load(idx + 1, true);
    });
    audio.addEventListener('timeupdate', function () {
      if (!isYt && audio.duration) fill.style.width = (audio.currentTime / audio.duration * 100) + '%';
    });
    audio.addEventListener('error', function () { if (!isYt) setPlaying(false); });
    setInterval(function () {
      if (!isYt || !yt || !yt.getDuration) return;
      try { var d = yt.getDuration(); if (d) fill.style.width = (yt.getCurrentTime() / d * 100) + '%'; } catch (e) { /* 무시 */ }
    }, 500);

    $('.mw-play', el).addEventListener('click', toggle);
    $('.mw-prev', el).addEventListener('click', function () { if (!overrideOn) load(idx - 1, true); });
    $('.mw-next', el).addEventListener('click', function () { if (!overrideOn) load(idx + 1, true); });
    $('.mw-min', el).addEventListener('click', function () { el.classList.toggle('mini'); });
    vol.addEventListener('input', function () { setVol(parseFloat(vol.value)); lsSet('jh_vol', vol.value); });
    bar.addEventListener('click', function (e) {
      var r = bar.getBoundingClientRect();
      var ratio = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
      if (isYt) { try { if (yt && yt.getDuration) yt.seekTo(ratio * yt.getDuration(), true); } catch (err) { /* 무시 */ } }
      else if (audio.duration) audio.currentTime = ratio * audio.duration;
    });

    // 자동재생: 브라우저가 막으면 처음 클릭할 때 시작
    // 에디터(글쓰기 화면)로 넘어가기 직전 재생 중이었다면 기억해뒀다가, 돌아왔을 때 이어서 재생
    var resumeAfterEditor = false, resumeTime = 0;
    try {
      // 이 페이지 자체에 글마다 정한 음악(.page-bgm)이 있으면, 곧이어 initView()가 그 곡으로
      // 바꿔줄 거라서 여기서 사이트 기본 곡을 먼저 재생하지 않음(안 그러면 새로고침할 때마다
      // 기본 곡이 잠깐 먼저 나왔다가 바뀌거나, 경합 때문에 기본 곡이 그대로 남는 경우가 있었음).
      var hasPageBgm = !!document.querySelector('.page-bgm');
      if (!hasPageBgm && sessionStorage.getItem('jh_resume') === '1') {
        resumeAfterEditor = true;
        resumeTime = parseFloat(sessionStorage.getItem('jh_resume_time')) || 0;
      }
      sessionStorage.removeItem('jh_resume');
      sessionStorage.removeItem('jh_resume_time');
    } catch (e) { /* 무시 */ }

    if ((JH.autoplay || resumeAfterEditor) && !document.querySelector('.page-bgm')) {
      var first = function () {
        document.removeEventListener('pointerdown', first, true);
        document.removeEventListener('keydown', first, true);
        if (!playing() && !userPaused) playCur();
      };
      document.addEventListener('pointerdown', first, true);
      document.addEventListener('keydown', first, true);
      if (!isYt) {
        if (resumeAfterEditor && resumeTime > 0) {
          audio.addEventListener('loadedmetadata', function onMeta() {
            audio.removeEventListener('loadedmetadata', onMeta);
            try { audio.currentTime = resumeTime; } catch (e2) { /* 무시 */ }
          });
        }
        var t1 = audio.play(); if (t1 && t1.catch) t1.catch(function () {});
      } else if (resumeAfterEditor) {
        playCur();
      }
    }

    // 에디터로 넘어가는 등, 페이지를 떠날 때 재생 중이었다면 다음 로드 때 이어서 재생하도록 기록
    window.addEventListener('pagehide', function () {
      try {
        // 재생 중이던 게 이 글만의 음악(override)이면, 그건 이 페이지에만 해당하는 곡이라
        // 사이트 기본 곡 이어듣기 기록으로 남기지 않음.
        if (playing() && !overrideOn) {
          sessionStorage.setItem('jh_resume', '1');
          sessionStorage.setItem('jh_resume_time', String(isYt ? 0 : (audio.currentTime || 0)));
        }
      } catch (e) { /* 무시 */ }
    });

    if (window.innerWidth < 860) el.classList.add('mini');
    makeDraggable(el, 'music', true);

    return {
      pause: function () { if (playing()) { userPaused = true; pauseCur(); } },
      hold: function () { if (playing()) { held = true; pauseCur(); } },
      release: function () { if (held) { held = false; playCur(); } },
      setPage: function (page, hasOwnBgm) {
        if (hasOwnBgm) { el.classList.remove('hide'); return; } // 이 페이지 고유 음악은 override가 알아서 처리함
        var hide = el.getAttribute('data-scope') === 'home' && page !== 'home';
        el.classList.toggle('hide', hide);
        if (hide) {
          if (playing()) { hidePaused = true; pauseCur(); setPlaying(false); }
        } else if (hidePaused) {
          // 홈에서만 보이는 위젯이 다른 화면에서 숨겨지며 멈췄던 거라면, 홈으로 돌아왔을 때 이어서 재생함.
          hidePaused = false;
          playCur();
          setTimeout(function () {
            if (playing()) return;
            var retry = function () {
              document.removeEventListener('pointerdown', retry, true);
              document.removeEventListener('keydown', retry, true);
              if (!playing()) playCur();
            };
            document.addEventListener('pointerdown', retry, true);
            document.addEventListener('keydown', retry, true);
          }, 200);
        }
      },
      playOverride: playOverride,
      clearOverride: clearOverride
    };
  })();

  /* ============ 프로필 사운드 버튼 (테마곡 / 보이스): 홈 플레이어와 같은 방식(mp3+유튜브)으로 재생 ============ */
  var Sound = (function () {
    var audio = new Audio();
    audio.preload = 'none';
    var yt = null, ytHolder = null, isYt = false, activeBtn = null, activeKey = null;

    function showCaption(text) {
      var cap = $('.pd-caption');
      if (!cap) return;
      cap.textContent = text || '';
      cap.hidden = !text;
    }
    function stop() {
      if (isYt) { try { if (yt && yt.pauseVideo) yt.pauseVideo(); } catch (e) { /* 무시 */ } }
      else { audio.pause(); }
      if (activeBtn) activeBtn.classList.remove('on');
      activeBtn = null; activeKey = null; isYt = false;
      showCaption('');
      Music.release();
    }
    function playSrc(btn, src, ytId, caption) {
      var key = (src || '') + '|' + (ytId || '') + '|' + (caption || '');
      var same = activeBtn === btn && activeKey === key
        && ((isYt && yt) || (!isYt && !audio.paused));
      stop();
      if (same) return; // 같은 트랙을 다시 누르면 정지만 하고 끝
      if (!src && !ytId) return;
      Music.hold();
      activeBtn = btn; activeKey = key;
      btn.classList.add('on');
      isYt = !!ytId;
      showCaption(caption || '');
      if (isYt) {
        ensureYT(function () {
          if (activeBtn !== btn) return; // 그 사이 다른 걸 눌렀으면 무시
          if (!ytHolder) {
            ytHolder = document.createElement('div');
            ytHolder.style.cssText = 'position:fixed;left:-9999px;top:0;width:2px;height:2px;overflow:hidden';
            document.body.appendChild(ytHolder);
            var inner = document.createElement('div');
            ytHolder.appendChild(inner);
            yt = new window.YT.Player(inner, {
              width: '2', height: '2', videoId: ytId,
              playerVars: { autoplay: 1, controls: 0, playsinline: 1, origin: window.location.origin },
              events: {
                onReady: function (ev) { try { ev.target.playVideo(); } catch (e2) { /* 무시 */ } },
                onStateChange: function (ev) { if (ev.data === 0) stop(); },
                onError: function () { stop(); }
              }
            });
          } else {
            yt.loadVideoById(ytId);
          }
        });
      } else {
        audio.src = src;
        audio.onended = stop;
        audio.onerror = stop;
        var pr = audio.play();
        if (pr && pr.catch) pr.catch(function () {});
      }
    }
    return { playSrc: playSrc, stop: stop };
  })();
  window.Sound = Sound;

  document.addEventListener('ended', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('theme-audio')) Music.release();
  }, true);
  document.addEventListener('play', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('theme-audio')) Music.hold();
  }, true);

  /* ============ 라이트박스 ============ */
  var Lb = (function () {
    var el = $('#lb'), img = $('.lb-img', el), cap = $('.lb-cap', el), lockBox = $('.lb-lock', el), blurVeil = $('.lb-blur-veil', el);
    var grid = $('.lb-grid', el), bar = $('.lb-bar', el), countEl = $('.lb-count', el), gridBtn = $('.lb-gridbtn', el);
    var ppBtn = $('.lb-pp', el), pnBtn = $('.lb-pn', el), vid = $('.lb-vid', el);
    function isVid(u) { return /\.(mp4|webm|m4v)(\?|#|$)/i.test(String(u)); }
    function stopVid() { try { vid.pause(); } catch (e) { /* 무시 */ } vid.removeAttribute('src'); vid.load(); vid.hidden = true; vid.classList.remove('blurred'); el.classList.remove('lb-isvid'); }
    function thumbHtml(u) { return isVid(u) ? '<video src="' + esc(u) + '#t=0.1" muted preload="metadata"></video><i class="lb-play"></i>' : '<img src="' + esc(u) + '" alt="">'; }
    function showPostBtns(v) { bar.hidden = !v; ppBtn.hidden = !v; pnBtn.hidden = !v; }
    var ids = [], idx = -1, token = 0, mode = 'gallery', staticItems = [];
    var cur = null, imgIdx = 0, gridOn = false, blurOn = false;   // 한 글 안의 여러 장

    function multi() { return mode === 'gallery' && !!cur && cur.images && cur.images.length > 1; }

    function open(id) {
      mode = 'gallery';
      ids = $$('.g-card').map(function (a) { return String(a.dataset.id); });
      idx = ids.indexOf(String(id));
      if (idx < 0) { ids = [String(id)]; idx = 0; }
      el.hidden = false;
      document.documentElement.classList.add('lb-open');
      load();
    }
    /** items: [{src, title?}], startIdx: 시작 인덱스. 갤러리 API 없이 정적 목록만 보여줄 때 (로그 이미지 등) */
    function openList(items, startIdx) {
      mode = 'static';
      staticItems = items || [];
      idx = Math.max(0, Math.min(staticItems.length - 1, startIdx || 0));
      el.hidden = false;
      document.documentElement.classList.add('lb-open');
      load();
    }
    function resetPost() {
      cur = null; imgIdx = 0; blurOn = false;
      setGrid(false);
      showPostBtns(false);
    }
    function close() {
      if (el.hidden) return;
      el.hidden = true;
      el.classList.remove('nocap');
      document.documentElement.classList.remove('lb-open');
      img.removeAttribute('src');
      stopVid();
      blurVeil.hidden = true;
      img.classList.remove('blurred');
      resetPost();
      token++;
    }
    function load() {
      var my = ++token;
      img.classList.remove('on', 'blurred');
      img.removeAttribute('src');
      stopVid();
      lockBox.innerHTML = '';
      blurVeil.hidden = true;
      resetPost();
      if (mode === 'static') {
        var it = staticItems[idx];
        cap.innerHTML = '';
        if (it) { img.onload = function () { img.classList.add('on'); }; img.src = it.src; img.alt = it.title || ''; }
        return;
      }
      cap.innerHTML = '<p class="lb-load">불러오는 중…</p>';
      fetch(JH.base + 'api.php?a=gallery_item&id=' + encodeURIComponent(ids[idx]), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (my === token) render(d); })
        .catch(function () { if (my === token) cap.innerHTML = '<p class="lb-load">불러오지 못했어요.</p>'; });
    }
    function revealBlur() {
      blurOn = false;
      img.classList.remove('blurred');
      vid.classList.remove('blurred');
      blurVeil.hidden = true;
      grid.classList.remove('blurred');
    }
    function showImg(i) {
      var list = cur.images && cur.images.length ? cur.images : [cur.image];
      imgIdx = (i + list.length) % list.length;
      var u = list[imgIdx];
      if (isVid(u)) {
        img.classList.remove('on'); img.removeAttribute('src');
        vid.hidden = false; el.classList.add('lb-isvid');
        vid.classList.toggle('blurred', blurOn);
        vid.src = u;
        var pr = vid.play(); if (pr && pr.catch) pr.catch(function () { /* 자동 재생이 막혀도 재생 버튼으로 볼 수 있어요 */ });
      } else {
        stopVid();
        img.classList.remove('on');
        img.onload = function () { img.classList.add('on'); };
        img.src = u;
        img.alt = cur.title || '';
      }
      if (list.length > 1) {
        countEl.textContent = (imgIdx + 1) + ' / ' + list.length;
        $$('.lb-thumbs button', cap).forEach(function (b, k) { b.classList.toggle('on', k === imgIdx); });
        var nu = list[(imgIdx + 1) % list.length];
        if (!isVid(nu)) { var nx = new Image(); nx.src = nu; }
      }
    }
    function setGrid(on) {
      gridOn = !!on && multi();
      if (!vid.hidden) { if (gridOn) { try { vid.pause(); } catch (e) { /* 무시 */ } } else { var rp = vid.play(); if (rp && rp.catch) rp.catch(function () {}); } }
      grid.hidden = !gridOn;
      el.classList.toggle('lb-gridon', gridOn);
      gridBtn.setAttribute('aria-pressed', gridOn ? 'true' : 'false');
      var lab = gridBtn.querySelector('span'); if (lab) lab.textContent = gridOn ? '한 장씩 보기' : '전체 보기';
      if (gridOn) {
        grid.classList.toggle('blurred', blurOn);
        grid.innerHTML = '<div class="lb-gin">' + cur.images.map(function (u, k) {
          return '<button type="button" data-i="' + k + '"' + (k === imgIdx ? ' class="cur"' : '') + '>' + thumbHtml(u) + '<em>' + (k + 1) + '</em></button>';
        }).join('') + '</div>';
        grid.scrollTop = 0;
      } else { grid.innerHTML = ''; }
    }
    function render(d) {
      if (!d.ok) { cap.innerHTML = '<p class="lb-load">' + esc(d.msg || '볼 수 없는 이미지예요.') + '</p>'; return; }
      if (d.locked) {
        cap.innerHTML = '';
        lockBox.innerHTML = '<div class="lock-box">' + LOCK_SVG + '<h1>비밀글이에요</h1><p>비밀번호를 입력하면 볼 수 있어요.</p>' +
          '<form data-unlock data-type="gallery" data-id="' + esc(d.id) + '"><input type="password" name="pw" autocomplete="off" required placeholder="비밀번호"><button class="btn" type="submit">열기</button></form>' +
          '<p class="lock-err" role="alert"></p></div>';
        var inp = $('input', lockBox); if (inp) inp.focus();
        return;
      }
      cur = d; if (!cur.images || !cur.images.length) cur.images = cur.image ? [cur.image] : [];
      blurOn = !!d.blur;
      var tags = (d.tags || []).map(function (t) {
        return '<a class="chip" href="' + esc(JH.base + 'gallery.php?tag=' + encodeURIComponent(t)) + '">#' + esc(t) + '</a>';
      }).join('');
      var thumbs = multi() ? '<div class="lb-thumbs">' + cur.images.map(function (u, k) {
        return '<button type="button" data-i="' + k + '" aria-label="' + (k + 1) + '번째 ' + (isVid(u) ? '영상' : '이미지') + '">' + thumbHtml(u) + '</button>';
      }).join('') + '</div>' : '';
      cap.innerHTML = thumbs +
        '<div class="lb-t"><b>' + esc(d.title) + '</b>' + (d.commissioner ? '<span class="lb-c">' + esc(d.commissioner) + '</span>' : '') + '</div>' +
        (d.desc ? '<div class="lb-d">' + d.desc + '</div>' : '') +
        (tags ? '<div class="lb-tags">' + tags + '</div>' : '') +
        '<time>' + esc(d.date) + '</time>';
      showPostBtns(multi());
      showImg(0);
      if (blurOn) { img.classList.add('blurred'); blurVeil.hidden = false; }
    }
    /** 다음 글 / 이전 글 */
    function step(dir) {
      var len = mode === 'static' ? staticItems.length : ids.length;
      if (len < 2) return;
      idx = (idx + dir + len) % len;
      load();
    }
    /** 한 글 안에서 다음 이미지 / 이전 이미지 */
    function stepImg(dir) { if (multi()) showImg(imgIdx + dir); }
    /** 옆 화살표·좌우 키·스와이프: 여러 장이면 이미지, 한 장이면 글 */
    function arrow(dir) { if (multi()) { if (!gridOn) stepImg(dir); } else step(dir); }

    $('.lb-x', el).addEventListener('click', close);
    $('.lb-prev', el).addEventListener('click', function () { arrow(-1); });
    $('.lb-next', el).addEventListener('click', function () { arrow(1); });
    ppBtn.addEventListener('click', function () { step(-1); });
    pnBtn.addEventListener('click', function () { step(1); });
    gridBtn.addEventListener('click', function () { setGrid(!gridOn); });
    grid.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-i]');
      if (b) {
        if (blurOn) { revealBlur(); return; }
        showImg(parseInt(b.getAttribute('data-i'), 10));
        setGrid(false);
      } else if (e.target === grid || e.target.classList.contains('lb-gin')) { setGrid(false); }
    });
    cap.addEventListener('click', function (e) {
      var b = e.target.closest('.lb-thumbs button');
      if (b) { e.stopPropagation(); showImg(parseInt(b.getAttribute('data-i'), 10)); }
    });
    img.addEventListener('click', function () {
      if (img.classList.contains('blurred')) { revealBlur(); return; }
      el.classList.toggle('nocap');
    });
    blurVeil.addEventListener('click', function () { revealBlur(); });
    vid.addEventListener('click', function () { if (vid.classList.contains('blurred')) revealBlur(); });
    el.addEventListener('click', function (e) {
      if (e.target === el || e.target.classList.contains('lb-stage')) close();
      if (e.target.closest('.lb-cap a')) close();
    });
    document.addEventListener('keydown', function (e) {
      if (el.hidden) return;
      var k = e.key;
      if ((k === 'ArrowLeft' || k === 'ArrowRight') && document.activeElement === vid) e.preventDefault();
      if (k === 'Escape') { if (gridOn) setGrid(false); else close(); }
      else if (k === 'ArrowLeft') arrow(-1);
      else if (k === 'ArrowRight') arrow(1);
      else if ((k === 'ArrowUp' || k === 'ArrowDown') && mode === 'gallery') { e.preventDefault(); step(k === 'ArrowUp' ? -1 : 1); }
      else if ((k === 'g' || k === 'G') && multi() && !e.ctrlKey && !e.metaKey && !e.altKey) setGrid(!gridOn);
    });
    var tx = null;
    el.addEventListener('touchstart', function (e) { tx = e.touches.length === 1 ? e.touches[0].clientX : null; }, { passive: true });
    el.addEventListener('touchend', function (e) {
      if (tx === null) return;
      var dx = e.changedTouches[0].clientX - tx;
      if (Math.abs(dx) > 60) arrow(dx > 0 ? -1 : 1);
      tx = null;
    }, { passive: true });

    return { open: open, openList: openList, close: close, reload: load, revealBlur: revealBlur };
  })();

  /* ============ 갤러리 카드: 마우스를 올리면 묶음 이미지가 차례로 바뀜 ============ */
  (function () {
    if (!window.matchMedia || window.matchMedia('(prefers-reduced-motion: reduce)').matches || window.matchMedia('(hover: none)').matches) return;
    function start(card) {
      if (card.__cy) return;
      var th = card.querySelector('.g-th'), im = th && th.querySelector('img'), list;
      if (!im) return;
      try { list = JSON.parse(card.getAttribute('data-imgs') || '[]'); } catch (e) { return; }
      if (!list || list.length < 2) return;
      var st = card.__cy = { i: 0, im: im, cover: im.getAttribute('src'), dots: $$('.g-dots i', th), box: th.querySelector('.g-dots') };
      if (!card.__pre) { card.__pre = list.map(function (u) { var x = new Image(); x.src = u; return x; }); }
      function tick() {
        st.i = (st.i + 1) % list.length;
        im.src = list[st.i];
        im.classList.remove('gflip'); void im.offsetWidth; im.classList.add('gflip');
        st.dots.forEach(function (d, k) { d.classList.toggle('on', k === st.i % st.dots.length); });
      }
      if (st.box) st.box.classList.add('on');
      st.dots.forEach(function (d, k) { d.classList.toggle('on', k === 0); });
      st.t0 = setTimeout(function () { tick(); st.t = setInterval(tick, 1800); }, 700);
    }
    function stop(card) {
      var st = card.__cy; if (!st) return;
      clearTimeout(st.t0); clearInterval(st.t);
      st.im.src = st.cover; st.im.classList.remove('gflip');
      if (st.box) st.box.classList.remove('on');
      card.__cy = null;
    }
    function vidOf(c) { return c.querySelector('.g-th video.g-vid'); }
    document.addEventListener('mouseover', function (e) {
      var c = e.target.closest && e.target.closest('.g-card');
      if (!c || c.contains(e.relatedTarget)) return;
      if (c.classList.contains('g-multi')) start(c);
      var v = vidOf(c); if (v) { var pr = v.play(); if (pr && pr.catch) pr.catch(function () {}); }
    });
    document.addEventListener('mouseout', function (e) {
      var c = e.target.closest && e.target.closest('.g-card');
      if (!c || c.contains(e.relatedTarget)) return;
      if (c.classList.contains('g-multi')) stop(c);
      var v = vidOf(c); if (v) { v.pause(); try { v.currentTime = 0.1; } catch (x) { /* 무시 */ } }
    });
  })();

  /* ============ 페이지 전환 (PJAX) ============ */
  var PJAX_RE = /\/(home|profile|gallery|log|au|trpg|guestbook|timeline|side|rp|running)\.php$/;
  var navToken = 0;

  /* ============ 특정 상세 페이지에서는 왼쪽 메뉴를 숨기고 뒤로가기 버튼으로 대체 ============ */
  var NO_RAIL_PAGES = { 'profile-detail': 1, 'profile-v2': 1, 'au-detail': 1, 'timeline-list': 1, 'timeline-detail': 1, 'rp-list': 1, 'rp-detail': 1 };
  function syncRail(page) {
    document.documentElement.classList.toggle('no-rail', !!NO_RAIL_PAGES[page]);
  }

  function applyView(d) {
    $$('.theme-audio', view).forEach(function (a) { a.pause(); });
    Music.release();
    Sound.stop();
    document.title = d.title;
    bgEl.style.cssText = d.bg || '';
    syncGhost(d.ghost);
    BnTip.hide();
    view.innerHTML = d.html;
    view.setAttribute('data-page', d.page);
    view.setAttribute('data-menu', d.menu);
    view.classList.remove('in');
    void view.offsetWidth;
    view.classList.add('in');
    Rail.set(parseInt(d.menu, 10));
    Music.setPage(d.page, !!view.querySelector('.page-bgm'));
    syncRail(d.page);
    initView();
    Music.setPage(d.page, !!view.querySelector('.page-bgm')); // override 정리 이후 숨김 상태를 최종 확정
  }
  function go(url, push) {
    var my = ++navToken;
    var fetchUrl = url + (url.indexOf('?') > -1 ? '&' : '?') + '_pj=' + Date.now();
    return fetch(fetchUrl, { headers: { 'X-PJAX': '1' }, credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        var ct = r.headers.get('content-type') || '';
        if (ct.indexOf('json') < 0) { window.location.href = url; return null; }
        return r.json();
      })
      .then(function (d) {
        if (!d || my !== navToken) return;
        Lb.close();
        applyView(d);
        if (push) history.pushState({}, '', url);
        window.scrollTo(0, 0);
      })
      .catch(function () { window.location.href = url; });
  }
  window.addEventListener('popstate', function () { go(window.location.href, false); });

  /* ============ 왼쪽 메뉴: 묶음 메뉴(캐릭터/스토리) 펼치기 ============ */
  document.addEventListener('click', function (e) {
    var grp = e.target.closest('.mi-group');
    $$('.mi-group.open').forEach(function (g) { if (g !== grp) g.classList.remove('open'); });
    if (grp) { e.stopPropagation(); grp.classList.toggle('open'); }
  });

  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest('a[href]');
    if (!a) return;
    if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-nopjax')) return;
    var u;
    try { u = new URL(a.href, window.location.href); } catch (err) { return; }
    if (u.origin !== window.location.origin || !PJAX_RE.test(u.pathname)) return;
    e.preventDefault();
    go(u.href, true);
  });

  /* ============ 롤20 원본 HTML(iframe) 높이 자동 맞춤 ============ */
  function fitFrame(f) {
    try {
      var d = f.contentDocument;
      if (!d || !d.documentElement) return;
      f.style.height = '0px';
      var h = Math.max(d.documentElement.scrollHeight, d.body ? d.body.scrollHeight : 0);
      f.style.height = (h + 4) + 'px';
    } catch (e) { /* 무시 */ }
  }
  document.addEventListener('load', function (e) {
    var t = e.target;
    if (!t || !t.classList || !t.classList.contains('raw-frame')) return;
    fitFrame(t);
    [300, 900, 2200, 5000].forEach(function (ms) { setTimeout(function () { fitFrame(t); }, ms); });
    try { t.contentDocument.addEventListener('load', function () { fitFrame(t); }, true); } catch (err) { /* 무시 */ }
  }, true);
  window.addEventListener('resize', function () { $$('.raw-frame').forEach(fitFrame); });

  /* ============ 뷰 초기화 (페이지가 바뀔 때마다) ============ */
  function initView() {
    var dd = $('#dday');
    if (dd) makeDraggable(dd, 'dday', false);
    $$('.sticker', view).forEach(makeStickerDrag);
    $$('.raw-frame', view).forEach(function (f) { setTimeout(function () { fitFrame(f); }, 50); });
    var auto = $('audio[data-auto="1"]', view);
    if (auto) { var pr = auto.play(); if (pr && pr.catch) pr.catch(function () {}); }
    var pageBgm = $('.page-bgm', view);
    if (pageBgm) {
      Music.playOverride({
        title: pageBgm.getAttribute('data-title') || '이 글의 음악',
        artist: '', cover: '',
        src: pageBgm.getAttribute('data-src') || '',
        yt: pageBgm.getAttribute('data-yt') || ''
      }, pageBgm.getAttribute('data-auto') === '1');
    } else {
      Music.clearOverride();
    }
    var open = $('[data-open]', view);
    if (open) Lb.open(open.getAttribute('data-open'));
    pd2Layout();
  }

  /* ============ 프로필 상세(새 디자인): 아래쪽 요소끼리 겹치지 않게 자리 맞추기 ============ */
  function pd2Layout() {
    var sec = $('.pd2', view);
    if (!sec) return;
    var sw = $('.pd2-switch', sec);
    if (window.innerWidth <= 860) {
      sec.style.removeProperty('--pd2-ov-pb');
      sec.style.removeProperty('--pd2-info-max');
      return;
    }
    var sr = sec.getBoundingClientRect();
    // 상세 내용과 기본 정보 목록이 프로필 선택 칸 위에서 끝나도록
    var top = sw ? sw.getBoundingClientRect().top : sr.bottom - 20;
    sec.style.setProperty('--pd2-ov-pb', Math.max(40, Math.round(sr.bottom - top + 26)) + 'px');
    var info = $('.pd2-info', sec);
    if (info) sec.style.setProperty('--pd2-info-max', Math.max(120, Math.round(top - info.getBoundingClientRect().top - 24)) + 'px');
  }
  var pd2Timer = 0;
  function pd2LayoutSoon() { clearTimeout(pd2Timer); pd2Timer = setTimeout(pd2Layout, 80); }
  window.addEventListener('resize', pd2LayoutSoon);
  function pd2SetTab(sec, which) {
    var detail = which === 'detail';
    sec.classList.toggle('is-detail', detail);
    $$('[data-pd2-tab]', sec).forEach(function (b) {
      var on = b.getAttribute('data-pd2-tab') === which;
      b.classList.toggle('on', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    var ov = $('.pd2-ov', sec);
    if (ov) { ov.setAttribute('aria-hidden', detail ? 'false' : 'true'); if (detail) { var inr = $('.pd2-ov-in', ov); if (inr) inr.scrollTop = 0; } }
    pd2Layout();
  }
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var sec = $('.pd2.is-detail', view);
    if (sec && !$('#lb:not([hidden])')) pd2SetTab(sec, 'basic');
  });

  /* ============ 배너 말풍선 (카드 밖으로 나오도록 화면 위층에 띄움) ============ */
  var BnTip = (function () {
    var el = null, cur = null;
    function ensure() {
      if (!el) { el = document.createElement('div'); el.id = 'bnTip'; el.setAttribute('role', 'tooltip'); el.appendChild(document.createElement('span')); document.body.appendChild(el); }
      return el;
    }
    function place(target) {
      var r = target.getBoundingClientRect(), t = ensure();
      var w = t.offsetWidth, h = t.offsetHeight, gap = 10;
      var cx = r.left + r.width / 2;
      var left = Math.max(8, Math.min(window.innerWidth - w - 8, cx - w / 2));
      var below = r.top - h - gap < 6;
      t.classList.toggle('below', below);
      t.style.left = Math.round(left) + 'px';
      t.style.top = Math.round(below ? r.bottom + gap : r.top - h - gap) + 'px';
      t.style.setProperty('--ax', Math.round(Math.max(10, Math.min(w - 10, cx - left))) + 'px');
    }
    function show(target, text, copied) {
      if (!text) { hide(); return; }
      var t = ensure();
      cur = target;
      t.firstChild.textContent = text;
      t.classList.toggle('copied', !!copied);
      place(target);
      t.classList.add('on');
    }
    function hide() { cur = null; if (el) el.classList.remove('on', 'copied'); }
    document.addEventListener('mouseover', function (e) {
      var b = e.target.closest && e.target.closest('.bn[data-tip]');
      if (b === cur) return;
      if (b && !b.classList.contains('bn-copied')) show(b, b.getAttribute('data-tip'), false);
      else if (!b && cur && !cur.classList.contains('bn-copied')) hide();
    });
    document.addEventListener('focusin', function (e) {
      var b = e.target.closest && e.target.closest('.bn[data-tip]');
      if (b) show(b, b.getAttribute('data-tip'), false);
    });
    document.addEventListener('focusout', function (e) {
      if (cur && e.target === cur && !cur.classList.contains('bn-copied')) hide();
    });
    window.addEventListener('scroll', function () { if (cur) place(cur); }, true);
    window.addEventListener('resize', function () { if (cur) place(cur); });
    return { show: show, hide: hide };
  })();

  /* ============ 위임 이벤트 ============ */
  document.addEventListener('click', function (e) {
    var t = e.target;

    // 내 배너(관리자): 눌렀을 때 유동 이미지 주소를 복사
    var cb = t.closest('[data-copy-banner]');
    if (cb) {
      e.preventDefault();
      var fullUrl = cb.getAttribute('data-copy-banner');
      if (fullUrl.indexOf('http') !== 0) {
        var a2 = document.createElement('a'); a2.href = fullUrl; fullUrl = a2.href;
      }
      var showCopied = function () {
        cb.classList.add('bn-copied');
        BnTip.show(cb, '복사됐어요!', true);
        setTimeout(function () {
          cb.classList.remove('bn-copied');
          if (cb.matches(':hover')) BnTip.show(cb, cb.getAttribute('data-tip'), false); else BnTip.hide();
        }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(fullUrl).then(showCopied).catch(function () {
          var ta = document.createElement('textarea');
          ta.value = fullUrl; ta.style.position = 'fixed'; ta.style.opacity = '0';
          document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); } catch (err) { /* 무시 */ }
          document.body.removeChild(ta); showCopied();
        });
      } else {
        var ta2 = document.createElement('textarea');
        ta2.value = fullUrl; ta2.style.position = 'fixed'; ta2.style.opacity = '0';
        document.body.appendChild(ta2); ta2.select();
        try { document.execCommand('copy'); } catch (err) { /* 무시 */ }
        document.body.removeChild(ta2); showCopied();
      }
      return;
    }

    // 프로필(새 디자인): 기본 정보 / 상세 프로필 탭
    var ptab = t.closest('[data-pd2-tab]');
    if (ptab) {
      e.preventDefault();
      var psec = ptab.closest('.pd2');
      if (psec) pd2SetTab(psec, ptab.getAttribute('data-pd2-tab'));
      return;
    }
    // 프로필(새 디자인): 보이스 버튼 하나 = 대사 하나
    var pv = t.closest('.pd2-voice');
    if (pv) {
      e.preventDefault();
      Sound.playSrc(pv, pv.getAttribute('data-src'), '', pv.getAttribute('data-line') || '');
      return;
    }

    // 프로필: 테마곡 / 보이스 버튼
    var sb = t.closest('.snd-btn');
    if (sb) {
      e.preventDefault();
      if (sb.getAttribute('data-kind') === 'theme') {
        Sound.playSrc(sb, sb.getAttribute('data-src'), sb.getAttribute('data-yt'), '');
      } else {
        var grp = sb.closest('.snd-voice');
        var dEl = grp && $('.snd-data', grp);
        var list = dEl ? JSON.parse(dEl.textContent) : [];
        if (list.length) {
          var rIdx = Math.floor(Math.random() * list.length);
          Sound.playSrc(sb, list[rIdx].src, '', list[rIdx].line);
        }
      }
      return;
    }
    var si = t.closest('.snd-item');
    if (si) {
      e.preventDefault();
      var grp2 = si.closest('.snd-voice');
      var dEl2 = grp2 && $('.snd-data', grp2);
      var arr = dEl2 ? JSON.parse(dEl2.textContent) : [];
      var idx3 = parseInt(si.getAttribute('data-idx'), 10);
      var btn3 = $('.snd-btn', grp2);
      if (arr[idx3] && btn3) Sound.playSrc(btn3, arr[idx3].src, '', arr[idx3].line);
      return;
    }

    // 로그 게시글 이미지 → 라이트박스 (같은 글의 다른 이미지로 넘겨볼 수 있음)
    var logImg = t.closest('.log-images img');
    if (logImg) {
      var wrap = logImg.closest('[data-images]');
      var list = [];
      try { list = JSON.parse(wrap.getAttribute('data-images') || '[]'); } catch (e) { list = []; }
      var items = list.map(function (src) { return { src: src }; });
      Lb.openList(items, parseInt(logImg.getAttribute('data-idx'), 10) || 0);
      return;
    }

    // RP·썰 백업 대화 속 이미지 → 라이트박스 (같은 대화의 다른 이미지로 넘겨볼 수 있음)
    var chatImg = t.closest('.rp-bub-img img, .side-bub-img img');
    if (chatImg) {
      var wrapC = chatImg.closest('[data-images]');
      var listC = [];
      try { listC = JSON.parse(wrapC.getAttribute('data-images') || '[]'); } catch (e) { listC = []; }
      var itemsC = listC.map(function (src) { return { src: src }; });
      Lb.openList(itemsC, parseInt(chatImg.getAttribute('data-idx'), 10) || 0);
      return;
    }

    // 본문(리치 텍스트) 안의 이미지 → 라이트박스 (같은 글 안의 다른 이미지로도 넘겨볼 수 있음)
    var bodyImg = t.closest('.rd-body img');
    if (bodyImg) {
      var bodyEl = bodyImg.closest('.rd-body');
      var bimgs = $$('img', bodyEl);
      var bidx = bimgs.indexOf(bodyImg);
      var bitems = bimgs.map(function (im) { return { src: im.currentSrc || im.src }; });
      Lb.openList(bitems, bidx < 0 ? 0 : bidx);
      return;
    }

    // 갤러리 카드 → 라이트박스
    var gc = t.closest('.g-card');
    if (gc && !(e.metaKey || e.ctrlKey || e.shiftKey || e.button)) {
      e.preventDefault();
      Lb.open(gc.getAttribute('data-id'));
      return;
    }

    // 프로필 선택 화면: 터치/클릭으로 카드 열기 (합성 이미지 모드의 좌우 클릭 영역도 동일하게 처리)
    var fig = t.closest('.pf-fig, .pf-name, .pf-combo-zone');
    if (fig) {
      var ch = fig.closest('.pf-char');
      $$('.pf-char').forEach(function (c) { if (c !== ch) c.classList.remove('open'); });
      ch.classList.toggle('open');
      return;
    }

    // AU 상세: 캐릭터 클릭 → 반대쪽에 정보 카드 (합성 이미지 모드의 좌우 클릭 영역도 동일하게 처리)
    var ac = t.closest('.au-char.has-info, .au-combo-zone');
    if (ac) { toggleAu(ac.getAttribute('data-k')); return; }
    if (t.closest('.au-x')) { toggleAu(null); return; }

    // AU 상세: 정보/캐릭터 탭 전환
    var auTab = t.closest('.au-tab');
    if (auTab) {
      var which = auTab.getAttribute('data-tab');
      $$('.au-tab', auTab.parentNode).forEach(function (b) { b.classList.toggle('on', b === auTab); });
      $$('.au-panel', view).forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== which; });
      return;
    }

    // 썰 백업 상세: 대화방 탭 전환
    var sideTab = t.closest('.side-tab');
    if (sideTab) {
      var conv = sideTab.getAttribute('data-conv');
      $$('.side-tab', sideTab.parentNode).forEach(function (b) { b.classList.toggle('on', b === sideTab); });
      $$('.side-chat', view).forEach(function (p) { p.hidden = p.getAttribute('data-conv-panel') !== conv; });
      return;
    }

    // RP 상세: 대화방 탭 전환
    var rpTab = t.closest('.rp-tab');
    if (rpTab) {
      var rconv = rpTab.getAttribute('data-conv');
      $$('.rp-tab', rpTab.parentNode).forEach(function (b) { b.classList.toggle('on', b === rpTab); });
      $$('.rp-chat', view).forEach(function (p) { p.hidden = p.getAttribute('data-conv-panel') !== rconv; });
      return;
    }

    // 방명록 삭제 (관리자, 예전 방식 - 하위 호환용)
    var del = t.closest('[data-gbdel]');
    if (del) {
      if (!window.confirm('이 방명록을 삭제할까요?')) return;
      api('gb_del', { id: del.getAttribute('data-gbdel') }).then(function (d) {
        if (d.ok) go(window.location.href, false); else window.alert(d.msg || '삭제하지 못했어요.');
      });
      return;
    }

    // 방명록: 답글 접기/펼치기
    var gbRT = t.closest('[data-gb-rtoggle]');
    if (gbRT) {
      var rbox = gbRT.parentNode.querySelector('.gb-reply-list');
      if (rbox) {
        var openNow = rbox.hidden;
        rbox.hidden = !openNow;
        gbRT.classList.toggle('open', openNow);
        gbRT.setAttribute('aria-expanded', openNow ? 'true' : 'false');
      }
      return;
    }

    // 방명록: "···" 메뉴 열고 닫기
    var gbMenuBtn = t.closest('[data-gb-menu]');
    if (gbMenuBtn) {
      var thisMenu = gbMenuBtn.nextElementSibling;
      var wasOpen = thisMenu && thisMenu.classList.contains('open');
      $$('.gb-menu.open', view).forEach(function (m) { m.classList.remove('open'); });
      if (thisMenu && !wasOpen) thisMenu.classList.add('open');
      return;
    }
    if (!t.closest('.gb-more-wrap')) { $$('.gb-menu.open', view).forEach(function (m) { m.classList.remove('open'); }); }

    // 방명록: 수정 폼 열기
    var gbEditOpen = t.closest('[data-gb-edit-open]');
    if (gbEditOpen) {
      var liE = gbEditOpen.closest('[data-gb-item]');
      liE.querySelector('.gb-view').hidden = true;
      liE.querySelector('[data-gb-editform]').hidden = false;
      $$('.gb-menu.open', view).forEach(function (m) { m.classList.remove('open'); });
      var ta = liE.querySelector('[data-gb-editform] textarea'); if (ta) ta.focus();
      return;
    }

    // 방명록: 삭제 확인 폼 열기
    var gbDelOpen = t.closest('[data-gb-del-open]');
    if (gbDelOpen) {
      var liD = gbDelOpen.closest('[data-gb-item]');
      liD.querySelector('.gb-view').hidden = true;
      liD.querySelector('[data-gb-delform]').hidden = false;
      $$('.gb-menu.open', view).forEach(function (m) { m.classList.remove('open'); });
      return;
    }

    // 방명록: 답글 폼 열기
    var gbReplyOpen = t.closest('[data-gb-reply-open]');
    if (gbReplyOpen) {
      var liR = gbReplyOpen.closest('[data-gb-item]');
      liR.querySelector('[data-gb-replyform]').hidden = false;
      gbReplyOpen.hidden = true;
      var ta2 = liR.querySelector('[data-gb-replyform] textarea'); if (ta2) ta2.focus();
      return;
    }

    // 방명록: 새 답글(누구나) 쓰기 폼 열기
    var gbrAddOpen = t.closest('[data-gbr-add-open]');
    if (gbrAddOpen) {
      var liRA = gbrAddOpen.closest('[data-gb-item]');
      var rlist = liRA.querySelector('.gb-reply-list');
      if (rlist) rlist.hidden = false;
      var rtoggleA = liRA.querySelector('[data-gb-rtoggle]');
      if (rtoggleA) { rtoggleA.classList.add('open'); rtoggleA.setAttribute('aria-expanded', 'true'); }
      liRA.querySelector('[data-gbr-addform]').hidden = false;
      gbrAddOpen.hidden = true;
      var ta3 = liRA.querySelector('[data-gbr-addform] textarea'); if (ta3) ta3.focus();
      return;
    }

    // 방명록: 새 답글 쓰기 폼 취소
    var gbrAddCancel = t.closest('[data-gbr-add-cancel]');
    if (gbrAddCancel) {
      var formA = gbrAddCancel.closest('form');
      formA.hidden = true;
      var errSpanA = formA.querySelector('.gb-err'); if (errSpanA) errSpanA.textContent = '';
      var liRAc = gbrAddCancel.closest('[data-gb-item]');
      var addBtn = liRAc.querySelector('[data-gbr-add-open]'); if (addBtn) addBtn.hidden = false;
      return;
    }

    // 방명록: 개별 답글 "···" 메뉴
    var gbrMenuBtn = t.closest('[data-gbr-menu]');
    if (gbrMenuBtn) {
      var thisMenuR = gbrMenuBtn.nextElementSibling;
      var wasOpenR = thisMenuR && thisMenuR.classList.contains('open');
      $$('.gb-menu.open', view).forEach(function (m) { m.classList.remove('open'); });
      if (thisMenuR && !wasOpenR) thisMenuR.classList.add('open');
      return;
    }

    // 방명록: 개별 답글 수정 폼 열기
    var gbrEditOpen = t.closest('[data-gbr-edit-open]');
    if (gbrEditOpen) {
      var itemE = gbrEditOpen.closest('[data-gbr-item]');
      itemE.querySelector('.gb-r-view').hidden = true;
      itemE.querySelector('[data-gbr-editform]').hidden = false;
      $$('.gb-menu.open', view).forEach(function (m) { m.classList.remove('open'); });
      var taE = itemE.querySelector('[data-gbr-editform] textarea'); if (taE) taE.focus();
      return;
    }

    // 방명록: 개별 답글 삭제 확인 폼 열기
    var gbrDelOpen = t.closest('[data-gbr-del-open]');
    if (gbrDelOpen) {
      var itemD = gbrDelOpen.closest('[data-gbr-item]');
      itemD.querySelector('.gb-r-view').hidden = true;
      itemD.querySelector('[data-gbr-delform]').hidden = false;
      $$('.gb-menu.open', view).forEach(function (m) { m.classList.remove('open'); });
      return;
    }

    // 방명록: 개별 답글 수정/삭제 폼 취소
    var gbrCancel = t.closest('[data-gbr-cancel]');
    if (gbrCancel) {
      var itemC = gbrCancel.closest('[data-gbr-item]');
      var formC = gbrCancel.closest('form');
      formC.hidden = true;
      var errC = formC.querySelector('.gb-err'); if (errC) errC.textContent = '';
      itemC.querySelector('.gb-r-view').hidden = false;
      return;
    }

    // 방명록: 폼 취소
    var gbCancel = t.closest('[data-gb-cancel]');
    if (gbCancel) {
      var liC = gbCancel.closest('[data-gb-item]');
      var form = gbCancel.closest('form');
      form.hidden = true;
      var errSpan = form.querySelector('.gb-err'); if (errSpan) errSpan.textContent = '';
      if (form.matches('[data-gb-replyform]')) {
        var replyBtn = liC.querySelector('[data-gb-reply-open]'); if (replyBtn) replyBtn.hidden = false;
      } else {
        liC.querySelector('.gb-view').hidden = false;
      }
      return;
    }

    // 위젯 위치를 기본값으로 저장 (관리자)
    var sp = t.closest('#savepos');
    if (sp) {
      var pos = {};
      ['dday', 'music'].forEach(function (k) {
        var w = $('[data-widget="' + k + '"]');
        if (w && !w.classList.contains('hide') && w.offsetWidth) pos[k] = posOf(w, k === 'music');
      });
      var stk = {};
      $$('.sticker', view).forEach(function (el) {
        var par = el.offsetParent;
        if (par && par.clientWidth && par.clientHeight) {
          var entry = { x: +(el.offsetLeft / par.clientWidth * 100).toFixed(2), y: +(el.offsetTop / par.clientHeight * 100).toFixed(2) };
          var wAttr = el.getAttribute('data-w'), rotAttr = el.getAttribute('data-rot');
          if (wAttr !== null) entry.w = Math.round(parseFloat(wAttr));
          if (rotAttr !== null) entry.rot = Math.round(parseFloat(rotAttr));
          stk[el.getAttribute('data-sid')] = entry;
        }
      });
      api('save_pos', { pos: JSON.stringify(pos), stickers: JSON.stringify(stk) }).then(function (d) {
        if (d.ok) {
          JH.widgetVer = d.ver; JH.widgetPos = d.pos;
          try { localStorage.removeItem('jh_pos'); } catch (err) { /* 무시 */ }
          sp.textContent = '저장했어요 ✓';
          setTimeout(function () { sp.textContent = '위젯·스티커 위치 저장'; }, 1800);
        } else { window.alert(d.msg || '저장하지 못했어요.'); }
      });
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var t = e.target;
    if (t.classList && (t.classList.contains('pf-char') || (t.classList.contains('au-char') && t.classList.contains('has-info')))) {
      e.preventDefault();
      if (t.classList.contains('pf-char')) {
        $$('.pf-char').forEach(function (c) { if (c !== t) c.classList.remove('open'); });
        t.classList.toggle('open');
      } else { toggleAu(t.getAttribute('data-k')); }
    }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggleAu(null); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') $$('.mi-group.open').forEach(function (g) { g.classList.remove('open'); }); });

  // AU 상세: data-picked 값에 따라 CSS가 반대쪽에 정보 카드를 보여줌 (캐릭터 스테이지 / 합성 이미지 공통)
  function toggleAu(k) {
    var stage = $('.au-stage, .au-combo', view);
    if (!stage) return;
    var cur = stage.getAttribute('data-picked');
    var next = (k !== null && cur !== String(k)) ? String(k) : '';
    stage.setAttribute('data-picked', next);
    $$('.au-char', stage).forEach(function (c) {
      var on = next !== '' && c.getAttribute('data-k') === next;
      c.classList.toggle('on', on);
      if (c.classList.contains('has-info')) c.setAttribute('aria-expanded', on ? 'true' : 'false');
    });
    var shown = null;
    $$('.au-info', stage).forEach(function (p) {
      var on = next !== '' && p.getAttribute('data-for') === next;
      p.hidden = !on;
      if (on) shown = p;
    });
    if (shown && shown.scrollIntoView) shown.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  document.addEventListener('submit', function (e) {
    var f = e.target;

    // 비밀글 열기
    if (f.matches && f.matches('form[data-unlock]')) {
      e.preventDefault();
      var err = f.parentNode.querySelector('.lock-err');
      var btn = f.querySelector('button');
      btn.disabled = true;
      api('unlock', { type: f.getAttribute('data-type'), id: f.getAttribute('data-id'), pw: f.elements.pw.value })
        .then(function (d) {
          btn.disabled = false;
          if (d.ok) {
            if (f.closest('#lb')) Lb.reload(); else go(window.location.href, false);
          } else {
            if (err) err.textContent = d.msg || '열지 못했어요.';
            f.elements.pw.value = '';
            f.elements.pw.focus();
          }
        })
        .catch(function () { btn.disabled = false; if (err) err.textContent = '네트워크 오류가 났어요.'; });
      return;
    }

    // 방명록 쓰기
    if (f.matches && f.matches('form[data-gb]')) {
      e.preventDefault();
      var msgEl = f.querySelector('.gb-err');
      var b2 = f.querySelector('button[type=submit]');
      b2.disabled = true;
      api('gb_add', {
        name: f.elements.name.value, message: f.elements.message.value,
        pw: f.elements.pw ? f.elements.pw.value : '',
        secret: (f.elements.secret && f.elements.secret.checked) ? '1' : '0',
        website: f.elements.website ? f.elements.website.value : ''
      })
        .then(function (d) {
          b2.disabled = false;
          if (d.ok) go(window.location.href.split('#')[0], false);
          else if (msgEl) msgEl.textContent = d.msg || '남기지 못했어요.';
        })
        .catch(function () { b2.disabled = false; if (msgEl) msgEl.textContent = '네트워크 오류가 났어요.'; });
      return;
    }

    // 방명록 글 수정 저장
    if (f.matches && f.matches('form[data-gb-editform]')) {
      e.preventDefault();
      var li = f.closest('[data-gb-item]');
      var errE = f.querySelector('.gb-err');
      var btnE = f.querySelector('.gb-submit');
      btnE.disabled = true;
      api('gb_edit', {
        id: li.getAttribute('data-id'), message: f.elements.message.value,
        pw: f.elements.pw ? f.elements.pw.value : '',
        secret: (f.elements.secret && f.elements.secret.checked) ? '1' : '0'
      }).then(function (d) {
        btnE.disabled = false;
        if (d.ok) go(window.location.href, false);
        else if (errE) errE.textContent = d.msg || '수정하지 못했어요.';
      }).catch(function () { btnE.disabled = false; if (errE) errE.textContent = '네트워크 오류가 났어요.'; });
      return;
    }

    // 방명록 글 삭제 (비밀번호 확인 폼 또는 관리자)
    if (f.matches && f.matches('form[data-gb-delform]')) {
      e.preventDefault();
      var li2 = f.closest('[data-gb-item]');
      var errD = f.querySelector('.gb-err');
      var btnD = f.querySelector('.gb-submit-del');
      btnD.disabled = true;
      api('gb_del_pw', { id: li2.getAttribute('data-id'), pw: f.elements.pw ? f.elements.pw.value : '' })
        .then(function (d) {
          btnD.disabled = false;
          if (d.ok) go(window.location.href, false);
          else if (errD) errD.textContent = d.msg || '삭제하지 못했어요.';
        }).catch(function () { btnD.disabled = false; if (errD) errD.textContent = '네트워크 오류가 났어요.'; });
      return;
    }

    // 방명록 답글 저장 (관리자)
    if (f.matches && f.matches('form[data-gb-replyform]')) {
      e.preventDefault();
      var li3 = f.closest('[data-gb-item]');
      var errR = f.querySelector('.gb-err');
      var btnR = f.querySelector('.gb-submit');
      btnR.disabled = true;
      api('gb_reply', { id: li3.getAttribute('data-id'), reply: f.elements.reply.value })
        .then(function (d) {
          btnR.disabled = false;
          if (d.ok) go(window.location.href, false);
          else if (errR) errR.textContent = d.msg || '저장하지 못했어요.';
        }).catch(function () { btnR.disabled = false; if (errR) errR.textContent = '네트워크 오류가 났어요.'; });
      return;
    }

    // 방명록 새 답글 등록 (누구나)
    if (f.matches && f.matches('form[data-gbr-addform]')) {
      e.preventDefault();
      var liRA2 = f.closest('[data-gb-item]');
      var errRA = f.querySelector('.gb-err');
      var btnRA = f.querySelector('.gb-submit');
      btnRA.disabled = true;
      api('gbr_add', {
        gb_id: liRA2.getAttribute('data-id'), message: f.elements.message.value,
        name: f.elements.name ? f.elements.name.value : '',
        pw: f.elements.pw ? f.elements.pw.value : ''
      }).then(function (d) {
        btnRA.disabled = false;
        if (d.ok) go(window.location.href, false);
        else if (errRA) errRA.textContent = d.msg || '남기지 못했어요.';
      }).catch(function () { btnRA.disabled = false; if (errRA) errRA.textContent = '네트워크 오류가 났어요.'; });
      return;
    }

    // 방명록 개별 답글 수정 저장
    if (f.matches && f.matches('form[data-gbr-editform]')) {
      e.preventDefault();
      var itemRE = f.closest('[data-gbr-item]');
      var errRE = f.querySelector('.gb-err');
      var btnRE = f.querySelector('.gb-submit');
      btnRE.disabled = true;
      api('gbr_edit', { id: itemRE.getAttribute('data-id'), message: f.elements.message.value, pw: f.elements.pw ? f.elements.pw.value : '' })
        .then(function (d) {
          btnRE.disabled = false;
          if (d.ok) go(window.location.href, false);
          else if (errRE) errRE.textContent = d.msg || '수정하지 못했어요.';
        }).catch(function () { btnRE.disabled = false; if (errRE) errRE.textContent = '네트워크 오류가 났어요.'; });
      return;
    }

    // 방명록 개별 답글 삭제
    if (f.matches && f.matches('form[data-gbr-delform]')) {
      e.preventDefault();
      var itemRD = f.closest('[data-gbr-item]');
      var errRD = f.querySelector('.gb-err');
      var btnRD = f.querySelector('.gb-submit-del');
      btnRD.disabled = true;
      api('gbr_del', { id: itemRD.getAttribute('data-id'), pw: f.elements.pw ? f.elements.pw.value : '' })
        .then(function (d) {
          btnRD.disabled = false;
          if (d.ok) go(window.location.href, false);
          else if (errRD) errRD.textContent = d.msg || '삭제하지 못했어요.';
        }).catch(function () { btnRD.disabled = false; if (errRD) errRD.textContent = '네트워크 오류가 났어요.'; });
      return;
    }
  });

  /* ============ 클릭 효과음 ============ */
  (function () {
    var cfg = JH.clickSound;
    if (!cfg) return;
    var DEFAULT_CLICK = 'data:audio/wav;base64,UklGRsAIAABXQVZFZm10IBAAAAABAAEAIlYAAESsAAACABAAZGF0YZwIAADMTGFM90uNSyRLvEpUSuxJerbgtka3q7cQuHS417g6uWNGAkagRUBF30SARCBEwkNjQ/q8V720vRC+bL7HviK/fL8qQNE/eT8gP8k+cT4aPsQ9ksLnwjzDkcPlwzjEi8TexDDFfjotOtw5jDk8Oew4nThOOAA4TsibyOjINcmByc3JGMpjyq7KCDW/NHU0LDTkM5wzVDMNM8Yygc3HzQ3OUs6XztzOIM9kz6jPFTDSL5AvTS8ML8ouiS5JLgguONJ30rfS9tI003PTsdPu0yvUaNRbKx8r4yqnKmwqMSr2Kbwpgim41vHWKtdj15vX1NcL2EPYetix2Bgn4iasJnYmQSYLJtcloiVuJTol+tou22HblNvG2/nbK9xd3I7cv9zw3N8iriJ+Ik4iHyLvIcAhkSFiITQh+t4o31bfg9+w393fCuA24GPgjuC64Bof7x7EHpkebx5EHhoe8R3HHZ4ddB214t3iBuMu41bjfuOm483j9OMb5ELklxtxG0sbJRv/GtoatBqPGmoaRhohGv0ZJ+ZL5m/mk+a25tnm/OYf50HnZOeG56jnNhgUGPMX0hexF5AXbxdOFy4XDhfuFs4WUulx6ZHpsOnP6e7pDeor6knqaOqG6qPqPxUhFQQV5xTKFK0UkBRzFFcUOxQfFAMU5xM17FDsbOyH7KLsvezY7PLsDe0n7UHtW+117XESVxI+EiQSCxLyEdkRwBGoEY8RdxFeEUYR0u7q7gHvGe8x70jvX+92743vpO+779Hv6O/+7+sP1Q+/D6kPlA9+D2gPUw8+DygPEw/+DuoO1Q7ADlTxafF98ZHxpfG58c3x4fH08QjyG/Iv8kLyVfKYDYUNcg1gDU0NOw0oDRYNBA3yDOAMzgy8DKoMmQyHDIrznPOt877zz/Pg8/HzAvQS9CP0M/RE9FT0ZPR09IT0bAtcC0wLPAstCx0LDgv+Cu8K4ArRCsIKswqkCpUKhgqI9Zf1pfW09cL10PXf9e31+/UJ9hb2JPYy9kD2TfZb9mj2iwl9CXAJYwlWCUkJPAkvCSMJFgkJCf0I8AjkCNcIywi/CLMIWfdl93H3ffeJ95X3ofes97j3w/fP99r35vfx9/z3B/gS+B34KPjNB8IHtwetB6IHlweNB4IHeAdtB2MHWQdPB0QHOgcwByYHHAcSBwkHAfkL+RX5Hvko+TH5O/lE+U75V/lg+Wn5cvl8+YX5jvmX+aD5qPmx+UYGPQY1BiwGIwYbBhIGCgYCBvkF8QXpBeAF2AXQBcgFwAW4BbAFqAWgBZgFb/p3+n/6hvqO+pb6nfql+qz6tPq7+sL6yfrR+tj63/rm+u369Pr7+gL7CfsQ++kE4gTbBNUEzgTHBMAEugSzBK0EpgSgBJkEkwSNBIYEgAR6BHQEbQRnBGEEWwRVBE8Et/u9+8P7yfvP+9X72vvg++b77Pvx+/f7/fsC/Aj8DfwT/Bj8Hvwj/Cj8Lvwz/Dj8PvxD/Ej8swOuA6kDpAOeA5kDlAOPA4oDhgOBA3wDdwNyA20DaQNkA18DWgNWA1EDTQNIA0MDPwM6AzYDMQPT/Nj83Pzg/OX86fzt/PL89vz6/P78Av0H/Qv9D/0T/Rf9G/0f/SP9J/0r/S/9M/03/Tv9Pv1C/Ub9Sv1O/VH9qwKnAqMCoAKcApgClQKRAo4CigKHAoMCgAJ8AnkCdQJyAm4CawJoAmQCYQJeAloCVwJUAlECTQJKAkcCRAJBAj4COgI3Asz9z/3S/dX92P3b/d794f3k/ef96v3t/e/98v31/fj9+/3+/QD+A/4G/gn+C/4O/hH+FP4W/hn+HP4e/iH+I/4m/in+K/4u/jD+M/41/sgBxgHDAcEBvgG8AboBtwG1AbIBsAGuAasBqQGnAaQBogGgAZ4BmwGZAZcBlQGSAZABjgGMAYoBiAGGAYMBgQF/AX0BewF5AXcBdQFzAXEBbwFtAWsBaQFnAWUBnf6f/qH+o/6l/qf+qP6q/qz+rv6w/rL+s/61/rf+uf67/rz+vv7A/sL+w/7F/sf+yf7K/sz+zv7P/tH+0/7U/tb+1/7Z/tv+3P7e/t/+4f7j/uT+5v7n/un+6v7s/u3+7/7w/vL+8/71/vb++P75/gYBBAEDAQEBAAH/AP0A/AD6APkA+AD2APUA9ADyAPEA8ADuAO0A7ADrAOkA6ADnAOUA5ADjAOIA4QDfAN4A3QDcANsA2QDYANcA1gDVANMA0gDRANAAzwDOAM0AzADKAMkAyADHAMYAxQDEAMMAwgDBAMAAvwC+AL0AvAC7ALkAuAC3ALYAtQC1ALQAswCyALEAsACvAK4ArQBU/1X/Vv9X/1j/Wf9a/1v/W/9c/13/Xv9f/2D/Yf9i/2P/Y/9k/2X/Zv9n/2j/aP9p/2r/a/9s/23/bf9u/2//cP9w/3H/cv9z/3T/dP91/3b/d/93/3j/ef96/3r/e/98/33/ff9+/3//f/+A/4H/gf+C/4P/hP+E/4X/hv+G/4f/iP+I/4n/if+K/4v/i/+M/43/jf+O/4//j/+Q/5D/kf+S/5L/k/+T/5T/lf+V/5b/lv+X/5f/mP+Z/5n/mv+a/5v/m/+c/53/nf+e/57/n/+f/6D/oP+h/6H/ov+i/6P/o/+k/6T/pf+l/6b/pv+n/6f/qP+o/6n/qf+q/6r/q/+r/6z/rP+t/63/rf+u/67/r/+v/7D/sP+x/7H/sf+y/7L/s/+z/7T/tP+0/7X/tf+2/7b/tv+3/7f/uP+4/7j/uf+5/7r/uv+6/7v/u/+7/7z/vP+9/73/vf++/77/vv+//7//v//A/8D/wf/B/8H/wv/C/8L/w//D/8P/xP/E/8T/xf/F/8X/xv/G/8b/xv/H/8f/x//I/8j/yP/J/8n/yf/K/8r/yv/K/8v/y//L/8z/zP/M/8z/zf/N/83/zv/O/87/zv/P/8//z//Q/w==';
    var src = cfg.file || DEFAULT_CLICK;
    var pool = [], poolIdx = 0, POOL_SIZE = 5;
    for (var i = 0; i < POOL_SIZE; i++) {
      var a = new Audio(src);
      a.preload = 'auto';
      a.volume = cfg.volume;
      pool.push(a);
    }
    function play() {
      var a = pool[poolIdx];
      poolIdx = (poolIdx + 1) % pool.length;
      try {
        a.currentTime = 0;
        var p = a.play();
        if (p && p.catch) p.catch(function () { /* 자동재생이 막혀 있으면 조용히 무시 */ });
      } catch (e) { /* 무시 */ }
    }
    document.addEventListener('click', function () { play(); }, true);
  })();

  /* ============ 떨어지는 효과 (별가루/물방울/하트 등) ============ */
  (function () {
    var cfg = JH.particles;
    var canvas = document.getElementById('particles');
    if (!cfg || !canvas) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var ctx = canvas.getContext('2d');
    var W = 0, H = 0, DPR = Math.min(2, window.devicePixelRatio || 1);
    function resize() {
      W = window.innerWidth; H = window.innerHeight;
      canvas.width = W * DPR; canvas.height = H * DPR;
      canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
      ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize);

    var count = cfg.count, size = cfg.size, speed = cfg.speed, color = cfg.color, shape = cfg.shape;
    function rand(a, b) { return a + Math.random() * (b - a); }
    function make(fresh) {
      return {
        x: rand(0, W), y: fresh ? rand(0, H) : rand(-40, -4),
        s: rand(size * 0.55, size),
        vy: rand(0.55, 1) * speed * 0.4 + 0.25,
        vx: rand(-0.35, 0.35),
        rot: rand(0, Math.PI * 2), vr: rand(-0.02, 0.02),
        op: rand(0.45, 0.95),
        sway: rand(0, Math.PI * 2)
      };
    }
    var particles = [];
    for (var i = 0; i < count; i++) particles.push(make(true));

    function drawDot(p) {
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.s / 2, 0, Math.PI * 2);
      ctx.fill();
    }
    function drawStar(p) {
      var spikes = 5, outer = p.s / 2, inner = outer / 2.4;
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);
      ctx.beginPath();
      for (var k = 0; k < spikes * 2; k++) {
        var r = k % 2 === 0 ? outer : inner;
        var a = (Math.PI / spikes) * k;
        var px = Math.cos(a) * r, py = Math.sin(a) * r;
        if (k === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
      }
      ctx.closePath();
      ctx.fill();
      ctx.restore();
    }
    function drawDrop(p) {
      var r = p.s / 2;
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot * 0.12);
      ctx.beginPath();
      ctx.moveTo(0, -r * 1.6);
      ctx.bezierCurveTo(r * 1.1, -r * 0.2, r * 0.9, r * 1.3, 0, r * 1.3);
      ctx.bezierCurveTo(-r * 0.9, r * 1.3, -r * 1.1, -r * 0.2, 0, -r * 1.6);
      ctx.closePath();
      ctx.fill();
      ctx.restore();
    }
    function drawHeart(p) {
      var r = p.s / 2.6;
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot * 0.2);
      ctx.beginPath();
      ctx.moveTo(0, r * 0.6);
      ctx.bezierCurveTo(r * 1.6, -r * 0.9, r * 0.4, -r * 2, 0, -r * 0.6);
      ctx.bezierCurveTo(-r * 0.4, -r * 2, -r * 1.6, -r * 0.9, 0, r * 0.6);
      ctx.closePath();
      ctx.fill();
      ctx.restore();
    }
    var draw = { dot: drawDot, star: drawStar, drop: drawDrop, heart: drawHeart }[shape] || drawStar;

    var running = true;
    document.addEventListener('visibilitychange', function () { running = document.visibilityState === 'visible'; });

    function tick() {
      requestAnimationFrame(tick);
      if (!running) return;
      ctx.clearRect(0, 0, W, H);
      ctx.fillStyle = color;
      for (var j = 0; j < particles.length; j++) {
        var p = particles[j];
        p.y += p.vy;
        p.sway += 0.012;
        p.x += p.vx + Math.sin(p.sway) * 0.3;
        p.rot += p.vr;
        if (p.y - p.s > H) { var n = make(false); particles[j] = n; n.y = -n.s; }
        if (p.x < -30) p.x = W + 30; else if (p.x > W + 30) p.x = -30;
        ctx.globalAlpha = p.op;
        draw(p);
      }
      ctx.globalAlpha = 1;
    }
    tick();
  })();

  /* ============ 움직이는(GIF) 커서: 마우스 위치에 이미지를 그대로 그림 ============ */
  (function () {
    var c = JH.cursorFx;
    if (!c || !c.img) return;
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return;
    var el = document.createElement('img');
    el.id = 'cfx'; el.src = c.img; el.alt = ''; el.draggable = false;
    el.style.width = c.size + 'px'; el.style.height = 'auto';
    document.body.appendChild(el);
    if (c.hide) document.documentElement.classList.add('cfx-hide');
    if (c.hover) { var pre = new Image(); pre.src = c.hover; }
    var hovering = false;
    document.addEventListener('mousemove', function (e) {
      el.style.transform = 'translate3d(' + (e.clientX - c.hx) + 'px,' + (e.clientY - c.hy) + 'px,0)';
      el.classList.add('on');
    }, { passive: true });
    document.addEventListener('mouseleave', function () { el.classList.remove('on'); });
    document.addEventListener('mouseover', function (e) {
      var t = e.target;
      // iframe(롤20 원본 로그 등) 안에서는 마우스 위치를 알 수 없어서 잠시 숨김
      if (t.tagName === 'IFRAME') { el.classList.remove('on'); return; }
      if (!c.hover) return;
      var on = !!(t.closest && t.closest('a[href],button,label,[role=button],input[type=submit],input[type=button],.btn,[data-gb-menu]'));
      if (on === hovering) return;
      hovering = on;
      el.src = on ? c.hover : c.img;
    });
  })();

  /* ============ 마우스 효과: 지나간 자리에서 조각이 반짝이며 떨어짐 ============ */
  (function () {
    var cfg = JH.mtrail;
    if (!cfg) return;
    if (window.matchMedia && (window.matchMedia('(pointer: coarse)').matches || window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
    var canvas = document.createElement('canvas');
    canvas.id = 'mtrail';
    document.body.appendChild(canvas);
    var ctx = canvas.getContext('2d');
    var W = 0, H = 0, DPR = Math.min(2, window.devicePixelRatio || 1);
    function resize() {
      W = window.innerWidth; H = window.innerHeight;
      canvas.width = W * DPR; canvas.height = H * DPR;
      canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
      ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize);

    var SHAPES = ['star', 'sparkle', 'heart', 'dot', 'drop'];
    var parts = [], raf = 0, lx = null, ly = null, acc = 0;
    var step = Math.max(5, 24 - cfg.amount * 2);
    var lifeBase = 30 + cfg.life * 12;
    function rand(a, b) { return a + Math.random() * (b - a); }

    function spawn(x, y) {
      if (parts.length > 220) parts.shift();
      parts.push({
        x: x + rand(-4, 4), y: y + rand(-4, 4),
        vx: rand(-0.7, 0.7), vy: rand(-0.4, 0.6),
        g: 0.03 + cfg.life * 0.002,
        s: rand(cfg.size * 0.55, cfg.size),
        rot: rand(0, Math.PI * 2), vr: rand(-0.08, 0.08),
        t: 0, life: rand(lifeBase * 0.7, lifeBase * 1.2),
        c: (cfg.color2 && Math.random() < 0.5) ? cfg.color2 : cfg.color,
        shape: cfg.shape === 'mix' ? SHAPES[(Math.random() * SHAPES.length) | 0] : cfg.shape
      });
    }

    function path(shape, r) {
      var k, a, px, py;
      ctx.beginPath();
      if (shape === 'dot') { ctx.arc(0, 0, r * 0.6, 0, Math.PI * 2); return; }
      if (shape === 'star') {
        for (k = 0; k < 10; k++) {
          var rr = k % 2 === 0 ? r : r / 2.4; a = (Math.PI / 5) * k - Math.PI / 2;
          px = Math.cos(a) * rr; py = Math.sin(a) * rr;
          if (k === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
        }
        ctx.closePath(); return;
      }
      if (shape === 'sparkle') {
        for (k = 0; k < 8; k++) {
          var r2 = k % 2 === 0 ? r : r * 0.22; a = (Math.PI / 4) * k - Math.PI / 2;
          px = Math.cos(a) * r2; py = Math.sin(a) * r2;
          if (k === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
        }
        ctx.closePath(); return;
      }
      if (shape === 'heart') {
        var h = r * 0.75;
        ctx.moveTo(0, h * 0.6);
        ctx.bezierCurveTo(h * 1.6, -h * 0.9, h * 0.4, -h * 2, 0, -h * 0.6);
        ctx.bezierCurveTo(-h * 0.4, -h * 2, -h * 1.6, -h * 0.9, 0, h * 0.6);
        ctx.closePath(); return;
      }
      // drop
      ctx.moveTo(0, -r * 1.1);
      ctx.bezierCurveTo(r * 0.8, -r * 0.1, r * 0.65, r * 0.85, 0, r * 0.85);
      ctx.bezierCurveTo(-r * 0.65, r * 0.85, -r * 0.8, -r * 0.1, 0, -r * 1.1);
      ctx.closePath();
    }

    function tick() {
      raf = 0;
      ctx.clearRect(0, 0, W, H);
      for (var i = parts.length - 1; i >= 0; i--) {
        var p = parts[i];
        p.t++;
        if (p.t >= p.life) { parts.splice(i, 1); continue; }
        p.vy += p.g; p.x += p.vx; p.y += p.vy; p.rot += p.vr;
        var f = p.t / p.life;
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot);
        ctx.globalAlpha = Math.max(0, 1 - f * f);
        ctx.fillStyle = p.c;
        path(p.shape, p.s / 2 * (1 - f * 0.35));
        ctx.fill();
        ctx.restore();
      }
      if (parts.length) raf = requestAnimationFrame(tick);
    }
    function kick() { if (!raf) raf = requestAnimationFrame(tick); }

    document.addEventListener('mousemove', function (e) {
      var x = e.clientX, y = e.clientY;
      if (lx === null) { lx = x; ly = y; return; }
      var dx = x - lx, dy = y - ly, d = Math.sqrt(dx * dx + dy * dy);
      acc += d;
      if (acc >= step) {
        var n = Math.min(6, Math.floor(acc / step));
        for (var i = 1; i <= n; i++) spawn(lx + dx * (i / n), ly + dy * (i / n));
        acc -= n * step;
        kick();
      }
      lx = x; ly = y;
    }, { passive: true });
    document.addEventListener('mouseleave', function () { lx = null; });
  })();

  /* ============ 시작 ============ */
  Rail.init(parseInt(view.getAttribute('data-menu'), 10));
  Music.setPage(view.getAttribute('data-page'), !!view.querySelector('.page-bgm'));
  syncRail(view.getAttribute('data-page'));
  initView();
  Music.setPage(view.getAttribute('data-page'), !!view.querySelector('.page-bgm'));
})();
