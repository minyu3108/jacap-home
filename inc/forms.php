<?php
/** 관리자 입력폼 렌더링 / 값 수집 */

function field_opts($f) {
    if (isset($f['opts']) && $f['opts'] === '@chars') {
        return ['1' => S('char1_name', 'A'), '2' => S('char2_name', 'B')];
    }
    if (isset($f['opts']) && $f['opts'] === '@profiles') {
        $o = [];
        try {
            foreach (rows('SELECT id, name, side FROM profiles ORDER BY side ASC, sort_order ASC, id ASC') as $pr) {
                $who = ((string)$pr['side'] === '2') ? S('char2_name', 'B') : S('char1_name', 'A');
                $o[(string)$pr['id']] = $who . ' · ' . $pr['name'];
            }
        } catch (Exception $e) { /* 무시 */ }
        return $o;
    }
    return isset($f['opts']) ? $f['opts'] : [];
}

function render_field($f, $val) {
    $n = $f['n'];
    $id = 'f_' . $n;
    $help = isset($f['h']) ? '<small class="help">' . nl2br(h($f['h'])) . '</small>' : '';
    $o = '<div class="fld fld-' . h($f['t']) . '">';
    $o .= '<label for="' . h($id) . '">' . h($f['l']) . '</label>';
    switch ($f['t']) {
        case 'text':
            $o .= '<input type="text" id="' . h($id) . '" name="v_' . h($n) . '" value="' . h($val) . '" maxlength="255">';
            break;
        case 'code':
            $o .= '<textarea id="' . h($id) . '" name="v_' . h($n) . '" rows="' . (empty($f['raw']) ? '8' : '14') . '" class="codebox' . (empty($f['raw']) ? '' : ' rawbox') . '" spellcheck="false">' . h($val) . '</textarea>';
            break;
        case 'textarea':
            $o .= '<textarea id="' . h($id) . '" name="v_' . h($n) . '" rows="4">' . h($val) . '</textarea>';
            break;
        case 'date':
            $o .= '<input type="date" id="' . h($id) . '" name="v_' . h($n) . '" value="' . h($val) . '">';
            break;
        case 'int':
            $o .= '<input type="number" id="' . h($id) . '" name="v_' . h($n) . '" value="' . h((string)(int)$val) . '" step="1">';
            break;
        case 'check':
            $o .= '<span class="chk"><input type="hidden" name="v_' . h($n) . '" value="0">'
                . '<label><input type="checkbox" id="' . h($id) . '" name="v_' . h($n) . '" value="1"' . ((string)$val === '1' ? ' checked' : '') . '> 사용 / 켜기</label></span>';
            break;
        case 'select':
            $o .= '<select id="' . h($id) . '" name="v_' . h($n) . '">';
            foreach (field_opts($f) as $k => $lab) {
                $o .= '<option value="' . h($k) . '"' . ((string)$val === (string)$k ? ' selected' : '') . '>' . h($lab) . '</option>';
            }
            $o .= '</select>';
            break;
        case 'color':
            $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$val) ? $val : '#888888';
            $o .= '<span class="clr"><input type="color" id="' . h($id) . '" name="v_' . h($n) . '" value="' . h($hex) . '">'
                . '<label><input type="checkbox" name="clr_' . h($n) . '" value="1"' . (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$val) ? ' checked' : '') . '> 이 색 사용</label></span>';
            break;
        case 'font':
            $o .= '<div class="fileF">';
            if ($val !== '' && $val !== null) { $o .= '<div class="cur">현재: ' . h(basename((string)$val)) . '</div>'; }
            $o .= '<input type="file" name="file_' . h($n) . '" accept=".ttf,.otf,.woff,.woff2" class="filein">';
            $o .= '<input type="text" name="url_' . h($n) . '" value="' . h(preg_match('#^(https?:)?//#i', (string)$val) ? (string)$val : '') . '" placeholder="또는 폰트 파일 주소(URL) 붙여넣기">';
            if ($val !== '' && $val !== null) { $o .= '<label class="chk"><input type="checkbox" name="del_' . h($n) . '" value="1"> 현재 폰트 삭제</label>'; }
            $o .= '</div>';
            break;
        case 'images':
            $arr = [];
            if (is_string($val) && trim($val) !== '') { $d = json_decode($val, true); if (is_array($d)) $arr = $d; }
            $o .= '<div class="imgs-field" data-field="' . h($n) . '"' . (!empty($f['media']) ? ' data-media="1"' : '') . '>';
            $o .= '<div class="imgs-list"></div>';
            $o .= '<div class="imgs-add">';
            $o .= '<input type="file" class="imgs-file" accept="' . (!empty($f['media']) ? 'image/*,video/mp4,video/webm' : 'image/*') . '" multiple>';
            $o .= '<input type="text" class="imgs-url" placeholder="또는 이미지 주소(URL) 붙여넣기">';
            $o .= '<button type="button" class="btn sm ghost imgs-addurl">주소 추가</button>';
            $o .= '</div>';
            $o .= '<input type="hidden" name="v_' . h($n) . '" class="imgs-json" value=\'' . h(json_encode(array_values($arr), JSON_UNESCAPED_SLASHES)) . '\'>';
            $o .= '</div>';
            break;
        case 'icon':
        case 'image':
        case 'audio':
            $isImg = ($f['t'] === 'image' || $f['t'] === 'icon');
            $ext = (preg_match('#^(https?:)?//#i', (string)$val)) ? (string)$val : '';
            $o .= '<div class="fileF">';
            if ($val !== '' && $val !== null) {
                if ($isImg) { $o .= '<div class="prev"><img src="' . h(asset($val)) . '" alt=""></div>'; }
                else { $o .= '<div class="prev"><audio controls preload="none" src="' . h(asset($val)) . '"></audio></div>'; }
                $o .= '<div class="cur">현재: ' . h(basename((string)$val)) . '</div>';
            }
            $o .= '<input type="file" name="file_' . h($n) . '" accept="' . ($f['t'] === 'icon' ? 'image/*,.ico' : ($isImg ? 'image/*' : 'audio/*')) . '" class="filein">';
            $o .= '<input type="text" name="url_' . h($n) . '" value="' . h($ext) . '" placeholder="또는 ' . ($isImg ? '이미지' : '음악 파일') . ' 주소(URL) 붙여넣기">';
            if ($val !== '' && $val !== null) {
                $o .= '<label class="chk"><input type="checkbox" name="del_' . h($n) . '" value="1"> 현재 파일 삭제</label>';
            }
            $o .= '</div>';
            break;
        case 'html':
            $o .= '<div class="htmled">'
                . '<div class="ed-bar">'
                . '<button type="button" data-cmd="bold" title="굵게"><b>B</b></button>'
                . '<button type="button" data-cmd="italic" title="기울임"><i>I</i></button>'
                . '<button type="button" data-cmd="underline" title="밑줄"><u>U</u></button>'
                . '<button type="button" data-cmd="strikeThrough" title="취소선"><s>S</s></button>'
                . '<label class="ed-c" title="글자색">A<input type="color" class="ed-color" value="#c0392b"></label>'
                . '<select class="ed-font" title="글꼴"><option value="">글꼴</option>' . editor_font_options() . '</select>'
                . '<label class="ed-c" title="글씨 배경색(형광펜)">&#9646;<input type="color" class="ed-bg" value="#fff2a8"></label>'
                . '<select class="ed-size" title="글자 크기"><option value="">크기</option><option value="2">작게</option><option value="3">보통</option><option value="5">크게</option><option value="6">아주 크게</option></select>'
                . '<button type="button" data-cmd="justifyLeft" title="왼쪽 정렬">◧</button>'
                . '<button type="button" data-cmd="justifyCenter" title="가운데 정렬">◫</button>'
                . '<button type="button" data-cmd="justifyRight" title="오른쪽 정렬">◨</button>'
                . '<button type="button" data-cmd="insertUnorderedList" title="목록">• 목록</button>'
                . '<button type="button" data-cmd="insertHorizontalRule" title="구분선">―</button>'
                . '<button type="button" data-cmd="link" title="링크">링크</button>'
                . '<button type="button" data-cmd="image" title="이미지 삽입">이미지</button>'
                . '<button type="button" data-cmd="imgs" title="본문 이미지 보기/교체 (깨진 이미지 바꾸기)">이미지 관리</button>'
                . '<button type="button" data-cmd="clean" title="서식 지우기">서식 지움</button>'
                . '<button type="button" data-cmd="source" class="src" title="HTML 직접 편집">HTML</button>'
                . '<input type="file" class="ed-file" accept="image/*" hidden>'
                . '</div>'
                . '<div class="ed-area" contenteditable="true" spellcheck="false"></div>'
                . '<textarea id="' . h($id) . '" name="v_' . h($n) . '" rows="14" class="ed-src">' . h($val) . '</textarea>'
                . '</div>';
            break;
    }
    return $o . $help . '</div>';
}

