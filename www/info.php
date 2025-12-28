<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Versuche die interne IP-Adresse des Raspberry Pi zu ermitteln
$pi_ip = null;

// Methode 1: Über SERVER_ADDR (wenn verfügbar)
if (!empty($_SERVER['SERVER_ADDR']) && filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    $pi_ip = $_SERVER['SERVER_ADDR'];
}

// Methode 2: Über Hostname
if (!$pi_ip) {
    $hostname = gethostname();
    $ip = gethostbyname($hostname);
    if ($ip !== $hostname && filter_var($ip, FILTER_VALIDATE_IP)) {
        $pi_ip = $ip;
    }
}

// Methode 3: Über Netzwerk-Interfaces (Linux)
if (!$pi_ip && function_exists('shell_exec')) {
    // Versuche eth0 oder wlan0 Interface
    $commands = [
        "hostname -I | awk '{print $1}'",
        "ip route get 8.8.8.8 | awk '{print $7}' | head -1",
        "ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1' | head -1"
    ];
    
    foreach ($commands as $cmd) {
        $result = trim(shell_exec($cmd . ' 2>/dev/null'));
        if ($result && filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $pi_ip = $result;
            break;
        }
    }
}

// Methode 4: Docker Gateway (falls in Container)
if (!$pi_ip && file_exists('/proc/net/route')) {
    $routes = file('/proc/net/route');
    foreach ($routes as $route) {
        $parts = preg_split('/\s+/', trim($route));
        if (isset($parts[1]) && $parts[1] === '00000000' && isset($parts[2])) {
            $gateway_hex = $parts[2];
            $gateway_parts = str_split($gateway_hex, 2);
            $gateway_parts = array_reverse($gateway_parts);
            $gateway = hexdec($gateway_parts[0]) . '.' . 
                       hexdec($gateway_parts[1]) . '.' . 
                       hexdec($gateway_parts[2]) . '.' . 
                       hexdec($gateway_parts[3]);
            if (filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $pi_ip = $gateway;
                break;
            }
        }
    }
}

// Fallback
if (!$pi_ip) {
    $pi_ip = $_SERVER['SERVER_ADDR'] ?? 'Nicht verfügbar';
}

$client_ip = '';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $client_ip = trim($ips[0]);
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $client_ip = $_SERVER['HTTP_X_REAL_IP'];
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
    $client_ip = $_SERVER['REMOTE_ADDR'];
} else {
    $client_ip = 'Nicht verfügbar';
}

$response = [
    'status' => 'success',
    'server_ip' => $pi_ip,
    'client_ip' => $client_ip,
    'php_version' => phpversion(),
    'hostname' => gethostname(),
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
