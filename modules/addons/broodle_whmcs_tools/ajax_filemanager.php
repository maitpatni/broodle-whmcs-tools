<?php
/**
 * Broodle WHMCS Tools — File Manager AJAX Handler
 * Included from ajax.php — all variables ($action, $protocol, $hostname, $port, etc.) are available.
 */

switch ($action) {

case 'fm_list':
    $dir = isset($_POST['dir']) ? trim($_POST['dir']) : '/';
    if ($dir === '' || $dir === '.') $dir = '/';
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=3"
         . "&cpanel_jsonapi_module=Fileman"
         . "&cpanel_jsonapi_func=list_files"
         . "&dir=" . urlencode($dir)
         . "&include_mime=1"
         . "&include_permissions=1"
         . "&include_hash=0"
         . "&check_for_leaf_directories=1";
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, 30);
    if ($r['code'] !== 200 || !$r['body']) { echo json_encode(['success' => false, 'message' => 'Failed to list files']); break; }
    $json = json_decode($r['body'], true);
    $status = $json['result']['status'] ?? 0;
    if (!$status) { echo json_encode(['success' => false, 'message' => $json['result']['errors'][0] ?? 'Failed']); break; }
    $items = $json['result']['data'] ?? [];
    $files = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $name = $item['file'] ?? ($item['name'] ?? '');
        if ($name === '.' || $name === '..') continue;
        $files[] = [
            'name'  => $name,
            'type'  => ($item['type'] ?? '') === 'dir' ? 'dir' : 'file',
            'size'  => (int)($item['size'] ?? 0),
            'mtime' => $item['mtime'] ?? ($item['ctime'] ?? ''),
            'perms' => $item['niceperms'] ?? ($item['humanperms'] ?? ($item['permissions'] ?? '')),
            'rawperms' => $item['permissions'] ?? '',
            'mime'  => $item['mimetype'] ?? ($item['mime'] ?? ''),
            'path'  => rtrim($dir, '/') . '/' . $name,
        ];
    }
    // Get home directory
    $homedir = '';
    $urlH = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=getdir";
    $rH = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $urlH);
    if ($rH['code'] === 200 && $rH['body']) {
        $jh = json_decode($rH['body'], true);
        $homedir = $jh['cpanelresult']['data'][0]['homedir'] ?? ($jh['cpanelresult']['data'][0]['dir'] ?? '');
    }
    echo json_encode(['success' => true, 'files' => $files, 'dir' => $dir, 'homedir' => $homedir]);
    break;

case 'fm_read':
    $filePath = isset($_POST['file']) ? trim($_POST['file']) : '';
    if (empty($filePath)) { echo json_encode(['success' => false, 'message' => 'No file specified']); break; }
    $dir = dirname($filePath); $file = basename($filePath);
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=get_file_content"
         . "&dir=" . urlencode($dir) . "&file=" . urlencode($file)
         . "&from_charset=utf-8&to_charset=utf-8";
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, 30);
    if ($r['code'] !== 200 || !$r['body']) { echo json_encode(['success' => false, 'message' => 'Failed to read file']); break; }
    $json = json_decode($r['body'], true);
    if (!($json['result']['status'] ?? 0)) { echo json_encode(['success' => false, 'message' => $json['result']['errors'][0] ?? 'Failed']); break; }
    echo json_encode(['success' => true, 'content' => $json['result']['data']['content'] ?? '', 'file' => $filePath]);
    break;

case 'fm_save':
    $filePath = isset($_POST['file']) ? trim($_POST['file']) : '';
    $content  = isset($_POST['content']) ? $_POST['content'] : '';
    if (empty($filePath)) { echo json_encode(['success' => false, 'message' => 'No file specified']); break; }
    $dir = dirname($filePath); $file = basename($filePath);
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=save_file_content"
         . "&dir=" . urlencode($dir) . "&file=" . urlencode($file)
         . "&from_charset=utf-8&to_charset=utf-8&content=" . urlencode($content);
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, 30);
    $p = broodle_ajax_parse_result($r);
    echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'File saved' : ($p['error'] ?? 'Failed')]);
    break;

