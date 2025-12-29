<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Versuche die interne IP-Adresse des Raspberry Pi Hosts zu ermitteln (nicht Container-IP)
$pi_ip = null;

// Methode 1: Docker Gateway ermitteln (beste Methode für Container)
// Das Docker Gateway ist normalerweise die IP des Hosts
if (file_exists('/proc/net/route')) {
    $routes = file('/proc/net/route');
    foreach ($routes as $route) {
        $parts = preg_split('/\s+/', trim($route));
        // Suche nach Standard-Route (Destination 00000000)
        if (isset($parts[1]) && $parts[1] === '00000000' && isset($parts[2])) {
            $gateway_hex = $parts[2];
            $gateway_parts = str_split($gateway_hex, 2);
            $gateway_parts = array_reverse($gateway_parts);
            $gateway = hexdec($gateway_parts[0]) . '.' . 
                       hexdec($gateway_parts[1]) . '.' . 
                       hexdec($gateway_parts[2]) . '.' . 
                       hexdec($gateway_parts[3]);
            if (filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // Gateway ist normalerweise die Host-IP bei Docker
                $pi_ip = $gateway;
                break;
            }
        }
    }
}

// Methode 2: Über ip route get (ermittelt die IP des Interfaces für externe Verbindungen)
if (!$pi_ip && function_exists('shell_exec')) {
    $commands = [
        // Ermittelt die Source-IP für Verbindungen zu 8.8.8.8 (Host-IP, nicht Container-IP)
        "ip route get 8.8.8.8 2>/dev/null | grep -oP 'src \\K[0-9.]+' | head -1",
        // Alternative: Ermittelt die IP des Interfaces, das die Standard-Route verwendet
        "ip route show default 2>/dev/null | awk '/default/ {print $5}' | xargs -I {} ip addr show {} 2>/dev/null | grep -oP 'inet \\K[0-9.]+' | head -1",
        // Fallback: Erste nicht-localhost IPv4
        "ip -4 addr show | grep -oP 'inet \\K[0-9.]+' | grep -v '^127\\.' | head -1"
    ];
    
    foreach ($commands as $cmd) {
        $result = trim(shell_exec($cmd));
        if ($result && filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $pi_ip = $result;
            break;
        }
    }
}

// Methode 3: Über hostname -I (gibt alle IPs zurück, erste ist meist die Host-IP)
if (!$pi_ip && function_exists('shell_exec')) {
    $result = trim(shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
    if ($result && filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $pi_ip = $result;
    }
}

// Fallback: Wenn nichts funktioniert, versuche SERVER_ADDR (kann aber Container-IP sein)
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
