<?php

if (preg_match('/\.(?:png|jpg|jpeg|gif|ico)$/', $_SERVER["REQUEST_URI"])) {
    return false; // пусть PHP сервер сам отдаёт файл
}

require __DIR__ . '/index.php';

?>