function editor_font_options() {
    $o = '';
    foreach (editor_fonts() as $stack => $label) { $o .= '<option value="' . h($stack) . '">' . h($label) . '</option>'; }
    return $o;
}

/** 폼에서 값을 읽어 저장할 값을 반환. 오류는 $errs에 추가 */
function collect_field($f, $old, &$errs) {
    $n = $f['n'];
    $post = function ($k, $d = '') { return isset($_POST[$k]) && !is_array($_POST[$k]) ? (string)$_POST[$k] : $d; };
    switch ($f['t']) {
        case 'text':
            return cut(trim($post('v_' . $n)), 255);
        case 'textarea':
        case 'code':
            return trim($post('v_' . $n));
        case 'html':
            return sanitize_html($post('v_' . $n));
        case 'date':
            $v = $post('v_' . $n);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        case 'int':
            return (string)(int)$post('v_' . $n, '0');
        case 'check':
            return $post('v_' . $n) === '1' ? '1' : '0';
        case 'select':
            $opts = field_opts($f);
            $v = $post('v_' . $n);
            if (isset($opts[$v])) return $v;
            if ($old !== null && isset($opts[$old])) return $old;
            reset($opts);
            return (string)key($opts);
        case 'color':
            $v = $post('v_' . $n);
            if ($post('clr_' . $n) === '1' && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) return strtolower($v);
            return '';
        case 'images':
            $raw = $post('v_' . $n);
            $arr = json_decode($raw, true);
            if (!is_array($arr)) $arr = [];
            $clean = [];
            foreach ($arr as $u) {
                $u = trim((string)$u);
                if ($u === '') continue;
                if (preg_match('#^(https?:)?//#i', $u) || preg_match('#^[A-Za-z0-9_\-./%]+$#', $u)) { $clean[] = cut($u, 500); }
                if (count($clean) >= 40) break;
            }
            return json_encode($clean, JSON_UNESCAPED_SLASHES);
        case 'font':
        case 'icon':
        case 'image':
        case 'audio':
            $cur = (string)$old;
            $kind = $f['t'];
            if (isset($_FILES['file_' . $n]) && $_FILES['file_' . $n]['error'] !== UPLOAD_ERR_NO_FILE) {
                list($path, $err) = save_upload($_FILES['file_' . $n], $kind);
                if ($err) { $errs[] = '[' . $f['l'] . '] ' . $err; return $cur; }
                if ($path) {
                    if ($n === 'cursor_img' || $n === 'cursor_hover_img') { shrink_image_if_needed(ROOT . '/' . $path, 48); }
                    elseif ($kind === 'icon') { shrink_image_if_needed(ROOT . '/' . $path, 512); }
                    elseif ($kind === 'image') { shrink_image_if_needed(ROOT . '/' . $path, 2400); }
                    return $path;
                }
            }
            $u = trim($post('url_' . $n));
            if ($u !== '' && $u !== $cur) {
                // 외부 주소(http/https) 또는 이 사이트 uploads 폴더 안의 파일만 받아요
                if (preg_match('#^(https?:)?//#i', $u) || (preg_match('#^uploads/[A-Za-z0-9_\-./%]+$#', $u) && strpos($u, '..') === false)) return cut($u, 500);
                $errs[] = '[' . $f['l'] . '] 주소 형식이 올바르지 않아요.';
                return $cur;
            }
            if ($post('del_' . $n) === '1') return '';
            return $cur;
    }
    return '';
}
