<?php
/** 갤러리: 이미지 카드 목록 + 태그 필터. 클릭하면 라이트박스(전체화면 보기)로 열립니다. */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
board_guard('gallery');

$tag = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$openId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$per = 6;

$all = rows('SELECT id, title, image, more_images, commissioner, tags, card_color, blur, visibility, created_at FROM gallery WHERE ' . vis_sql() . ' ORDER BY created_at DESC, id DESC');

// 태그 모음 (열람 가능한 글의 태그만 집계)
$tagCount = [];
foreach ($all as $r) {
    if (is_locked('gallery', $r)) continue;
    foreach (parse_tags($r['tags']) as $t) { $tagCount[$t] = (isset($tagCount[$t]) ? $tagCount[$t] : 0) + 1; }
}
arsort($tagCount);

$total = count($all);
if ($tag !== '') {
    $all = array_values(array_filter($all, function ($r) use ($tag) {
        return !is_locked('gallery', $r) && in_array($tag, parse_tags($r['tags']), true);
    }));
}
if ($q !== '') {
    $all = array_values(array_filter($all, function ($r) use ($q) {
        return mb_stripos($r['title'], $q) !== false || mb_stripos((string)$r['commissioner'], $q) !== false;
    }));
}
$shown = count($all);
list($off, $pages, $page) = paginate($shown, $per, $page);
$items = array_slice($all, $off, $per);
$qsBase = [];
if ($tag !== '') $qsBase['tag'] = $tag;
if ($q !== '') $qsBase['q'] = $q;

view_start(['title' => S('m_gallery'), 'page' => 'gallery', 'menu' => 2, 'bg' => 'gallery']);
?>
<section class="gallery" <?= $openId ? 'data-open="' . $openId . '"' : '' ?>>
  <header class="pg-head">
    <h1 class="sr-only"><?= h(S('m_gallery')) ?></h1>
    <div class="pg-count">총 <?= $total ?>개</div>
    <div class="pg-actions">
      <form class="board-search" method="get">
        <?php if ($tag !== ''): ?><input type="hidden" name="tag" value="<?= h($tag) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="제목·작가로 검색">
        <button type="submit" aria-label="검색"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg></button>
      </form>
      <?= add_button('gallery', 'gallery.php') ?>
    </div>
    <?php if ($tagCount): ?>
    <div class="chips">
      <a class="chip <?= $tag === '' ? 'on' : '' ?>" href="<?= h(url('gallery.php')) . ($q !== '' ? '?q=' . h(rawurlencode($q)) : '') ?>">전체</a>
      <?php foreach ($tagCount as $t => $n): ?>
        <a class="chip <?= $tag === (string)$t ? 'on' : '' ?>" href="<?= h(url('gallery.php')) ?>?tag=<?= h(rawurlencode($t)) ?><?= $q !== '' ? '&q=' . h(rawurlencode($q)) : '' ?>">#<?= h($t) ?> <small><?= $n ?></small></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </header>

  <?php if (!$items): ?>
    <p class="empty"><?= $q !== '' ? '검색 결과가 없어요.' : '아직 올라온 이미지가 없어요.' . (is_admin() ? ' 관리자 페이지에서 이미지를 올려보세요.' : '') ?></p>
  <?php else: ?>
  <div class="g-grid">
    <?php foreach ($items as $r):
        $locked = ($r['visibility'] === 'password') && is_locked('gallery', $r);
        $gimgs = $locked ? [] : gallery_images($r);   // 잠긴 글의 이미지 주소는 내보내지 않음
        $gpics = []; $gvids = [];
        foreach ($gimgs as $gu) { if (media_is_video($gu)) $gvids[] = $gu; else $gpics[] = $gu; }
        $gcover = $r['image'] !== '' ? $r['image'] : (isset($gpics[0]) ? $gpics[0] : '');
        $gcoverVid = ($gcover === '' && $gvids) ? $gvids[0] : ''; ?>
      <a class="g-card<?= count($gimgs) > 1 ? ' g-multi' : '' ?>" <?= count($gpics) > 1 ? 'data-imgs="' . h(json_encode(array_map('asset', $gpics), JSON_UNESCAPED_SLASHES)) . '" ' : '' ?>style="<?= h(card_style_attr($r)) ?>" data-nopjax data-id="<?= (int)$r['id'] ?>" href="<?= h(url('gallery.php')) ?>?id=<?= (int)$r['id'] ?>">
        <?php if ($locked): ?>
          <span class="g-th lockth"><?= lock_icon() ?></span>
        <?php else: ?>
          <span class="g-th<?= ($gcover === '' && $gcoverVid === '') ? ' ph' : '' ?><?= on1($r['blur']) ? ' g-blur' : '' ?>"><?php if ($gcover !== ''): ?><img loading="lazy" src="<?= h(asset($gcover)) ?>" alt=""><?php elseif ($gcoverVid !== ''): ?><video class="g-vid" muted loop playsinline preload="metadata" src="<?= h(asset($gcoverVid)) ?>#t=0.1"></video><?php endif; ?><?php if ($gvids): ?><span class="g-vbadge" title="영상 포함"><svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"></path></svg>영상</span><?php endif; ?><?php if (count($gimgs) > 1): ?><span class="g-cnt" title="이미지 <?= count($gimgs) ?>장"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"></rect><path d="M4 16V6a2 2 0 0 1 2-2h10"></path></svg><?= count($gimgs) ?></span><span class="g-dots" aria-hidden="true"><?php for ($di = 0; $di < min(count($gimgs), 8); $di++): ?><i></i><?php endfor; ?></span><?php endif; ?><?php if (on1($r['blur'])): ?><span class="g-blur-tag">흐림 처리됨</span><?php endif; ?></span>
        <?php endif; ?>
        <span class="g-meta">
          <b><?= h($r['title']) ?></b>
          <?php if (!$locked && $r['commissioner'] !== ''): ?><i><?= h($r['commissioner']) ?></i><?php endif; ?>
          <?php if ($r['visibility'] === 'admin'): ?><i>관리자만</i><?php endif; ?>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
  <?= pager($page, $pages, $qsBase) ?>
  <?php endif; ?>
</section>
<?php
view_end();
