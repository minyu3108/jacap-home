<?php
/**
 * 홈페이지 배너의 "유동 이미지 주소". 이 파일 주소 자체를 다른 사이트에 걸어두면,
 * 관리자가 배너 이미지를 바꿀 때마다 그 사이트에 보이는 이미지도 함께 바뀝니다.
 */
require __DIR__ . '/inc/core.php';

$img = (string)S('own_banner');
$path = '';
// uploads 폴더 안의 이미지 파일만 내보내요 (설정값이 잘못 들어가도 다른 파일이 새어 나가지 않게)
if ($img !== '' && strpos($img, 'uploads/') === 0 && strpos($img, '..') === false) {
    $real = realpath(ROOT . '/' . $img);
    $upl = realpath(ROOT . '/uploads');
    $okExt = in_array(strtolower(pathinfo($img, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true);
    if ($real && $upl && strpos($real, $upl . DIRECTORY_SEPARATOR) === 0 && $okExt && is_file($real)) $path = $real;
}

if ($path === '') {
    // 배너가 없을 때는 아주 작은 투명 이미지를 대신 보여줘서 깨진 이미지 아이콘이 뜨지 않게 함
    header('Content-Type: image/gif');
    header('Cache-Control: no-store');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7');
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif'];
header('Content-Type: ' . $mime[$ext]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, must-revalidate');
header('Content-Length: ' . filesize($path));
readfile($path);
