<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Versuche die interne IP-Adresse des Raspberry Pi Hosts zu ermitteln (nicht Container-IP)
$pi_ip = null;

// Hilfsfunktion: Prüft ob eine IP eine Docker Bridge IP ist
function isDockerBridgeIP($ip) {
    // Docker Bridge IPs: 172.16.0.0/12, 192.168.0.0/16 (Docker-Bereich)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        // 172.16.0.0 - 172.31.255.255 (Docker Standard Bridge)
        if ($parts[0] == 172 && $parts[1] >= 16 && $parts[1] <= 31) {
            return true;
        }
        // 192.168.0.0/16 könnte Docker sein, aber auch lokales Netzwerk
        // Wir ignorieren nur wenn es eindeutig Docker ist (z.B. 192.168.65.x für Docker Desktop)
        if ($parts[0] == 192 && $parts[1] == 168 && $parts[2] == 65) {
            return true;
        }
    }
    return false;
}

// Methode 1: Über Umgebungsvariable (falls gesetzt)
if (empty($pi_ip) && !empty($_SERVER['HOST_IP'])) {
    $host_ip = $_SERVER['HOST_IP'];
    if (filter_var($host_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !isDockerBridgeIP($host_ip)) {
        $pi_ip = $host_ip;
    }
}

// Methode 2: Über Docker Host-Netzwerk (host.docker.internal oder Gateway)
if (!$pi_ip && function_exists('shell_exec')) {
    // Versuche host.docker.internal aufzulösen
    $host_ip = trim(shell_exec("getent hosts host.docker.internal 2>/dev/null | awk '{print $1}'"));
    if ($host_ip && filter_var($host_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !isDockerBridgeIP($host_ip)) {
        $pi_ip = $host_ip;
    }
}

// Methode 2b: Über Docker-Befehl die Host-IP ermitteln
if (!$pi_ip && function_exists('shell_exec')) {
    // Versuche die Host-IP über Docker zu ermitteln
    $docker_host_ip = trim(shell_exec("docker inspect -f '{{range .NetworkSettings.Networks}}{{.Gateway}}{{end}}' \$(hostname) 2>/dev/null"));
    if ($docker_host_ip && filter_var($docker_host_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !isDockerBridgeIP($docker_host_ip)) {
        $pi_ip = $docker_host_ip;
    }
}

// Methode 3: Über ip route get - ermittelt die Source-IP für externe Verbindungen
if (!$pi_ip && function_exists('shell_exec')) {
    $commands = [
        // Ermittelt die Source-IP für Verbindungen zu 8.8.8.8
        "ip route get 8.8.8.8 2>/dev/null | grep -oP 'src \\K[0-9.]+' | head -1",
        // Alternative mit awk (falls grep -P nicht verfügbar)
        "ip route get 8.8.8.8 2>/dev/null | awk '{for(i=1;i<=NF;i++){if(\\$i==\"src\"){print \\$(i+1);exit}}}'",
    ];
    
    foreach ($commands as $cmd) {
        $result = trim(shell_exec($cmd));
        if ($result && filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && !isDockerBridgeIP($result)) {
            $pi_ip = $result;
            break;
        }
    }
}

// Methode 4: Über Docker Gateway, aber nur wenn es keine Docker Bridge IP ist
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
            if (filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !isDockerBridgeIP($gateway)) {
                $pi_ip = $gateway;
                break;
            }
        }
    }
}

// Methode 5: Über alle Netzwerk-Interfaces und filtere Docker Bridge IPs raus
if (!$pi_ip && function_exists('shell_exec')) {
    $commands = [
        "ip -4 addr show | grep -oP 'inet \\K[0-9.]+' | grep -v '^127\\.'",
        "hostname -I 2>/dev/null | awk '{for(i=1;i<=NF;i++) print \\$i}'"
    ];
    
    foreach ($commands as $cmd) {
        $results = trim(shell_exec($cmd));
        if ($results) {
            $ips = preg_split('/\s+/', $results);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && !isDockerBridgeIP($ip)) {
                    $pi_ip = $ip;
                    break 2;
                }
            }
        }
    }
}

// Methode 6: Über Client-IP wenn sie vom lokalen Netzwerk kommt (192.168.x.x)
if (!$pi_ip && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $client_ip = trim($ips[0]);
    if (filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !isDockerBridgeIP($client_ip)) {
        // Wenn Client-IP im lokalen Netzwerk ist, könnte das die Host-IP sein
        $parts = explode('.', $client_ip);
        if ($parts[0] == 192 && $parts[1] == 168) {
            // Versuche die Host-IP im gleichen Netzwerk zu ermitteln
            // Dies ist ein Fallback - normalerweise sollte eine der obigen Methoden funktionieren
        }
    }
}

// Fallback: Wenn nichts funktioniert
if (!$pi_ip) {
    $pi_ip = 'Nicht verfügbar';
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
