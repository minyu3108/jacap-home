<?php
/** 프론트에서 쓰는 작은 API (JSON) */
require __DIR__ . '/inc/core.php';

$a = isset($_GET['a']) ? (string)$_GET['a'] : '';

/* ---- 갤러리 한 장 정보 (라이트박스용) ---- */
if ($a === 'gallery_item') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $r = row('SELECT * FROM gallery WHERE id=?', [$id]);
    if (!$r || ($r['visibility'] === 'admin' && !is_admin())) { json_out(['ok' => false, 'msg' => '볼 수 없는 이미지예요.']); }
    if (!can_view('gallery', $r)) { json_out(['ok' => true, 'locked' => true, 'id' => (int)$r['id']]); }
    json_out([
        'ok' => true, 'locked' => false, 'id' => (int)$r['id'],
        'title' => $r['title'], 'image' => asset($r['image']), 'images' => array_map('asset', gallery_images($r)),
        'commissioner' => $r['commissioner'], 'tags' => parse_tags($r['tags']),
        'desc' => nl2br_h($r['body']), 'date' => fmt_date($r['created_at']),
        'blur' => on1($r['blur']),
    ]);
}

/* ---- 이하는 모두 POST + CSRF ---- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_out(['ok' => false, 'msg' => '잘못된 요청'], 405); }
if (!csrf_ok()) { json_out(['ok' => false, 'msg' => '페이지를 새로고침한 뒤 다시 시도해 주세요.'], 403); }

function post($k) { return isset($_POST[$k]) && !is_array($_POST[$k]) ? (string)$_POST[$k] : ''; }

/* ---- 비밀글 열기 ---- */
if ($a === 'unlock') {
    $type = post('type');
    if (!in_array($type, ['gallery', 'logs', 'trpg', 'aus', 'timeline', 'side', 'rp', 'running'], true)) { json_out(['ok' => false, 'msg' => '잘못된 요청']); }
    if (attempts_count('unlock', 600) >= 10) { json_out(['ok' => false, 'msg' => '시도 횟수가 너무 많아요. 10분 뒤에 다시 해주세요.']); }
    $id = (int)post('id');
    $r = row('SELECT id, visibility, pw_hash FROM `' . $type . '` WHERE id=?', [$id]);
    if ($r && $r['visibility'] === 'password' && $r['pw_hash'] !== '' && password_verify(post('pw'), $r['pw_hash'])) {
        $_SESSION['unlock'][$type . ':' . $id] = true;
        json_out(['ok' => true]);
    }
    attempts_add('unlock');
    usleep(500000);
    json_out(['ok' => false, 'msg' => '비밀번호가 맞지 않아요.']);
}

/* ---- 방명록 쓰기 ---- */
if ($a === 'gb_add') {
    if (!board_on('guestbook')) { json_out(['ok' => false, 'msg' => '방명록은 지금 사용하지 않아요.']); }
    if (!is_admin() && S('gb_open') !== '1') { json_out(['ok' => false, 'msg' => '지금은 관리자만 남길 수 있어요.']); }
    if (post('website') !== '') { json_out(['ok' => true]); } // 스팸봇 함정 칸
    $name = cut(trim(strip_tags(post('name'))), 20);
    $msg = cut(trim(strip_tags(post('message'))), 500);
    if ($name === '' || $msg === '') { json_out(['ok' => false, 'msg' => '이름과 메시지를 입력해 주세요.']); }
    if (!is_admin()) {
        if (attempts_count('gb', 300) >= 3) { json_out(['ok' => false, 'msg' => '너무 자주 남길 수 없어요. 잠시 후 다시 시도해 주세요.']); }
        if (preg_match_all('#https?://#i', $msg) > 2) { json_out(['ok' => false, 'msg' => '링크는 2개까지만 넣을 수 있어요.']); }
        attempts_add('gb');
    }
    $pw = cut(trim((string)post('pw')), 50);
    $secret = post('secret') === '1';
    insert_row('guestbook', [
        'name' => $name, 'message' => $msg,
        'visibility' => $secret ? 'admin' : 'public',
        'pw_hash' => $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : '',
        'created_at' => now(),
    ]);
    json_out(['ok' => true]);
}

