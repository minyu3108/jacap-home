<?php
/** 메인 홈: 큰 이미지 + 로그인 카드 + 배너 + 디데이 위젯 (뮤직 플레이어는 공통 레이아웃에 있음) */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';

view_start(['title' => S('site_title'), 'page' => 'home', 'menu' => -1, 'bg' => 'home']);

$banners = rows('SELECT * FROM banners ORDER BY sort_order ASC, id ASC');
$dd = (S('dday_on') === '1') ? dday_items() : [];
$img = S('home_image');
$link = safe_url(S('home_link'));

$cardStyle = '';
if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('login_color'))) $cardStyle .= 'background-color:' . S('login_color') . ';';
if (S('login_bg') !== '') $cardStyle .= "background-image:url('" . cssurl(asset(S('login_bg'))) . "');background-size:cover;background-position:center;";

$plain = (S('dday_plain') === '1');
$ddImg = S('dday_img');
$ddStyle = '';
if (!$plain) {
    if (S('dday_bg_color') !== '') $ddStyle .= 'background-color:' . S('dday_bg_color') . ';';
    if (S('dday_bg') !== '') $ddStyle .= "background-image:url('" . cssurl(asset(S('dday_bg'))) . "');background-size:cover;background-position:center;";
}
if (S('dday_text') !== '') $ddStyle .= 'color:' . S('dday_text') . ';';
if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('dday_num_color'))) $ddStyle .= '--dd-num:' . S('dday_num_color') . ';';
$ddImgW = max(20, min(600, (int)S('dday_img_w')));

try { $stickers = rows('SELECT * FROM stickers ORDER BY z ASC, id ASC'); } catch (Exception $e) { $stickers = []; }
?>
<div class="hm">
  <div class="hm-stage" id="stage">
    <div class="hm-main">
      <?php if ($img !== ''): ?>
        <?php if ($link !== ''): ?><a href="<?= h($link) ?>" target="_blank" rel="noopener"><?php endif; ?>
        <img src="<?= h(asset($img)) ?>" alt="">
        <?php if ($link !== ''): ?></a><?php endif; ?>
      <?php elseif (is_admin()): ?>
        <div class="hm-empty">관리자 페이지 &gt; 사이트 설정 &gt; "메인 홈"에서<br>메인 이미지를 등록해 주세요.</div>
      <?php endif; ?>
    </div>

    <?php foreach ($stickers as $sk):
        if ($sk['image'] === '') continue;
        $sw = (int)$sk['w'] > 0 ? min(1200, (int)$sk['w']) : 150;
        $rot = max(-180, min(180, (int)$sk['rot']));
        $sz = 2 + max(0, min(5, (int)$sk['z']));
        $sst = 'left:' . ((int)$sk['pos_x'] / 10) . '%;top:' . ((int)$sk['pos_y'] / 10) . '%;width:' . $sw . 'px;z-index:' . $sz . ';transform:rotate(' . $rot . 'deg);'; ?>
      <img class="sticker <?= $sk['card_style'] ? 'sticker-card' : '' ?>" data-sid="<?= (int)$sk['id'] ?>" data-w="<?= $sw ?>" data-rot="<?= $rot ?>" src="<?= h(asset($sk['image'])) ?>" alt="" style="<?= h($sst) ?>" draggable="false">
    <?php endforeach; ?>

    <?php if ($dd || $ddImg !== ''): ?>
    <div class="widget dday<?= $plain ? ' plain' : '' ?>" id="dday" data-widget="dday" style="<?= h($ddStyle) ?>">
      <?php if (!$plain): ?><div class="dd-veil"></div><?php endif; ?>
      <div class="dd-in">
        <?php if ($ddImg !== ''): ?><img class="dd-img" src="<?= h(asset($ddImg)) ?>" alt="" style="width:<?= $ddImgW ?>px"><?php endif; ?>
        <?php if (S('dday_title') !== ''): ?><div class="dd-title"><?= h(S('dday_title')) ?></div><?php endif; ?>
        <?php foreach ($dd as $d): ?>
          <div class="dd-row"><span class="dd-l"><?= h($d['label']) ?></span><i class="dd-dots"></i><b class="dd-n"><?= h($d['text']) ?></b></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <aside class="hm-side">
    <section class="login-card" style="<?= h($cardStyle) ?>">
      <?php if (S('login_bg') !== ''): ?><div class="lc-veil"></div><?php endif; ?>
      <div class="lc-in">
      <?php if (is_admin()): ?>
        <div class="lc-title"><?= h(admin_name()) ?>님</div>
        <div class="lc-btns">
          <a class="btn sm" data-nopjax href="<?= h(url('admin/')) ?>">관리자 페이지</a>
          <button type="button" class="btn sm ghost" id="savepos" title="지금 위젯 위치를 방문자들의 기본 위치로 저장해요">위젯·스티커 위치 저장</button>
          <a class="btn sm ghost" data-nopjax href="<?= h(url('login.php')) ?>?out=1&amp;t=<?= h(csrf_token()) ?>">로그아웃</a>
        </div>
      <?php else: ?>
        <div class="lc-title"><?= h(S('login_title')) ?></div>
        <form method="post" action="<?= h(url('login.php')) ?>" class="lc-form">
          <?= csrf_field() ?>
          <input type="text" name="username" placeholder="아이디" autocomplete="username" required>
          <input type="password" name="password" placeholder="비밀번호" autocomplete="current-password" required>
          <button class="btn sm" type="submit">로그인</button>
        </form>
        <?php if (isset($_GET['needlogin'])): ?><p class="lc-err" role="alert">관리자 페이지는 로그인 후 이용할 수 있어요.</p><?php endif; ?>
        <?php if (isset($_GET['loginfail'])): ?><p class="lc-err" role="alert">아이디 또는 비밀번호가 맞지 않아요.</p><?php endif; ?>
        <?php if (isset($_GET['locked'])): ?><p class="lc-err" role="alert">시도 횟수가 너무 많아요. 10분 뒤에 다시 해주세요.</p><?php endif; ?>
      <?php endif; ?>
      </div>
    </section>

    <?php $ownBanner = S('own_banner'); if ($banners || $ownBanner !== ''): ?>
    <section class="banner-box" aria-label="배너">
      <div class="banner-scroll">
        <?php if ($ownBanner !== ''):
            $ownName = S('site_title'); ?>
          <button type="button" class="bn" data-tip="눌러서 배너 주소 복사" data-copy-banner="<?= h(url('banner.php')) ?>">
            <img loading="lazy" src="<?= h(url('banner.php')) ?>" alt="<?= h($ownName) ?>">
          </button>
        <?php endif; ?>
        <?php foreach ($banners as $b):
            if ($b['image'] === '') continue;
            $bl = safe_url($b['link']); ?>
          <?php if ($bl !== ''): ?><a class="bn" href="<?= h($bl) ?>"<?= $b['newtab'] ? ' target="_blank" rel="noopener"' : '' ?> data-nopjax data-tip="<?= h($b['title']) ?>"><?php else: ?><span class="bn" data-tip="<?= h($b['title']) ?>"><?php endif; ?>
            <img loading="lazy" src="<?= h(asset($b['image'])) ?>" alt="<?= h($b['title']) ?>">
          <?php if ($bl !== ''): ?></a><?php else: ?></span><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php
view_end();
