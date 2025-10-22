<?php
require_once 'config.php';
date_default_timezone_set('America/Havana');

$sql = "SELECT * FROM horarios";
$result = $conn->query($sql);

if ($result) {
    $horarios = [];
    while ($row = $result->fetch_assoc()) {
        $horarios[] = $row;
    }

    $response = [
        'server_time' => [
            'timestamp' => time(),
            'day_of_week' => strtolower(date('l')), 
            'day_of_week_spanish' => ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'][(int)date('w')],
            'current_time_minutes' => (int)date('H')*60 + (int)date('i'),
            'formatted_date' => date('Y-m-d H:i:s')
        ],
        'schedule' => $horarios
    ];

    sendResponse($response);
} else {
    sendResponse(['error' => 'Error al obtener horarios: ' . $conn->error], 500);
}

$conn->close();
?>