/* ---- 방명록 글 수정 (작성자는 비밀번호로, 관리자는 바로) ---- */
if ($a === 'gb_edit') {
    if (!board_on('guestbook')) { json_out(['ok' => false, 'msg' => '방명록은 지금 사용하지 않아요.']); }
    $id = (int)post('id');
    $r = row('SELECT * FROM guestbook WHERE id=?', [$id]);
    if (!$r) { json_out(['ok' => false, 'msg' => '글을 찾을 수 없어요.']); }
    if (!is_admin()) {
        if (attempts_count('unlock', 600) >= 10) { json_out(['ok' => false, 'msg' => '비밀번호를 너무 많이 틀렸어요. 10분 뒤에 다시 해주세요.']); }
        if ($r['pw_hash'] === '' || !password_verify((string)post('pw'), $r['pw_hash'])) {
            attempts_add('unlock');
            usleep(500000);
            json_out(['ok' => false, 'msg' => '비밀번호가 맞지 않아요.']);
        }
    }
    $msg = cut(trim(strip_tags(post('message'))), 500);
    if ($msg === '') { json_out(['ok' => false, 'msg' => '메시지를 입력해 주세요.']); }
    $secret = post('secret') === '1';
    q('UPDATE guestbook SET message=?, visibility=? WHERE id=?', [$msg, $secret ? 'admin' : 'public', $id]);
    json_out(['ok' => true]);
}

/* ---- 방명록 글 삭제 (작성자는 비밀번호로, 관리자는 바로) ---- */
if ($a === 'gb_del_pw') {
    if (!board_on('guestbook')) { json_out(['ok' => false, 'msg' => '방명록은 지금 사용하지 않아요.']); }
    $id = (int)post('id');
    $r = row('SELECT * FROM guestbook WHERE id=?', [$id]);
    if (!$r) { json_out(['ok' => false, 'msg' => '글을 찾을 수 없어요.']); }
    if (!is_admin()) {
        if (attempts_count('unlock', 600) >= 10) { json_out(['ok' => false, 'msg' => '비밀번호를 너무 많이 틀렸어요. 10분 뒤에 다시 해주세요.']); }
        if ($r['pw_hash'] === '' || !password_verify((string)post('pw'), $r['pw_hash'])) {
            attempts_add('unlock');
            usleep(500000);
            json_out(['ok' => false, 'msg' => '비밀번호가 맞지 않아요.']);
        }
    }
    q('DELETE FROM guestbook WHERE id=?', [$id]);
    json_out(['ok' => true]);
}

/* ---- 방명록 답글 쓰기 (관리자·방문자 모두 가능) ---- */
if ($a === 'gbr_add') {
    if (!board_on('guestbook')) { json_out(['ok' => false, 'msg' => '방명록은 지금 사용하지 않아요.']); }
    $gbId = (int)post('gb_id');
    try { $gb = row('SELECT id, visibility FROM guestbook WHERE id=?', [$gbId]); } catch (Exception $e) { $gb = null; }
    if ($gb && $gb['visibility'] === 'admin' && !is_admin()) { $gb = null; } // 비밀글에는 방문자가 답글을 달 수 없음
    if (!$gb) { json_out(['ok' => false, 'msg' => '원글을 찾을 수 없어요.']); }
    if (post('website') !== '') { json_out(['ok' => true]); } // 스팸봇 함정 칸
    $message = cut(trim(strip_tags(post('message'))), 500);
    if ($message === '') { json_out(['ok' => false, 'msg' => '답글 내용을 입력해 주세요.']); }
    if (is_admin()) {
        $name = cut(trim((string)admin_name()), 20);
        $pwHash = '';
        $isAdminFlag = '1';
    } else {
        $name = cut(trim(strip_tags(post('name'))), 20);
        if ($name === '') { json_out(['ok' => false, 'msg' => '이름을 입력해 주세요.']); }
        if (attempts_count('gbr', 300) >= 5) { json_out(['ok' => false, 'msg' => '너무 자주 남길 수 없어요. 잠시 후 다시 시도해 주세요.']); }
        if (preg_match_all('#https?://#i', $message) > 2) { json_out(['ok' => false, 'msg' => '링크는 2개까지만 넣을 수 있어요.']); }
        attempts_add('gbr');
        $pw = cut(trim((string)post('pw')), 50);
        $pwHash = $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : '';
        $isAdminFlag = '0';
    }
    try {
        insert_row('guestbook_replies', [
            'gb_id' => $gbId, 'name' => $name, 'message' => $message, 'is_admin' => $isAdminFlag,
            'visibility' => 'public', 'pw_hash' => $pwHash, 'created_at' => now(),
        ]);
    } catch (Exception $e) {
        json_out(['ok' => false, 'msg' => '아직 답글 기능을 쓸 준비가 안 됐어요. 관리자에게 "데이터 구조 업데이트"를 요청해 주세요.']);
    }
    json_out(['ok' => true]);
}