case 'fm_create_file':
    $dir = isset($_POST['dir']) ? trim($_POST['dir']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    if (!$name) { echo json_encode(['success' => false, 'message' => 'Missing file name']); break; }
    if (!$dir || $dir === '/') {
        // Get home directory to use as base
        $urlH = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=getdir";
        $rH = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $urlH);
        if ($rH['code'] === 200 && $rH['body']) {
            $jh = json_decode($rH['body'], true);
            $hd = $jh['cpanelresult']['data'][0]['homedir'] ?? ($jh['cpanelresult']['data'][0]['dir'] ?? '');
            if ($hd) $dir = $hd;
        }
    }
    // Try UAPI first (apiversion=3)
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=save_file_content"
         . "&dir=" . urlencode($dir) . "&file=" . urlencode($name)
         . "&from_charset=utf-8&to_charset=utf-8&content=";
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
    $p = broodle_ajax_parse_result($r);
    if (!$p['ok']) {
        // Fallback to API2 mkfile
        $url2 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=mkfile"
             . "&dir=" . urlencode($dir) . "&name=" . urlencode($name);
        $r2 = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url2);
        $p = broodle_ajax_parse_result($r2);
    }
    echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'File created' : ($p['error'] ?? 'Failed')]);
    break;

case 'fm_create_folder':
    $dir = isset($_POST['dir']) ? trim($_POST['dir']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    if (!$name) { echo json_encode(['success' => false, 'message' => 'Missing folder name']); break; }
    if (!$dir || $dir === '/') {
        $urlH = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=getdir";
        $rH = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $urlH);
        if ($rH['code'] === 200 && $rH['body']) {
            $jh = json_decode($rH['body'], true);
            $hd = $jh['cpanelresult']['data'][0]['homedir'] ?? ($jh['cpanelresult']['data'][0]['dir'] ?? '');
            if ($hd) $dir = $hd;
        }
    }
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=mkdir"
         . "&dir=" . urlencode($dir) . "&name=" . urlencode($name);
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
    $p = broodle_ajax_parse_result($r);
    if (!$p['ok']) {
        // Fallback to API2
        $url2 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=mkdir"
             . "&dir=" . urlencode($dir) . "&name=" . urlencode($name);
        $r2 = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url2);
        $p = broodle_ajax_parse_result($r2);
    }
    echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Folder created' : ($p['error'] ?? 'Failed')]);
    break;

case 'fm_delete':
    $items = isset($_POST['items']) ? $_POST['items'] : '';
    if (empty($items)) { echo json_encode(['success' => false, 'message' => 'No items']); break; }
    $itemList = json_decode($items, true);
    if (!is_array($itemList) || empty($itemList)) { echo json_encode(['success' => false, 'message' => 'Invalid items']); break; }
    $errors = [];
    foreach ($itemList as $item) {
        $d = dirname($item); $f = basename($item);
        $ok = false;
        /* Method 1: API2 Fileman fileop unlink (most reliable) */
        $url1 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
              . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
              . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop"
              . "&op=unlink&sourcefiles=" . urlencode($item);
        $r1 = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url1);
        $b1 = json_decode($r1['body'] ?? '', true);
        if (isset($b1['cpanelresult']['data'][0]['result']) && $b1['cpanelresult']['data'][0]['result'] == 1) $ok = true;
        if (!$ok && isset($b1['cpanelresult']['event']['result']) && $b1['cpanelresult']['event']['result'] == 1) $ok = true;
        if (!$ok) {
            /* Method 2: UAPI Fileman::trash */
            $url2 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                  . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                  . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=trash"
                  . "&dir=" . urlencode($d) . "&files-0=" . urlencode($f);
            $r2 = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url2);
            $b2 = json_decode($r2['body'] ?? '', true);
            if (isset($b2['result']['status']) && $b2['result']['status'] == 1) $ok = true;
        }
        if (!$ok) {
            $reason = $b1['cpanelresult']['data'][0]['reason'] ?? ($b1['cpanelresult']['error'] ?? 'Failed to delete');
            $errors[] = $f . ': ' . $reason;
        }
    }
    echo json_encode(empty($errors) ? ['success' => true, 'message' => count($itemList) . ' item(s) deleted'] : ['success' => false, 'message' => implode('; ', $errors)]);
    break;

