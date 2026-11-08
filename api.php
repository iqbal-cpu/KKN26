<?php
const KODE = 'kemadang';
const COLS = ['L', 'M', 'Lg'];

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['HTTP_X_KODE'] ?? '') !== KODE) { http_response_code(401); exit('{"err":"kode"}'); }

$db = new PDO('sqlite:' . __DIR__ . '/data.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA busy_timeout=5000;
CREATE TABLE IF NOT EXISTS items(col TEXT, id TEXT, data TEXT, PRIMARY KEY(col,id));
CREATE TABLE IF NOT EXISTS meta(k TEXT PRIMARY KEY, v TEXT)');

$a = $_GET['a'] ?? '';
$b = json_decode(file_get_contents('php://input'), true) ?? [];
$col = $b['col'] ?? '';

try {
  if ($a === 'all') {
    $o = ['L' => [], 'M' => [], 'Lg' => []];
    foreach ($db->query('SELECT col,data FROM items ORDER BY rowid') as $r) $o[$r['col']][] = json_decode($r['data']);
    $p = $db->query("SELECT v FROM meta WHERE k='P'")->fetchColumn();
    $o['P'] = $p ? json_decode($p) : ['s' => '', 'h' => 45, 'k' => 2];
    echo json_encode($o);
  } elseif ($a === 'put' && in_array($col, COLS) && isset($b['item']['id'])) {
    $db->prepare('INSERT INTO items VALUES(?,?,?) ON CONFLICT(col,id) DO UPDATE SET data=excluded.data')
       ->execute([$col, (string)$b['item']['id'], json_encode($b['item'])]);
    echo '{"ok":1}';
  } elseif ($a === 'del' && in_array($col, COLS)) {
    $db->prepare('DELETE FROM items WHERE col=? AND id=?')->execute([$col, (string)($b['id'] ?? '')]);
    echo '{"ok":1}';
  } elseif ($a === 'p') {
    $db->prepare("INSERT INTO meta VALUES('P',?) ON CONFLICT(k) DO UPDATE SET v=excluded.v")->execute([json_encode($b)]);
    echo '{"ok":1}';
  } elseif ($a === 'up') {
    $f = $_FILES['f'] ?? null;
    $e = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
    if (!$f || $f['error'] || $f['size'] > 15 * 1024 * 1024 || !in_array($e, ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'pptx', 'docx', 'xlsx'])) {
      http_response_code(400); exit('{"err":"file"}');
    }
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads');
    $n = uniqid() . '.' . $e;
    move_uploaded_file($f['tmp_name'], __DIR__ . "/uploads/$n");
    echo json_encode(['url' => "uploads/$n", 'name' => $f['name']]);
  } else { http_response_code(400); echo '{"err":"aksi"}'; }
} catch (Exception $ex) { http_response_code(500); echo json_encode(['err' => 'server']); }
