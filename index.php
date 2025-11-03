<?php
declare(strict_types=1);

// CORS + JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

// Default: hari ini berdasarkan offset jam tz
function ymdToday(int $tz = 7): string {
	$nowUtc = new DateTime('now', new DateTimeZone('UTC'));
	$nowUtc->modify(($tz >= 0 ? '+' : '-') . abs($tz) . ' hours');
	return $nowUtc->format('Ymd'); // YYYYMMDD
}

$tz     = isset($_GET['tz']) ? (int)$_GET['tz'] : 7;
$cc     = $_GET['cc']     ?? 'ID';
$locale = $_GET['locale'] ?? 'id';
$date   = $_GET['date']   ?? ymdToday($tz);

// Endpoint LiveScore MEV
$upstream = "https://prod-cdn-mev-api.livescore.com/v1/api/app/date/soccer/{$date}/{$tz}"
	. '?countryCode=' . rawurlencode($cc)
	. '&locale=' . rawurlencode($locale);

// cURL fetch
$ch = curl_init($upstream);
curl_setopt_array($ch, [
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_CONNECTTIMEOUT => 8,
	CURLOPT_TIMEOUT        => 15,
	CURLOPT_HTTPHEADER     => [
		'Accept: application/json',
		'User-Agent: wasmer-php-proxy/1.0'
	],
]);

$body = curl_exec($ch);
$err  = curl_error($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $code >= 400 || $code === 0) {
	http_response_code(502);
	echo json_encode([
		'ok'      => false,
		'status'  => $code,
		'error'   => $err ?: 'Upstream error',
		'upstream'=> $upstream
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
}

// Jika valid JSON, kirimkan sebagai objek; jika tidak, teruskan mentah
$decoded = json_decode($body, true);
if (json_last_error() === JSON_ERROR_NONE) {
	echo json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
	echo $body;
}