case 'fm_rename':
    $oldPath = isset($_POST['old']) ? trim($_POST['old']) : '';
    $newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
    if (!$oldPath || !$newName) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); break; }
    $dir = dirname($oldPath); $oldName = basename($oldPath);
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=rename"
         . "&dir=" . urlencode($dir) . "&oldname=" . urlencode($oldName) . "&newname=" . urlencode($newName);
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
    $p = broodle_ajax_parse_result($r);
    echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Renamed' : ($p['error'] ?? 'Failed')]);
    break;

case 'fm_copy':
    $source = isset($_POST['source']) ? trim($_POST['source']) : '';
    $dest = isset($_POST['dest']) ? trim($_POST['dest']) : '';
    if (!$source || !$dest) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); break; }
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop"
         . "&op=copy&sourcefiles=" . urlencode($source) . "&destfiles=" . urlencode($dest);
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
    $p = broodle_ajax_parse_result($r);
    echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Copied' : ($p['error'] ?? 'Failed')]);
    break;

case 'fm_move':
    $source = isset($_POST['source']) ? trim($_POST['source']) : '';
    $dest = isset($_POST['dest']) ? trim($_POST['dest']) : '';
    if (!$source || !$dest) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); break; }
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop"
         . "&op=move&sourcefiles=" . urlencode($source) . "&destfiles=" . urlencode($dest);
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
    $p = broodle_ajax_parse_result($r);
    echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Moved' : ($p['error'] ?? 'Failed')]);
    break;

case 'fm_upload':
    $dir = isset($_POST['dir']) ? trim($_POST['dir']) : '';
    if (!$dir) { echo json_encode(['success' => false, 'message' => 'No directory']); break; }
    if (empty($_FILES['file'])) { echo json_encode(['success' => false, 'message' => 'No file']); break; }
    $f = $_FILES['file'];
    $uploadUrl = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=upload_files"
         . "&dir=" . urlencode($dir);
    $headers = [];
    if (!empty($accessHash)) $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
    $cfile = new CURLFile($f['tmp_name'], $f['type'] ?? 'application/octet-stream', $f['name']);
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $uploadUrl, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['file-1' => $cfile]]);
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    elseif (!empty($password)) curl_setopt($ch, CURLOPT_USERPWD, "{$serverUser}:{$password}");
    $resp = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($httpCode !== 200 || !$resp) { echo json_encode(['success' => false, 'message' => 'Upload failed']); break; }
    $json = json_decode($resp, true);
    echo json_encode(($json['result']['status'] ?? 0) ? ['success' => true, 'message' => 'Uploaded'] : ['success' => false, 'message' => $json['result']['errors'][0] ?? 'Failed']);
    break;

case 'fm_permissions':
    $filePath = isset($_POST['file']) ? trim($_POST['file']) : '';
    $perms = isset($_POST['perms']) ? trim($_POST['perms']) : '';
    if (!$filePath || !$perms) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); break; }
    $dir = dirname($filePath); $file = basename($filePath);
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=setperms"
         . "&dir=" . urlencode($dir) . "&file=" . urlencode($file) . "&perms=" . urlencode($perms);
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
    $p = broodle_ajax_parse_result($r);
    echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Permissions updated' : ($p['error'] ?? 'Failed')]);
    break;