/* ---- 방명록 답글 수정 (작성자는 비밀번호로, 관리자는 바로) ---- */
if ($a === 'gbr_edit') {
    if (!board_on('guestbook')) { json_out(['ok' => false, 'msg' => '방명록은 지금 사용하지 않아요.']); }
    $id = (int)post('id');
    try { $r = row('SELECT * FROM guestbook_replies WHERE id=?', [$id]); } catch (Exception $e) { $r = null; }
    if (!$r) { json_out(['ok' => false, 'msg' => '답글을 찾을 수 없어요.']); }
    if (!is_admin()) {
        if (attempts_count('unlock', 600) >= 10) { json_out(['ok' => false, 'msg' => '비밀번호를 너무 많이 틀렸어요. 10분 뒤에 다시 해주세요.']); }
        if ($r['pw_hash'] === '' || !password_verify((string)post('pw'), $r['pw_hash'])) {
            attempts_add('unlock');
            usleep(500000);
            json_out(['ok' => false, 'msg' => '비밀번호가 맞지 않아요.']);
        }
    }
    $message = cut(trim(strip_tags(post('message'))), 500);
    if ($message === '') { json_out(['ok' => false, 'msg' => '답글 내용을 입력해 주세요.']); }
    q('UPDATE guestbook_replies SET message=? WHERE id=?', [$message, $id]);
    json_out(['ok' => true]);
}

/* ---- 방명록 답글 삭제 (작성자는 비밀번호로, 관리자는 바로) ---- */
if ($a === 'gbr_del') {
    if (!board_on('guestbook')) { json_out(['ok' => false, 'msg' => '방명록은 지금 사용하지 않아요.']); }
    $id = (int)post('id');
    try { $r = row('SELECT * FROM guestbook_replies WHERE id=?', [$id]); } catch (Exception $e) { $r = null; }
    if (!$r) { json_out(['ok' => false, 'msg' => '답글을 찾을 수 없어요.']); }
    if (!is_admin()) {
        if (attempts_count('unlock', 600) >= 10) { json_out(['ok' => false, 'msg' => '비밀번호를 너무 많이 틀렸어요. 10분 뒤에 다시 해주세요.']); }
        if ($r['pw_hash'] === '' || !password_verify((string)post('pw'), $r['pw_hash'])) {
            attempts_add('unlock');
            usleep(500000);
            json_out(['ok' => false, 'msg' => '비밀번호가 맞지 않아요.']);
        }
    }
    q('DELETE FROM guestbook_replies WHERE id=?', [$id]);
    json_out(['ok' => true]);
}

/* ---- 이하는 관리자 전용 ---- */
if (!is_admin()) { json_out(['ok' => false, 'msg' => '관리자만 할 수 있어요.'], 403); }

if ($a === 'gb_del') {
    q('DELETE FROM guestbook WHERE id=?', [(int)post('id')]);
    json_out(['ok' => true]);
}

if ($a === 'gb_reply') {
    $id = (int)post('id');
    $reply = cut(trim(strip_tags(post('reply'))), 500);
    $by = $reply !== '' ? cut(trim((string)admin_name()), 30) : '';
    q('UPDATE guestbook SET reply=?, reply_at=?, reply_by=? WHERE id=?', [$reply, $reply !== '' ? now() : '', $by, $id]);
    json_out(['ok' => true]);
}


if ($a === 'save_pos') {
    $in = json_decode(post('pos'), true);
    $clean = [];
    if (is_array($in)) {
        foreach (['dday', 'music'] as $k) {
            if (isset($in[$k]['x'], $in[$k]['y']) && is_numeric($in[$k]['x']) && is_numeric($in[$k]['y'])) {
                $clean[$k] = ['x' => round(max(0, min(100, (float)$in[$k]['x'])), 2), 'y' => round(max(0, min(100, (float)$in[$k]['y'])), 2)];
            }
        }
    }
    $st = json_decode(post('stickers'), true);
    if (is_array($st)) {
        foreach ($st as $sid => $pp) {
            if (isset($pp['x'], $pp['y']) && is_numeric($pp['x']) && is_numeric($pp['y'])) {
                $sets = ['pos_x = ?', 'pos_y = ?'];
                $vals = [(int)round(max(0, min(100, (float)$pp['x'])) * 10), (int)round(max(0, min(100, (float)$pp['y'])) * 10)];
                if (isset($pp['w']) && is_numeric($pp['w'])) { $sets[] = 'w = ?'; $vals[] = (int)max(20, min(1200, (float)$pp['w'])); }
                if (isset($pp['rot']) && is_numeric($pp['rot'])) { $sets[] = 'rot = ?'; $vals[] = (int)max(-180, min(180, (float)$pp['rot'])); }
                $vals[] = (int)$sid;
                q('UPDATE stickers SET ' . implode(', ', $sets) . ' WHERE id=?', $vals);
            }
        }
    }
    $old = json_decode((string)S('widget_pos', '{}'), true);
    if (!is_array($old)) $old = [];
    $merged = array_merge($old, $clean);
    $ver = (string)time();
    set_setting('widget_pos', json_encode($merged));
    set_setting('widget_ver', $ver);
    json_out(['ok' => true, 'ver' => $ver, 'pos' => $merged]);
}

json_out(['ok' => false, 'msg' => '알 수 없는 요청'], 400);
