<?php
/** 랜딩 페이지: 로고/배경 이미지를 누르면 메인 홈으로 입장 */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';

if (S('landing_on') !== '1') { redirect(url('home.php')); }

$bg = bg_css(S('landing_bg_color'), S('landing_bg_color2'), S('landing_bg_image'));
$logo = S('landing_logo');
$w = max(60, min(2000, (int)S('landing_logo_w')));
$fav = S('favicon');
$verc = @filemtime(ROOT . '/assets/css/site.css') ?: 1;
?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(S('site_title')) ?></title>
<?= favicon_tags() ?>
<?= og_tags() ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gowun+Batang:wght@400;700&family=Gowun+Dodum&family=Nanum+Myeongjo:wght@400;700&family=Nanum+Gothic:wght@400;700&display=swap">
<link rel="stylesheet" href="<?= h(url('assets/css/site.css')) ?>?v=<?= $verc ?>">
<style><?= custom_font_css() ?>:root{<?php $ci=S('cursor_img'); if ($ci!=='') echo "--cursor:url('".cssurl(asset($ci))."') 4 4, auto;"; ?>--font:<?= font_stack(S('font')) ?>;--head:<?= font_stack(S('head_font')) ?>;<?= cssvar('land-ink', S('landing_ink')) ?>}</style>
</head>
<body class="landing" style="<?= h($bg) ?>">
<a class="land-enter" href="<?= h(url('home.php')) ?>" aria-label="입장하기">
  <?php if ($logo !== ''): ?>
    <img src="<?= h(asset($logo)) ?>" alt="<?= h(S('site_title')) ?>" style="width:<?= $w ?>px">
  <?php else: ?>
    <span class="land-title"><?= h(S('site_title')) ?></span>
  <?php endif; ?>
  <?php if (S('landing_caption') !== ''): ?><span class="land-cap"><?= h(S('landing_caption')) ?></span><?php endif; ?>
</a>
</body>
</html>
