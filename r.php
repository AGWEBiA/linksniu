<?php
// r.php — redirecionador de links curtos (ir.niucursos.com.br)
// Gerado automaticamente pelo AG Link Tracker.
// Coloque este arquivo na raiz do site (mesma pasta do .htaccess).

$SUPA   = 'https://trackapi.agwebia.com.br';
$KEY    = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJyb2xlIjoiYW5vbiIsImlzcyI6InN1cGFiYXNlIiwiaWF0IjoxNzkxMjI3NjM0LCJleHAiOjE5NDg5MDc2MzR9.nw4TouHG6uHenj9gmJzmFSYE2xaLJjkhIC6l1BH1_7c';
// Dominio configurado no cliente/projeto (gerado automaticamente).
// Este redirect so atende slugs de links cujo projeto ou cliente
// esta configurado com ESTE dominio — slugs de outros dominios
// retornam 404 aqui.
$DOMAIN = 'ir.niucursos.com.br';

// Versao deste arquivo (o painel avisa quando o r.php instalado esta desatualizado)
$VERSION = '2026-10-09';

// Identificacao do script (usada pelo botao "Testar agora" do painel)
header('X-AG-Tracker: 1');
header('X-AG-Domain: ' . $DOMAIN);
header('X-AG-Version: ' . $VERSION);

// IP real do visitante (usado para descobrir pais/cidade no analytics).
// Atras do Cloudflare ou de proxy, REMOTE_ADDR e o IP do proxy.
$ip = ''; $ipFrom = '';
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) { $ip = $_SERVER['HTTP_CF_CONNECTING_IP']; $ipFrom = 'cf-connecting-ip'; }
elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]); $ipFrom = 'x-forwarded-for'; }
elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) { $ip = $_SERVER['HTTP_X_REAL_IP']; $ipFrom = 'x-real-ip'; }
else { $ip = $_SERVER['REMOTE_ADDR'] ?? ''; $ipFrom = 'remote_addr'; }
$ipPublic = (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

// Auto-diagnostico: /r.php?agcheck=1
if (isset($_GET['agcheck'])) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ag' => true, 'domain' => $DOMAIN, 'v' => $VERSION, 'php' => PHP_VERSION, 'curl' => function_exists('curl_init'), 'ip_from' => $ipFrom, 'ip_public' => $ipPublic]);
  exit;
}

$slug = isset($_GET['slug']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['slug']) : '';
if (!$slug) { http_response_code(400); exit('slug ausente'); }

$ch = curl_init("$SUPA/rest/v1/tracked_links?pretty_slug=eq.$slug&select=id,full_url,projects(short_domain,workspaces(short_domain))");
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => ["apikey: $KEY", "Authorization: Bearer $KEY"],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 5,
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

// Aceita o link apenas se o dominio bate com o configurado no projeto
// (prioridade) ou no cliente do projeto.
$link = null;
foreach ((array)$resp as $row) {
  $p  = isset($row['projects']) ? $row['projects'] : null;
  if (!is_array($p)) continue;
  $pd = isset($p['short_domain']) ? $p['short_domain'] : null;
  $w  = isset($p['workspaces']) && is_array($p['workspaces']) ? $p['workspaces'] : null;
  $wd = $w && isset($w['short_domain']) ? $w['short_domain'] : null;
  if ($pd === $DOMAIN || (!$pd && $wd === $DOMAIN)) { $link = $row; break; }
}
if (empty($link['full_url'])) { http_response_code(404); exit('Link nao encontrado'); }

// Monta a URL final: repassa parametros de anuncio e adiciona o codigo do clique
$CLICK_KEYS = ['gclid','gbraid','wbraid','fbclid','ttclid','msclkid','twclid','li_fat_id','epik','irclickid','sck','xcod','aff'];
$clickCode  = substr(str_replace('.', '', uniqid('', true)), -12);

$dest = $link['full_url'];
$parts = parse_url($dest);
parse_str($parts['query'] ?? '', $destParams);

$clickParams = [];
foreach ($_GET as $k => $v) {
  if ($k === 'slug' || !is_string($v)) continue;
  if (in_array(strtolower($k), $CLICK_KEYS, true)) { $clickParams[$k] = $v; $destParams[$k] = $v; }
  elseif (!isset($destParams[$k])) { $destParams[$k] = $v; }
}
$destParams['lt'] = $clickCode;

$dest = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
  . (isset($parts['port']) ? ':' . $parts['port'] : '')
  . ($parts['path'] ?? '')
  . '?' . http_build_query($destParams)
  . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

$utm = [];
foreach (['utm_source','utm_medium','utm_campaign','utm_term','utm_content','src'] as $k) {
  if (!empty($destParams[$k])) $utm[$k] = $destParams[$k];
}

// Robos e pre-visualizacoes nao contam como clique real
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot = $ua === '' || preg_match('/(bot|crawl|spider|slurp|preview|fetch|monitor|headless|curl|wget|python-requests|axios|postman|facebookexternalhit|whatsapp|telegrambot|discordbot|slackbot|linkedinbot|twitterbot|embedly|pingdom|uptime|lighthouse|gtmetrix|semrush|ahrefs|dataprovider)/i', $ua) === 1;

// Registra o clique (nao bloqueia o redirect por muito tempo)
$payload = json_encode([
  'link_id'         => $link['id'],
  'ip_address'      => $ip ?: null,
  'user_agent'      => $ua ?: null,
  'referer'         => $_SERVER['HTTP_REFERER']    ?? null,
  'utm'             => (object)$utm,
  'click_params'    => (object)$clickParams,
  'destination_url' => $dest,
  'click_code'      => $clickCode,
  'is_bot'          => $isBot,
]);
$ch = curl_init("$SUPA/rest/v1/link_clicks");
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => $payload,
  CURLOPT_HTTPHEADER => [
    "apikey: $KEY", "Authorization: Bearer $KEY",
    "Content-Type: application/json", "Prefer: return=minimal",
  ],
  CURLOPT_TIMEOUT => 2,
  CURLOPT_RETURNTRANSFER => true,
]);
curl_exec($ch); curl_close($ch);

header("Location: " . $dest, true, 302);
exit;
