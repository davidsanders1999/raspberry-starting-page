<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$container_ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());

$docker_host_ip = null;
$docker_gateway = null;

if (file_exists('/proc/net/route')) {
    $routes = file('/proc/net/route');
    foreach ($routes as $route) {
        $parts = preg_split('/\s+/', trim($route));
        if (isset($parts[1]) && $parts[1] === '00000000' && isset($parts[2])) {
            $gateway_hex = $parts[2];
            $gateway_parts = str_split($gateway_hex, 2);
            $gateway_parts = array_reverse($gateway_parts);
            $docker_gateway = hexdec($gateway_parts[0]) . '.' . 
                            hexdec($gateway_parts[1]) . '.' . 
                            hexdec($gateway_parts[2]) . '.' . 
                            hexdec($gateway_parts[3]);
            $docker_host_ip = $docker_gateway;
            break;
        }
    }
}

if (! $docker_host_ip && ! empty($_SERVER['DOCKER_HOST_IP'])) {
    $docker_host_ip = $_SERVER['DOCKER_HOST_IP'];
}

if (!$docker_host_ip) {
    $container_parts = explode('. ', $container_ip);
    if (count($container_parts) === 4 && $container_parts[0] === '172') {
        $docker_host_ip = $container_parts[0] . '.' . 
                         $container_parts[1] .  '.' . 
                         $container_parts[2] . '. 1';
        $docker_gateway = $docker_host_ip;
    }
}

$client_ip = '';
if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $client_ip = trim($ips[0]);
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $client_ip = $_SERVER['HTTP_X_REAL_IP'];
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
    $client_ip = $_SERVER['REMOTE_ADDR'];
} else {
    $client_ip = 'Nicht verfügbar';
}

$hostname = gethostname();

$system_info = [
    'status' => 'success',
    'php_version' => phpversion(),
    'container_ip' => $container_ip,
    'container_hostname' => $hostname,
    'docker_host_ip' => $docker_host_ip,
    'docker_gateway' => $docker_gateway,
    'client_ip' => $client_ip,
    'server_ip' => $docker_host_ip ??  $container_ip,
    'server_name' => $_SERVER['SERVER_NAME'] ?? $hostname,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Nicht verfügbar',
    'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Nicht verfügbar',
    'timestamp' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get()
];

echo json_encode($system_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