case 'fm_compress':
    $items = isset($_POST['items']) ? $_POST['items'] : '';
    $dest = isset($_POST['dest']) ? trim($_POST['dest']) : '';
    if (!$items || !$dest) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); break; }
    $itemList = json_decode($items, true);
    if (!is_array($itemList) || empty($itemList)) { echo json_encode(['success' => false, 'message' => 'Invalid']); break; }
    // Use UAPI Fileman::save_file_content won't work for compress — use API2 fileop
    // API2 fileop compress: sourcefiles is comma-separated list, destfiles is the archive name
    // Each source file needs to be the full path
    $sourceStr = implode(',', $itemList);
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop"
         . "&op=compress&sourcefiles=" . urlencode($sourceStr) . "&destfiles=" . urlencode($dest);
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, 120);
    if ($r['code'] === 200 && $r['body']) {
        $json = json_decode($r['body'], true);
        $cpResult = $json['cpanelresult']['data'][0] ?? [];
        $ok = !empty($cpResult['result']) || (isset($cpResult['status']) && $cpResult['status']);
        if (!$ok) {
            // Check if there's a different success indicator
            $ok = (isset($json['cpanelresult']['event']['result']) && $json['cpanelresult']['event']['result'] == 1);
        }
        $err = $cpResult['reason'] ?? ($cpResult['error'] ?? 'Compress failed');
        echo json_encode($ok ? ['success' => true, 'message' => 'Compressed successfully'] : ['success' => false, 'message' => $err]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to connect']);
    }
    break;

case 'fm_extract':
    $filePath = isset($_POST['file']) ? trim($_POST['file']) : '';
    $dest = isset($_POST['dest']) ? trim($_POST['dest']) : '';
    if (!$filePath) { echo json_encode(['success' => false, 'message' => 'No file']); break; }
    if (!$dest) $dest = dirname($filePath);
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop"
         . "&op=extract&sourcefiles=" . urlencode($filePath) . "&destfiles=" . urlencode($dest);
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, 120);
    if ($r['code'] === 200 && $r['body']) {
        $json = json_decode($r['body'], true);
        $cpResult = $json['cpanelresult']['data'][0] ?? [];
        $ok = !empty($cpResult['result']) || (isset($cpResult['status']) && $cpResult['status']);
        if (!$ok) {
            $ok = (isset($json['cpanelresult']['event']['result']) && $json['cpanelresult']['event']['result'] == 1);
        }
        $err = $cpResult['reason'] ?? ($cpResult['error'] ?? 'Extract failed');
        echo json_encode($ok ? ['success' => true, 'message' => 'Extracted successfully'] : ['success' => false, 'message' => $err]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to connect']);
    }
    break;

case 'fm_search':
    $dir = isset($_POST['dir']) ? trim($_POST['dir']) : '/';
    $query = isset($_POST['query']) ? trim($_POST['query']) : '';
    if (!$query) { echo json_encode(['success' => false, 'message' => 'No query']); break; }
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=search"
         . "&dir=" . urlencode($dir) . "&regex=" . urlencode($query);
    $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, 30);
    if ($r['code'] !== 200 || !$r['body']) { echo json_encode(['success' => false, 'message' => 'Search failed']); break; }
    $json = json_decode($r['body'], true);
    $data = $json['cpanelresult']['data'] ?? [];
    $results = [];
    foreach ($data as $item) {
        if (!is_array($item)) continue;
        $results[] = ['file' => $item['file'] ?? '', 'dir' => $item['dir'] ?? '', 'path' => ($item['dir'] ?? '') . '/' . ($item['file'] ?? '')];
    }
    echo json_encode(['success' => true, 'results' => $results]);
    break;

case 'fm_download_url':
    $filePath = isset($_POST['file']) ? trim($_POST['file']) : '';
    if (!$filePath) { echo json_encode(['success' => false, 'message' => 'No file']); break; }
    $ssoUrl = "{$protocol}://{$hostname}:{$port}/json-api/create_user_session?api.version=1&user=" . urlencode($cpUsername) . "&service=cpaneld";
    $hdrs = [];
    if (!empty($accessHash)) $hdrs[] = "Authorization: whm {$serverUser}:{$accessHash}";
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $ssoUrl, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
    if (!empty($hdrs)) curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
    elseif (!empty($password)) curl_setopt($ch, CURLOPT_USERPWD, "{$serverUser}:{$password}");
    $resp = curl_exec($ch); curl_close($ch);
    $json = json_decode($resp, true);
    $sessionUrl = $json['data']['url'] ?? '';
    if (!$sessionUrl) { echo json_encode(['success' => false, 'message' => 'Session failed']); break; }
    // Extract the cpsess base URL (e.g. https://host:2083/cpsessXXXX)
    if (preg_match('#(https?://[^/]+/cpsess[^/]+)#', $sessionUrl, $m)) {
        $baseSession = $m[1];
        $downloadUrl = $baseSession . '/download?skipencode=1&file=' . urlencode($filePath);
    } else {
        // Fallback: append goto_uri
        $downloadUrl = $sessionUrl . (strpos($sessionUrl, '?') !== false ? '&' : '?') . 'goto_uri=' . urlencode('/download?skipencode=1&file=' . urlencode($filePath));
    }
    echo json_encode(['success' => true, 'url' => $downloadUrl]);
    break;

}
